<?php

namespace App\Services\Pagos;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoIntentoPago;
use App\Enums\EstadoPago;
use App\Enums\OrigenContratacion;
use App\Models\AjustePago;
use App\Models\Contratacion;
use App\Models\IntentoPago;
use App\Models\Pago;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Support\Facades\DB;

/**
 * Aplica a una orden de pago el estado de un cargo CONSULTADO al proveedor
 * desde el servidor. Es el único punto que confirma pagos y activa
 * servicios por pago en línea.
 *
 * - Autoritativo: recibe el cargo leído de la API del proveedor (no el
 *   contenido de una notificación ni parámetros del navegador).
 * - Valida que el cargo corresponda exactamente al intento: referencia,
 *   transacción, importe, moneda y ambiente (pruebas/producción).
 * - Idempotente y tolerante al orden: como siempre se aplica el estado
 *   vigente del cargo, una notificación repetida o atrasada no revierte un
 *   estado más reciente; la activación ocurre una sola vez por pago
 *   (bloqueo de fila + `pagos.contratacion_id`).
 * - Reembolsos y contracargos siguen las decisiones comerciales: total →
 *   suspensión (si no hay otro derecho vigente); parcial → revisión de
 *   DASTI; contracargo → restricción temporal y revisión. Nunca afecta a
 *   las redirecciones QR/NFC.
 */
class ConciliadorPagos
{
    public function __construct(
        private readonly GestorContrataciones $contrataciones,
        private readonly RegistradorAuditoria $auditoria,
        private readonly PasarelaPago $pasarela,
    ) {}

    /**
     * @param  string  $origen  «notificación», «retorno», «conciliación» o «administración».
     * @param  string|null  $evento  Tipo de notificación (pista para contracargos).
     * @return string Resultado legible.
     */
    public function aplicar(IntentoPago $intento, CargoExterno $cargo, string $origen, ?string $evento = null): string
    {
        return DB::transaction(function () use ($intento, $cargo, $origen, $evento) {
            /** @var IntentoPago $intento */
            $intento = IntentoPago::query()->lockForUpdate()->findOrFail($intento->id);
            /** @var Pago $pago */
            $pago = Pago::query()->with(['negocio', 'servicio'])->lockForUpdate()->findOrFail($intento->pago_id);

            if (($error = $this->validar($intento, $pago, $cargo)) !== null) {
                $pago->forceFill(['requiere_revision' => true, 'motivo' => mb_substr("Verificación fallida: {$error}", 0, 255)])->save();
                $this->auditoria->registrar(
                    accion: 'pago.rechazado_validacion',
                    descripcion: "Cargo de Openpay rechazado ({$origen}): {$error}",
                    recurso: $pago,
                    negocio: $pago->negocio_id,
                    nuevos: ['transaccion' => $cargo->id, 'estado_externo' => $cargo->estado],
                );

                return "rechazado: {$error}";
            }

            $this->registrarDatos($intento, $cargo);

            if ($cargo->reembolsadoCentavos > 0 || $cargo->estado === 'refunded') {
                return $this->reembolso($intento, $pago, $cargo, $origen);
            }

            return match ($cargo->estado) {
                'completed' => $evento === 'chargeback.rejected' || $pago->estado === EstadoPago::EnDisputa
                    ? $this->disputaGanada($intento, $pago, $cargo, $evento)
                    : $this->completado($intento, $pago, $origen),
                'charge_pending', 'in_progress' => $this->pendiente($intento),
                'failed' => $this->terminado($intento, $pago, EstadoIntentoPago::Fallido, EstadoPago::Fallido, $cargo->mensajeError ?? 'La tarjeta fue rechazada.'),
                'cancelled' => $this->terminado($intento, $pago, EstadoIntentoPago::Cancelado, EstadoPago::Cancelado, 'El cargo fue cancelado.'),
                'chargeback_pending' => $this->disputaAbierta($intento, $pago, $cargo),
                'chargeback_accepted', 'chargeback_adjustment' => $this->disputaPerdida($intento, $pago, $cargo),
                default => 'sin cambios: estado no reconocido «'.mb_substr($cargo->estado, 0, 30).'»',
            };
        });
    }

    /**
     * Comprueba que el cargo corresponde exactamente al intento registrado.
     */
    private function validar(IntentoPago $intento, Pago $pago, CargoExterno $cargo): ?string
    {
        return match (true) {
            $intento->livemode !== $this->pasarela->modoReal() => 'el ambiente del pago (pruebas/producción) no coincide con la configuración actual',
            $intento->transaccion_id !== null && $cargo->id !== $intento->transaccion_id => 'la transacción no corresponde al intento registrado',
            $cargo->ordenId !== $intento->orden_externa => 'la referencia del cargo no corresponde a la orden',
            $cargo->moneda !== strtoupper($pago->moneda) || $intento->moneda !== $pago->moneda => 'la moneda no coincide',
            $cargo->importeCentavos !== $intento->importe_centavos || $intento->importe_centavos !== $pago->importe_centavos => 'el importe no coincide',
            default => null,
        };
    }

    private function registrarDatos(IntentoPago $intento, CargoExterno $cargo): void
    {
        $intento->forceFill(array_filter([
            'transaccion_id' => $intento->transaccion_id ?? $cargo->id,
            'estado_externo' => mb_substr($cargo->estado, 0, 40),
            'autorizacion' => $cargo->autorizacion,
            'marca_tarjeta' => $cargo->marcaTarjeta,
            'tipo_tarjeta' => $cargo->tipoTarjeta,
            'terminacion_tarjeta' => $cargo->terminacionTarjeta,
            'codigo_error' => $cargo->codigoError,
            'mensaje_error' => $cargo->mensajeError,
        ], fn ($v) => $v !== null) + ['consultado_en' => now()])->save();
    }

    private function pendiente(IntentoPago $intento): string
    {
        if ($intento->estado === EstadoIntentoPago::Creado) {
            $intento->forceFill(['estado' => EstadoIntentoPago::Pendiente])->save();
        }

        return 'pendiente de pago';
    }

    private function completado(IntentoPago $intento, Pago $pago, string $origen): string
    {
        if ($intento->estado->abierto() || in_array($intento->estado, [EstadoIntentoPago::Fallido, EstadoIntentoPago::Cancelado, EstadoIntentoPago::Expirado], true)) {
            $intento->forceFill(['estado' => EstadoIntentoPago::Completado, 'completado_en' => now(), 'url_pago' => null])->save();
        }

        if (! in_array($pago->estado, [EstadoPago::Pendiente, EstadoPago::Fallido, EstadoPago::Cancelado, EstadoPago::Expirado], true)) {
            return 'sin cambios: el pago ya estaba confirmado';
        }

        $pago->forceFill([
            'estado' => EstadoPago::Pagado,
            'pagado_en' => now(),
            'ultimo_evento_en' => now(),
            'motivo' => null,
        ])->save();

        $this->auditoria->registrar(
            accion: 'pago.confirmado',
            descripcion: "Pago {$pago->folio} confirmado por Openpay ({$this->importe($pago->importe_centavos, $pago->moneda)}) vía {$origen}",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['transaccion' => $intento->transaccion_id, 'origen' => $origen],
        );

        // Solo el servicio comprado; activar() es idempotente por pago.
        $this->contrataciones->activar($pago->negocio, $pago->servicio, OrigenContratacion::Openpay, $pago);

        return 'pagado y servicio activado';
    }

    private function terminado(IntentoPago $intento, Pago $pago, EstadoIntentoPago $estadoIntento, EstadoPago $estadoPago, string $motivo): string
    {
        if (! $intento->estado->abierto()) {
            return 'sin cambios: el intento ya tenía un estado final';
        }

        $intento->forceFill(['estado' => $estadoIntento, 'url_pago' => null])->save();

        $otrosAbiertos = IntentoPago::query()->where('pago_id', $pago->id)->whereKeyNot($intento->id)
            ->whereIn('estado', [EstadoIntentoPago::Creado, EstadoIntentoPago::Pendiente, EstadoIntentoPago::Completado])->exists();

        if ($pago->estado === EstadoPago::Pendiente && ! $otrosAbiertos) {
            $pago->forceFill(['estado' => $estadoPago, 'motivo' => mb_substr($motivo, 0, 255)])->save();
        }

        return $estadoIntento->value;
    }

    /**
     * Total: suspende el servicio salvo que exista otro derecho vigente.
     * Parcial: revisión de DASTI, se mantiene el acceso.
     */
    private function reembolso(IntentoPago $intento, Pago $pago, CargoExterno $cargo, string $origen): string
    {
        $reembolsado = min($pago->importe_centavos, max($pago->reembolsado_centavos, $cargo->reembolsadoCentavos));
        $total = $reembolsado >= $pago->importe_centavos;

        if ($reembolsado === $pago->reembolsado_centavos && in_array($pago->estado, [EstadoPago::Reembolsado, EstadoPago::ReembolsoParcial], true)) {
            return 'sin cambios: reembolso ya registrado';
        }

        $intento->forceFill(['estado' => $total ? EstadoIntentoPago::Reembolsado : EstadoIntentoPago::ReembolsoParcial, 'url_pago' => null])->save();
        $pago->forceFill([
            'reembolsado_centavos' => $reembolsado,
            'estado' => $total ? EstadoPago::Reembolsado : EstadoPago::ReembolsoParcial,
            'requiere_revision' => ! $total || $pago->requiere_revision,
            'pagado_en' => $pago->pagado_en ?? now(),
            'motivo' => $total ? 'Reembolso total confirmado por Openpay.' : 'Reembolso parcial: requiere revisión de DASTI.',
        ])->save();

        AjustePago::query()->updateOrCreate(
            ['pago_id' => $pago->id, 'tipo' => AjustePago::REEMBOLSO],
            ['intento_pago_id' => $intento->id, 'estado' => AjustePago::APLICADO, 'importe_centavos' => $reembolsado, 'detectado_en' => now(),
                'detalle' => $total ? 'Reembolso total' : 'Reembolso parcial'],
        );

        $this->auditoria->registrar(
            accion: $total ? 'pago.reembolsado' : 'pago.reembolso_parcial',
            descripcion: $total
                ? "Reembolso total del pago {$pago->folio} confirmado por Openpay"
                : "Reembolso parcial del pago {$pago->folio} confirmado por Openpay: requiere revisión de DASTI",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['reembolsado_centavos' => $reembolsado, 'importe_centavos' => $pago->importe_centavos, 'origen' => $origen],
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if (! $total || $contratacion === null) {
            return $total ? 'reembolsado' : 'reembolso parcial: revisión de DASTI';
        }

        if ($this->contrataciones->tieneOtroDerechoVigente($contratacion)) {
            $this->contrataciones->cancelar($contratacion, 'Pago reembolsado; el negocio conserva otro periodo vigente.');

            return 'reembolsado: periodo cancelado, otro derecho vigente';
        }

        $this->contrataciones->suspender($contratacion, 'Reembolso total del pago confirmado por Openpay.', automatica: true);

        return 'reembolsado: servicio suspendido';
    }

    private function disputaAbierta(IntentoPago $intento, Pago $pago, CargoExterno $cargo): string
    {
        $ajuste = AjustePago::query()->where('pago_id', $pago->id)->where('tipo', AjustePago::CONTRACARGO)->latest('id')->first();

        if ($ajuste !== null && $ajuste->estado !== AjustePago::ABIERTO) {
            return 'sin cambios: el contracargo ya estaba resuelto';
        }

        if ($pago->estado === EstadoPago::EnDisputa) {
            return 'sin cambios: contracargo ya registrado';
        }

        $intento->forceFill(['estado' => EstadoIntentoPago::EnDisputa])->save();
        $pago->forceFill(['estado' => EstadoPago::EnDisputa, 'requiere_revision' => true, 'motivo' => 'Contracargo en revisión.'])->save();
        AjustePago::query()->create([
            'pago_id' => $pago->id, 'intento_pago_id' => $intento->id, 'tipo' => AjustePago::CONTRACARGO,
            'estado' => AjustePago::ABIERTO, 'importe_centavos' => $cargo->importeCentavos, 'detectado_en' => now(),
        ]);

        $this->auditoria->registrar(
            accion: 'pago.disputa_abierta',
            descripcion: "Contracargo abierto sobre el pago {$pago->folio}: servicio restringido para revisión",
            recurso: $pago,
            negocio: $pago->negocio_id,
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if ($contratacion !== null) {
            $this->contrataciones->restringir($contratacion, 'Contracargo en revisión.');
        }

        return 'contracargo: servicio restringido';
    }

    private function disputaGanada(IntentoPago $intento, Pago $pago, CargoExterno $cargo, ?string $evento): string
    {
        if ($pago->estado !== EstadoPago::EnDisputa) {
            // Sin disputa registrada: un cargo completado se trata normal.
            return $evento === 'chargeback.rejected' && $pago->estado === EstadoPago::Pagado
                ? 'sin cambios: no había contracargo registrado'
                : $this->completado($intento, $pago, 'notificación');
        }

        $intento->forceFill(['estado' => EstadoIntentoPago::Completado])->save();
        $pago->forceFill(['estado' => EstadoPago::Pagado, 'motivo' => 'Contracargo resuelto a favor.'])->save();
        AjustePago::query()->where('pago_id', $pago->id)->where('tipo', AjustePago::CONTRACARGO)->where('estado', AjustePago::ABIERTO)
            ->update(['estado' => AjustePago::GANADO, 'resuelto_en' => now()]);

        $this->auditoria->registrar(
            accion: 'pago.disputa_ganada',
            descripcion: "Contracargo del pago {$pago->folio} resuelto a favor de DASTI",
            recurso: $pago,
            negocio: $pago->negocio_id,
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if ($contratacion !== null && $contratacion->estado === EstadoContratacion::Restringida && ! $contratacion->restriccion_manual) {
            $this->contrataciones->levantarRestriccion($contratacion, 'Contracargo resuelto a favor.');

            return 'contracargo ganado: restricción levantada';
        }

        return 'contracargo ganado: DASTI mantiene la restricción';
    }

    private function disputaPerdida(IntentoPago $intento, Pago $pago, CargoExterno $cargo): string
    {
        if ($intento->estado === EstadoIntentoPago::ContracargoPerdido) {
            return 'sin cambios: contracargo perdido ya registrado';
        }

        $intento->forceFill(['estado' => EstadoIntentoPago::ContracargoPerdido])->save();
        $pago->forceFill([
            'estado' => EstadoPago::Reembolsado,
            'reembolsado_centavos' => $pago->importe_centavos,
            'requiere_revision' => true,
            'motivo' => 'Contracargo perdido: fondos devueltos al tarjetahabiente.',
        ])->save();

        $ajuste = AjustePago::query()->where('pago_id', $pago->id)->where('tipo', AjustePago::CONTRACARGO)->latest('id')->first();
        $ajuste === null
            ? AjustePago::query()->create(['pago_id' => $pago->id, 'intento_pago_id' => $intento->id, 'tipo' => AjustePago::CONTRACARGO,
                'estado' => AjustePago::PERDIDO, 'importe_centavos' => $cargo->importeCentavos, 'detectado_en' => now(), 'resuelto_en' => now()])
            : $ajuste->forceFill(['estado' => AjustePago::PERDIDO, 'resuelto_en' => now()])->save();

        $this->auditoria->registrar(
            accion: 'pago.disputa_perdida',
            descripcion: "Contracargo del pago {$pago->folio} resuelto en contra: fondos devueltos",
            recurso: $pago,
            negocio: $pago->negocio_id,
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if ($contratacion !== null) {
            $this->contrataciones->suspender($contratacion, 'Contracargo resuelto en contra.', automatica: true);

            return 'contracargo perdido: servicio suspendido';
        }

        return 'contracargo perdido';
    }

    private function importe(int $centavos, string $moneda): string
    {
        return '$'.number_format($centavos / 100, 2).' '.$moneda;
    }
}
