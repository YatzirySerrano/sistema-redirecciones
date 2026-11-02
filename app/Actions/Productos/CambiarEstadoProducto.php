<?php

namespace App\Actions\Productos;

use App\Enums\EstadoProducto;
use App\Models\Producto;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;

/**
 * Suspende o reactiva un producto. Un producto suspendido no redirige y su
 * cliente no puede modificar el destino.
 */
class CambiarEstadoProducto
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * El estado es del producto físico: se aplica a la fila principal y a
     * sus enlaces secundarios (frente NFC y reverso QR quedan igual).
     */
    public function ejecutar(Producto $producto, EstadoProducto $estado, ?string $motivo = null): Producto
    {
        $producto = $producto->principal ?? $producto;

        if ($producto->estado === $estado) {
            return $producto;
        }

        $anterior = $producto->estado;
        $suspender = $estado === EstadoProducto::Suspendido;

        DB::transaction(function () use ($producto, $estado, $suspender) {
            $cambios = ['estado' => $estado, 'suspendido_en' => $suspender ? now() : null];
            $producto->forceFill($cambios)->save();
            $producto->enlacesSecundarios()->each(fn (Producto $enlace) => $enlace->forceFill($cambios)->save());
        });

        $this->auditoria->registrar(
            accion: $suspender ? 'producto.suspendido' : 'producto.reactivado',
            descripcion: ($suspender ? 'Suspensión' : 'Reactivación')." de «{$producto->nombre}»",
            recurso: $producto,
            negocio: $producto->negocio_id,
            anteriores: ['estado' => $anterior],
            nuevos: array_filter(['estado' => $estado, 'motivo' => $motivo]),
        );

        return $producto;
    }
}
