<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * Actualiza los datos administrativos de un producto (no su destino, su
 * propietario ni su código).
 */
class ActualizarProducto
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(Producto $producto, array $datos): Producto
    {
        $producto->fill($datos);

        if (! $producto->isDirty()) {
            return $producto;
        }

        $cambios = array_keys($producto->getDirty());
        $anteriores = array_intersect_key($producto->getOriginal(), array_flip($cambios));
        $producto->save();

        $this->auditoria->registrar(
            accion: 'producto.actualizado',
            descripcion: "Actualización de datos de «{$producto->nombre}»",
            recurso: $producto,
            negocio: $producto->negocio_id,
            anteriores: $anteriores,
            nuevos: $producto->only($cambios),
        );

        return $producto;
    }
}
