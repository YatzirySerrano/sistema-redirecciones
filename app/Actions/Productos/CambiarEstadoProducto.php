<?php

namespace App\Actions\Productos;

use App\Enums\EstadoProducto;
use App\Models\Producto;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * Suspende o reactiva un producto. Un producto suspendido no redirige y su
 * cliente no puede modificar el destino.
 */
class CambiarEstadoProducto
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function ejecutar(Producto $producto, EstadoProducto $estado, ?string $motivo = null): Producto
    {
        if ($producto->estado === $estado) {
            return $producto;
        }

        $anterior = $producto->estado;
        $suspender = $estado === EstadoProducto::Suspendido;

        $producto->forceFill([
            'estado' => $estado,
            'suspendido_en' => $suspender ? now() : null,
        ])->save();

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
