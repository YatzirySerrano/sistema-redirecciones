<?php

namespace App\Actions\Productos;

use App\Enums\TipoProducto;
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

        // El número de QR solo aplica a productos QR y combinados.
        if ($producto->isDirty('tipo') && ! $producto->esEnlaceSecundario()) {
            $producto->forceFill(['cantidad_qr' => $producto->tipo === TipoProducto::Nfc ? null : ($producto->cantidad_qr ?? 1)]);
        }

        if (! $producto->isDirty()) {
            return $producto;
        }

        $cambios = array_keys($producto->getDirty());
        $anteriores = array_intersect_key($producto->getOriginal(), array_flip($cambios));
        $producto->save();

        // Los enlaces secundarios (reverso QR, QR 1 / QR 2) comparten los
        // datos del producto físico (nombre, preparación, entrega, referencias).
        $producto->todosLosEnlacesSecundarios()->each(fn (Producto $enlace) => $enlace->forceFill(
            $producto->only(['nombre', 'estado_preparacion', 'fecha_entrega', 'referencia_comercial', 'notas_internas']),
        )->save());

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
