<?php

namespace App\Http\Controllers;

use App\Actions\Productos\GuardarOpcionesQr;
use App\Http\Requests\OpcionesQrRequest;
use App\Models\Producto;
use App\Services\Qr\ErrorGeneracionQr;
use App\Services\Qr\GeneradorQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Vista previa, descarga y diseño del QR de un producto (administrador y
 * cliente propietario). La autorización se aplica en OpcionesQrRequest
 * (ProductoPolicy::descargarQr para ver/descargar y ::disenarQr para guardar).
 */
class QrController extends Controller
{
    public function show(OpcionesQrRequest $request, Producto $producto, GeneradorQr $generador): Response
    {
        $producto->loadMissing('negocio');
        $opciones = $request->opciones($producto->opciones_qr);

        if (! $opciones->esLegible()) {
            throw ValidationException::withMessages([
                'color' => 'El contraste entre el código y el fondo es insuficiente para garantizar la lectura.',
            ]);
        }

        try {
            $qr = $generador->generar(
                $producto,
                $opciones,
                (string) $request->validated('formato', 'png'),
                (int) $request->validated('tamano', 1024),
            );
        } catch (ErrorGeneracionQr $e) {
            // JSON 422 para la vista previa (fetch); redirección con errores
            // si se abrió el enlace directamente.
            throw ValidationException::withMessages(['qr' => $e->getMessage()]);
        }

        $disposicion = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($qr['contenido'], 200, [
            'Content-Type' => $qr['mime'],
            'Content-Disposition' => $disposicion.'; filename="'.$qr['nombre_archivo'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Un SVG abierto directamente no debe poder ejecutar nada.
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
        ]);
    }

    public function update(OpcionesQrRequest $request, Producto $producto, GuardarOpcionesQr $accion): RedirectResponse
    {
        $producto->loadMissing('negocio');
        $accion->ejecutar($producto, $request->opciones($producto->opciones_qr), $request->user());

        $this->notificar('Diseño del QR guardado.');

        return back();
    }
}
