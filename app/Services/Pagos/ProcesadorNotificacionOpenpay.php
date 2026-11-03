<?php

namespace App\Services\Pagos;

use App\Models\IntentoPago;
use App\Models\NotificacionPago;
use App\Models\Pago;
use App\Services\Auditoria\RegistradorAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa notificaciones (webhooks) de Openpay YA autenticadas por el
 * controlador (HTTP Basic configurado en el panel de Openpay).
 *
 * Openpay no firma sus notificaciones, por eso su contenido se trata solo
 * como un AVISO: se toma el identificador de la transacción y se consulta
 * el cargo a la API de Openpay con la llave privada. El estado aplicado es
 * siempre el que devuelve la API.
 *
 * - Idempotente: cada notificación (tipo + transacción + fecha + estado)
 *   se procesa una sola vez; Openpay reintenta hasta recibir 200 y puede
 *   enviar la misma notificación más de una vez.
 * - Si la API no responde se lanza la excepción: la notificación no queda
 *   marcada y Openpay la reintentará.
 * - Tipos desconocidos se aceptan (200) y se registran.
 */
class ProcesadorNotificacionOpenpay
{
    public function __construct(
        private readonly PasarelaPago $pasarela,
        private readonly ConciliadorPagos $conciliador,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function procesar(array $datos): string
    {
        $tipo = mb_substr((string) ($datos['type'] ?? 'desconocido'), 0, 60);
        $fecha = $this->fecha($datos['event_date'] ?? null);

        if ($tipo === 'verification') {
            return $this->verificacion($datos, $fecha);
        }

        $transaccion = is_array($datos['transaction'] ?? null) ? $datos['transaction'] : [];
        $transaccionId = isset($transaccion['id']) && is_string($transaccion['id']) ? mb_substr($transaccion['id'], 0, 100) : null;
        $orden = isset($transaccion['order_id']) && is_string($transaccion['order_id']) ? $transaccion['order_id'] : null;

        $clave = hash('sha256', implode('|', ['openpay', $tipo, $transaccionId, $fecha?->toIso8601String(), $transaccion['status'] ?? '']));

        if (NotificacionPago::query()->where('clave_unica', $clave)->whereNotNull('procesado_en')->exists()) {
            return 'duplicado';
        }

        $intento = $this->intento($transaccionId, $orden);

        if ($intento === null) {
            $this->registrar($clave, $tipo, $transaccionId, $fecha, null, 'ignorado: referencia inexistente');

            return 'ignorado: referencia inexistente';
        }

        // Consulta autoritativa (fuera de la transacción de base de datos).
        $cargo = $intento->transaccion_id !== null
            ? $this->pasarela->consultarCargo($intento->transaccion_id)
            : $this->pasarela->buscarPorOrden($intento->orden_externa);

        if ($cargo === null) {
            $this->registrar($clave, $tipo, $transaccionId, $fecha, $intento, 'ignorado: el cargo no existe en Openpay');

            return 'ignorado: el cargo no existe en Openpay';
        }

        try {
            return DB::transaction(function () use ($clave, $tipo, $transaccionId, $fecha, $intento, $cargo) {
                $registro = NotificacionPago::query()->where('clave_unica', $clave)->lockForUpdate()->first();

                if ($registro?->procesado_en !== null) {
                    return 'duplicado';
                }

                $resultado = $this->conciliador->aplicar($intento, $cargo, 'notificación', $tipo);
                $this->registrar($clave, $tipo, $transaccionId, $fecha, $intento, $resultado, $registro);

                return $resultado;
            });
        } catch (QueryException $e) {
            // Dos entregas simultáneas: la segunda choca con la clave única.
            if (str_contains($e->getMessage(), 'clave_unica')) {
                return 'duplicado';
            }

            throw $e;
        }
    }

    /**
     * Openpay envía un código al registrar el webhook; DASTI lo introduce
     * en el panel de Openpay para activarlo. Se guarda cifrado y se
     * muestra en Administración › Pagos.
     *
     * @param  array<string, mixed>  $datos
     */
    private function verificacion(array $datos, ?CarbonImmutable $fecha): string
    {
        $codigo = mb_substr((string) ($datos['verification_code'] ?? ''), 0, 60);

        if ($codigo === '') {
            return 'ignorado: verificación sin código';
        }

        $clave = hash('sha256', 'openpay|verification|'.$codigo);

        try {
            NotificacionPago::query()->firstOrCreate(['clave_unica' => $clave], [
                'proveedor' => Pago::PROVEEDOR_OPENPAY,
                'tipo' => 'verification',
                'ocurrido_en' => $fecha,
                'codigo_verificacion' => $codigo,
                'resultado' => 'código de verificación recibido',
                'procesado_en' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        $this->auditoria->registrar(
            accion: 'pago.webhook_verificacion',
            descripcion: 'Openpay envió el código de verificación del webhook',
        );

        return 'verificación recibida';
    }

    private function intento(?string $transaccionId, ?string $orden): ?IntentoPago
    {
        if ($transaccionId !== null && ($intento = IntentoPago::query()->where('transaccion_id', $transaccionId)->first()) !== null) {
            return $intento;
        }

        return $orden !== null ? IntentoPago::query()->where('orden_externa', $orden)->first() : null;
    }

    private function registrar(string $clave, string $tipo, ?string $transaccionId, ?CarbonImmutable $fecha, ?IntentoPago $intento, string $resultado, ?NotificacionPago $registro = null): void
    {
        $datos = [
            'proveedor' => Pago::PROVEEDOR_OPENPAY,
            'tipo' => $tipo,
            'transaccion_id' => $transaccionId,
            'intento_pago_id' => $intento?->id,
            'pago_id' => $intento?->pago_id,
            'ocurrido_en' => $fecha,
            'resultado' => mb_substr($resultado, 0, 255),
            'procesado_en' => now(),
        ];

        $registro !== null
            ? $registro->forceFill($datos)->save()
            : NotificacionPago::query()->updateOrCreate(['clave_unica' => $clave], $datos);
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        try {
            return is_string($valor) && $valor !== '' ? CarbonImmutable::parse($valor) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
