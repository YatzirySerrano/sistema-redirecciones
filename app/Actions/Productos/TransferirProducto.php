<?php

namespace App\Actions\Productos;

use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfiere un producto a otro negocio de forma controlada.
 *
 * El aislamiento se basa en negocio_id: en cuanto cambia, el negocio anterior
 * pierde todo acceso. Cada registro histórico (cambios de destino, eventos y
 * agregados diarios) guarda el negocio propietario en ese momento, así que
 * el historial queda delimitado por periodos de propiedad:
 *
 * - El retiro del destino anterior se registra en el negocio de origen.
 * - El nuevo propietario solo recibe, si se conserva el destino, un registro
 *   inicial sin URL anterior: nunca ve destinos ni actividad del dueño previo.
 * - Los contadores del producto (total y último acceso) reinician con el
 *   nuevo periodo; el histórico completo sigue en accesos_diarios y la
 *   auditoría conserva los valores previos para el administrador.
 */
class TransferirProducto
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * Se transfiere el producto físico completo: la fila principal y sus
     * enlaces secundarios (reverso QR) cambian juntos de propietario.
     */
    public function ejecutar(Producto $producto, Negocio $destino, User $actor, string $motivo, bool $conservarDestino = false): Producto
    {
        $principalId = $producto->producto_principal_id ?? $producto->id;

        return DB::transaction(function () use ($principalId, $destino, $actor, $motivo, $conservarDestino) {
            /** @var Producto $bloqueado */
            $bloqueado = Producto::query()->with('negocio')->whereKey($principalId)->lockForUpdate()->firstOrFail();
            $origen = $bloqueado->negocio;

            if ($origen->id === $destino->id) {
                throw ValidationException::withMessages(['negocio_id' => 'El producto ya pertenece a ese negocio.']);
            }

            $urlAnterior = $bloqueado->url_destino;
            $contadoresAnteriores = [
                'total_accesos' => $bloqueado->total_accesos,
                'ultimo_acceso_en' => $bloqueado->ultimo_acceso_en,
            ];

            $enlaces = [$bloqueado, ...Producto::query()->where('producto_principal_id', $bloqueado->id)->lockForUpdate()->get()->all()];

            foreach ($enlaces as $enlace) {
                $this->moverEnlace($enlace, $origen, $destino, $actor, $conservarDestino);
            }

            $detalle = [
                'negocio_anterior' => $origen->nombre_comercial,
                'negocio_nuevo' => $destino->nombre_comercial,
                'motivo' => $motivo,
                'destino_conservado' => $conservarDestino,
            ];

            $this->auditoria->registrar(
                accion: 'producto.transferido',
                descripcion: "«{$bloqueado->nombre}» transferido a «{$destino->nombre_comercial}»",
                recurso: $bloqueado,
                negocio: $origen,
                anteriores: ['negocio_id' => $origen->id, 'url_destino' => $urlAnterior, ...$contadoresAnteriores],
                nuevos: ['negocio_id' => $destino->id, ...$detalle],
                actor: $actor,
            );

            // El registro del negocio receptor no incluye la URL ni la
            // actividad del propietario anterior.
            $this->auditoria->registrar(
                accion: 'producto.recibido',
                descripcion: "«{$bloqueado->nombre}» recibido de «{$origen->nombre_comercial}»",
                recurso: $bloqueado,
                negocio: $destino,
                anteriores: ['negocio_id' => $origen->id],
                nuevos: ['negocio_id' => $destino->id, ...$detalle],
                actor: $actor,
            );

            return $bloqueado->setRelation('negocio', $destino);
        });
    }

    /**
     * Cambia de negocio un enlace: reinicia contadores, quita el logotipo del
     * QR (era del negocio anterior) y registra el destino retirado o
     * conservado en el historial de cada negocio.
     */
    private function moverEnlace(Producto $enlace, Negocio $origen, Negocio $destino, User $actor, bool $conservarDestino): void
    {
        $urlAnterior = $enlace->url_destino;
        $opcionesQr = $enlace->opciones_qr ?? [];
        $opcionesQr['incluir_logo'] = false;

        $enlace->forceFill([
            'negocio_id' => $destino->id,
            'opciones_qr' => $opcionesQr,
            'url_destino' => $conservarDestino ? $urlAnterior : null,
            'destino_actualizado_en' => $conservarDestino ? $enlace->destino_actualizado_en : now(),
            'total_accesos' => 0,
            'ultimo_acceso_en' => null,
        ])->save();

        if ($urlAnterior === null) {
            return;
        }

        // Sin conservar: el retiro solo lo ve el negocio de origen. Al
        // conservarlo: punto de partida del nuevo propietario, sin la URL previa.
        CambioDestino::query()->create([
            'producto_id' => $enlace->id,
            'negocio_id' => $conservarDestino ? $destino->id : $origen->id,
            'user_id' => $actor->id,
            'origen' => OrigenCambio::Sistema,
            'url_anterior' => $conservarDestino ? null : $urlAnterior,
            'url_nueva' => $conservarDestino ? $urlAnterior : null,
        ]);
    }
}
