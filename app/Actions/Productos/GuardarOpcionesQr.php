<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Qr\GeneradorQr;
use App\Services\Qr\OpcionesQr;
use Illuminate\Validation\ValidationException;

/**
 * Persiste el diseño del QR de un producto, rechazando combinaciones que
 * comprometen la lectura.
 */
class GuardarOpcionesQr
{
    public function __construct(
        private readonly GeneradorQr $generador,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function ejecutar(Producto $producto, OpcionesQr $opciones, User $actor): Producto
    {
        if (! $opciones->esLegible()) {
            throw ValidationException::withMessages([
                'color' => 'El contraste entre el código y el fondo es insuficiente. Usa un color de código más oscuro que el fondo.',
            ]);
        }

        if ($opciones->incluirLogo && ! $this->generador->logoDisponible($producto->negocio)) {
            throw ValidationException::withMessages([
                'incluir_logo' => 'El negocio no tiene un logotipo válido cargado.',
            ]);
        }

        $anteriores = OpcionesQr::desdeArreglo($producto->opciones_qr)->aArreglo();
        $nuevos = $opciones->aArreglo();

        if ($anteriores === $nuevos && $producto->opciones_qr !== null) {
            return $producto;
        }

        $producto->forceFill(['opciones_qr' => $nuevos])->save();

        $this->auditoria->registrar(
            accion: 'producto.qr_actualizado',
            descripcion: "Diseño del QR de «{$producto->nombre}» actualizado",
            recurso: $producto,
            negocio: $producto->negocio_id,
            anteriores: array_diff_assoc($anteriores, $nuevos),
            nuevos: array_diff_assoc($nuevos, $anteriores),
            actor: $actor,
        );

        return $producto;
    }
}
