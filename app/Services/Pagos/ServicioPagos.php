<?php

namespace App\Services\Pagos;

use App\Enums\EstadoIntentoPago;
use App\Enums\EstadoPago;
use App\Models\IntentoPago;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compra de servicios adicionales con pago en línea.
 *
 * - El servicio, el importe y la moneda salen SIEMPRE de la configuración
 *   del servidor; el navegador solo indica qué servicio quiere contratar.
 * - Una orden de pago (`pagos`) puede tener varios intentos de cobro
 *   (`intentos_pago`) si la tarjeta se rechaza.
 * - Evita cobros duplicados: un bloqueo por negocio y servicio impide crear
 *   dos cargos a la vez, y un intento reciente aún abierto se reutiliza.
 * - Volver del formulario de pago no activa nada por sí mismo: el retorno
 *   CONSULTA a Openpay desde el servidor y solo el ConciliadorPagos, con
 *   el cargo confirmado por la API, activa el servicio.
 */
class ServicioPagos
{
    private const MINUTOS_REUTILIZAR = 30;

    public function __construct(
        private readonly PasarelaPago $pasarela,
        private readonly AccesoServicios $acceso,
        private readonly ConciliadorPagos $conciliador,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function motivoNoDisponible(Servicio $servicio, Negocio $negocio): ?string
    {
        if (! Servicio::esAdicional($servicio->clave)) {
            return 'Este servicio se incluye con tus productos; no requiere contratación.';
        }

        if (! $negocio->estaActivo()) {
            return 'Tu negocio está suspendido. Contacta a DASTI.';
        }

        $estado = $this->acceso->estado($negocio, $servicio->clave);

        if ($estado['activo'] && $estado['vence_en'] === null) {
            return "Tu negocio ya tiene {$servicio->nombre} activo.";
        }

        if ($estado['estado'] === 'restringida') {
            return "{$servicio->nombre} está en revisión por un pago. Contacta a DASTI.";
        }

        if ($estado['estado'] === 'suspendida') {
            return "{$servicio->nombre} está suspendido. Contacta a DASTI para reactivarlo.";
        }

        if (($motivo = $servicio->motivoNoContratable()) !== null) {
            return $motivo;
        }

        if (! $this->pasarela->configurada()) {
            return 'El pago en línea aún no está disponible. Contacta a DASTI para contratar.';
        }

        return null;
    }

    /**
     * Crea (o reutiliza) la orden y el cargo, y devuelve la URL del
     * formulario de pago de Openpay.
     *
     * @return array{pago: Pago, url: string}
     */
    public function iniciar(Servicio $servicio, Negocio $negocio, User $usuario): array
    {
        if (($motivo = $this->motivoNoDisponible($servicio, $negocio)) !== null) {
            throw ValidationException::withMessages(['servicio' => $motivo]);
        }

        $candado = Cache::lock("pago-inicio:{$negocio->id}:{$servicio->id}", 30);

        if (! $candado->get()) {
            throw ValidationException::withMessages(['servicio' => 'Ya estamos preparando tu pago. Espera unos segundos.']);
        }

        try {
            if (($reutilizable = $this->intentoReutilizable($servicio, $negocio)) !== null) {
                return ['pago' => $reutilizable->pago, 'url' => (string) $reutilizable->url_pago];
            }

            [$pago, $intento] = DB::transaction(function () use ($servicio, $negocio, $usuario) {
                $pago = Pago::query()->create([
                    'negocio_id' => $negocio->id,
                    'servicio_id' => $servicio->id,
                    'proveedor' => $this->pasarela->clave(),
                    'estado' => EstadoPago::Pendiente,
                    'importe_centavos' => (int) $servicio->precio_centavos,
                    'moneda' => $servicio->moneda,
                    'concepto' => mb_substr("{$servicio->nombre} · {$negocio->nombre_comercial}", 0, 250),
                    'livemode' => $this->pasarela->modoReal(),
                    'iniciado_por' => $usuario->id,
                ]);

                return [$pago, $this->nuevoIntento($pago, $usuario)];
            });

            try {
                $cargo = $this->pasarela->crearCargo($pago, $intento, $this->cliente($usuario, $negocio), route('cliente.servicios.retorno', ['folio' => $pago->folio]));
            } catch (ErrorPasarela $e) {
                report($e);
                $intento->forceFill(['estado' => EstadoIntentoPago::Fallido, 'mensaje_error' => 'No se pudo crear el cargo.', 'codigo_error' => $e->codigo])->save();
                $pago->forceFill(['estado' => EstadoPago::Fallido, 'motivo' => 'No se pudo crear el cargo en Openpay.'])->save();

                throw ValidationException::withMessages(['servicio' => 'No pudimos conectar con el procesador de pagos. Inténtalo nuevamente en unos minutos.']);
            }

            $intento->forceFill([
                'transaccion_id' => $cargo->id,
                'estado' => EstadoIntentoPago::Pendiente,
                'estado_externo' => mb_substr($cargo->estado, 0, 40),
                'url_pago' => $cargo->urlPago,
            ])->save();

            $this->auditoria->registrar(
                accion: 'pago.iniciado',
                descripcion: "Inicio de pago en línea de {$servicio->nombre} (folio {$pago->folio})",
                recurso: $pago,
                negocio: $negocio,
                nuevos: ['importe_centavos' => $pago->importe_centavos, 'moneda' => $pago->moneda, 'transaccion' => $cargo->id],
                actor: $usuario,
            );

            return ['pago' => $pago, 'url' => (string) $cargo->urlPago];
        } finally {
            $candado->release();
        }
    }

    /**
     * Al volver del formulario: consulta a Openpay el estado real de los
     * intentos abiertos. Nunca confía en los parámetros de la URL.
     */
    public function verificar(Pago $pago, string $origen = 'retorno'): Pago
    {
        $intentos = IntentoPago::query()->where('pago_id', $pago->id)
            ->whereIn('estado', [EstadoIntentoPago::Creado, EstadoIntentoPago::Pendiente])
            ->get();

        foreach ($intentos as $intento) {
            $this->consultarYAplicar($intento, $origen);
        }

        return $pago->refresh();
    }

    /**
     * @return string|null Resultado, o null si no se pudo consultar.
     */
    public function consultarYAplicar(IntentoPago $intento, string $origen, ?string $evento = null): ?string
    {
        try {
            $cargo = $intento->transaccion_id !== null
                ? $this->pasarela->consultarCargo($intento->transaccion_id)
                : $this->pasarela->buscarPorOrden($intento->orden_externa);
        } catch (ErrorPasarela $e) {
            report($e);

            return null;
        }

        if ($cargo === null) {
            return 'sin cargo en el proveedor';
        }

        return $this->conciliador->aplicar($intento, $cargo, $origen, $evento);
    }

    /**
     * Reembolso solicitado por DASTI desde la administración. El efecto
     * (suspensión o revisión) se aplica con el cargo confirmado por Openpay.
     */
    public function reembolsar(Pago $pago, ?int $centavos, string $motivo, User $actor): string
    {
        $intento = IntentoPago::query()->where('pago_id', $pago->id)
            ->whereIn('estado', [EstadoIntentoPago::Completado, EstadoIntentoPago::ReembolsoParcial])
            ->whereNotNull('transaccion_id')->latest('id')->first();

        if ($intento === null || $pago->proveedor !== $this->pasarela->clave()) {
            throw ValidationException::withMessages(['importe' => 'Este pago no tiene un cargo en línea que se pueda reembolsar.']);
        }

        $disponible = $pago->importe_centavos - $pago->reembolsado_centavos;

        if ($centavos !== null && ($centavos < 1 || $centavos > $disponible)) {
            throw ValidationException::withMessages(['importe' => 'El importe a reembolsar no puede ser mayor a lo cobrado pendiente de reembolso.']);
        }

        try {
            $cargo = $this->pasarela->reembolsar((string) $intento->transaccion_id, $centavos, "Reembolso {$pago->folio}: {$motivo}");
        } catch (ErrorPasarela $e) {
            report($e);

            throw ValidationException::withMessages(['importe' => 'Openpay no aceptó el reembolso. Revisa el cargo en el panel de Openpay.']);
        }

        $this->auditoria->registrar(
            accion: 'pago.reembolso_solicitado',
            descripcion: "Reembolso solicitado a Openpay para el pago {$pago->folio}",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['importe_centavos' => $centavos ?? $disponible, 'motivo' => $motivo],
            actor: $actor,
        );

        // La respuesta del reembolso puede ser el reembolso, no el cargo:
        // se vuelve a consultar el cargo para aplicar su estado vigente.
        return $this->consultarYAplicar($intento, 'administración') ?? ($cargo->estado ?: 'reembolso solicitado');
    }

    /**
     * Intentos abiertos que siguen sin confirmarse: se consultan y, si
     * superan la vigencia configurada, se dan por caducados.
     */
    public function conciliarPendientes(): int
    {
        $horas = (int) config('services.openpay.horas_expiracion', 24);
        $procesados = 0;

        IntentoPago::query()->whereIn('estado', [EstadoIntentoPago::Creado, EstadoIntentoPago::Pendiente])
            ->where('created_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->each(function (IntentoPago $intento) use ($horas, &$procesados) {
                $resultado = $this->consultarYAplicar($intento, 'conciliación');
                $intento->refresh();
                $procesados++;

                if ($resultado !== null && $intento->estado->abierto() && $intento->created_at?->lte(now()->subHours($horas))) {
                    DB::transaction(function () use ($intento) {
                        $intento->forceFill(['estado' => EstadoIntentoPago::Expirado, 'url_pago' => null])->save();
                        Pago::query()->whereKey($intento->pago_id)->where('estado', EstadoPago::Pendiente)
                            ->update(['estado' => EstadoPago::Expirado->value, 'motivo' => 'El pago no se completó a tiempo.']);
                    });
                }
            });

        return $procesados;
    }

    private function intentoReutilizable(Servicio $servicio, Negocio $negocio): ?IntentoPago
    {
        $intento = IntentoPago::query()
            ->whereHas('pago', fn ($q) => $q->where('negocio_id', $negocio->id)->where('servicio_id', $servicio->id)
                ->where('estado', EstadoPago::Pendiente)->where('importe_centavos', (int) $servicio->precio_centavos)
                ->where('moneda', $servicio->moneda))
            ->where('estado', EstadoIntentoPago::Pendiente)
            ->where('livemode', $this->pasarela->modoReal())
            ->whereNotNull('url_pago')
            ->where('created_at', '>=', now()->subMinutes(self::MINUTOS_REUTILIZAR))
            ->with('pago')
            ->latest('id')
            ->first();

        if ($intento === null) {
            return null;
        }

        // Puede que ya se haya pagado y la notificación no haya llegado.
        $this->consultarYAplicar($intento, 'reinicio');
        $intento->refresh();

        if ($intento->estado === EstadoIntentoPago::Completado) {
            throw ValidationException::withMessages(['servicio' => 'Tu pago anterior ya fue aprobado. Actualiza la página para ver el servicio activo.']);
        }

        return $intento->estado === EstadoIntentoPago::Pendiente && $intento->url_pago !== null ? $intento : null;
    }

    private function nuevoIntento(Pago $pago, User $usuario): IntentoPago
    {
        $numero = IntentoPago::query()->where('pago_id', $pago->id)->count() + 1;

        return IntentoPago::query()->create([
            'pago_id' => $pago->id,
            'proveedor' => $this->pasarela->clave(),
            // Referencia única entre todas las transacciones del comercio.
            'orden_externa' => strtolower(($this->pasarela->modoReal() ? 'dasti-' : 'dasti-sbx-').$pago->folio.'-'.$numero),
            'estado' => EstadoIntentoPago::Creado,
            'importe_centavos' => $pago->importe_centavos,
            'moneda' => $pago->moneda,
            'livemode' => $pago->livemode,
            'iniciado_por' => $usuario->id,
        ]);
    }

    /**
     * Datos mínimos del comprador que Openpay exige para el cargo.
     *
     * @return array{nombre: string, apellidos: string, email: string, telefono: string|null}
     */
    private function cliente(User $usuario, Negocio $negocio): array
    {
        $partes = preg_split('/\s+/', trim($usuario->name), 2) ?: [];
        $telefono = preg_replace('/\D/', '', (string) $negocio->telefono);

        return [
            'nombre' => $partes[0] ?? 'Cliente',
            'apellidos' => $partes[1] ?? $negocio->nombre_comercial,
            'email' => $usuario->email,
            'telefono' => is_string($telefono) && strlen($telefono) === 10 ? $telefono : null,
        ];
    }
}
