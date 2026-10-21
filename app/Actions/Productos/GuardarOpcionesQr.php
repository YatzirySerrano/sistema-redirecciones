<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use App\Services\Qr\OpcionesQr;
use Illuminate\Validation\ValidationException;

/**
 * Persiste el diseño del QR de un producto, rechazando combinaciones que
 * comprometen la lectura.
 */
class GuardarOpcionesQr
{
    public function ejecutar(Producto $producto, OpcionesQr $opciones): Producto
    {
        if (! $opciones->esLegible()) {
            throw ValidationException::withMessages([
                'color' => 'El contraste entre el código y el fondo es insuficiente. Usa un color de código más oscuro que el fondo.',
            ]);
        }

        if ($opciones->incluirLogo && $producto->negocio->logo_path === null) {
            throw ValidationException::withMessages([
                'incluir_logo' => 'El negocio no tiene un logotipo cargado.',
            ]);
        }

        $producto->forceFill(['opciones_qr' => $opciones->aArreglo()])->save();

        return $producto;
    }
}
