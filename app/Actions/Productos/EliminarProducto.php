<?php

namespace App\Actions\Productos;

use App\Enums\EstadoPreparacion;
use App\Models\Producto;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Elimina un producto registrado por error. Solo se permite mientras sigue
 * pendiente de preparación y nunca ha recibido accesos: un producto
 * entregado o en uso debe suspenderse, no borrarse.
 */
class EliminarProducto
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * Se evalúa el producto físico completo (incluidos sus enlaces
     * secundarios): ninguno puede tener accesos.
     */
    public static function puedeEliminarse(Producto $producto): bool
    {
        if ($producto->esEnlaceSecundario()) {
            return false;
        }

        $enlaces = [$producto, ...$producto->todosLosEnlacesSecundarios()->get()->all()];

        return $producto->estado_preparacion === EstadoPreparacion::Pendiente
            && collect($enlaces)->every(fn (Producto $p) => $p->total_accesos === 0 && ! $p->eventosAcceso()->exists());
    }

    public function ejecutar(Producto $producto): void
    {
        if (! self::puedeEliminarse($producto)) {
            throw ValidationException::withMessages([
                'producto' => 'Solo se pueden eliminar productos pendientes de preparación y sin accesos. Suspéndelo en su lugar.',
            ]);
        }

        DB::transaction(function () use ($producto) {
            $this->auditoria->registrar(
                accion: 'producto.eliminado',
                descripcion: "Eliminación de «{$producto->nombre}» (registrado por error)",
                recurso: $producto,
                negocio: $producto->negocio_id,
                anteriores: $producto->only(['nombre', 'tipo', 'codigo', 'url_destino']),
            );

            // Los enlaces secundarios no se borran solos (clave foránea
            // restrictiva): se eliminan primero, junto con su historial.
            $producto->todosLosEnlacesSecundarios()->get()->each(function (Producto $enlace) {
                $enlace->cambiosDestino()->delete();
                $enlace->delete();
            });
            $producto->delete();
        });
    }
}
