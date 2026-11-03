<?php

namespace App\Http\Controllers\Paginas;

use App\Services\Paginas\GestorPaginas;
use App\Services\Paginas\QrPagina;
use App\Services\Paginas\ReglasSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dirección pública (slug) y su código QR.
 */
class DireccionController extends ControladorPaginas
{
    public function disponible(Request $request, ReglasSlug $reglas): JsonResponse
    {
        $request->validate(['slug' => ['required', 'string', 'max:60']]);
        $slug = $reglas->normalizar((string) $request->input('slug'));
        $pagina = $this->pagina($request);
        $error = $slug === $pagina->slug ? null : $reglas->error($slug, $pagina);

        return response()->json(['slug' => $slug, 'disponible' => $error === null, 'mensaje' => $error]);
    }

    public function update(Request $request, GestorPaginas $gestor): JsonResponse
    {
        $request->validate(['slug' => ['required', 'string', 'max:60']]);
        $pagina = $gestor->cambiarSlug($this->pagina($request), (string) $request->input('slug'), $this->usuario($request));

        return response()->json([
            'pagina' => $this->resumenPagina($pagina->fresh() ?? $pagina),
            'mensaje' => 'Dirección actualizada. La anterior seguirá redirigiendo a la nueva.',
        ]);
    }

    public function qr(Request $request, QrPagina $qr): Response
    {
        $request->validate(['formato' => ['nullable', 'in:png,svg'], 'descargar' => ['nullable', 'boolean']]);
        $pagina = $this->pagina($request);
        $formato = (string) $request->input('formato', 'png');
        $resultado = $qr->generar($pagina->urlCanonica(), $formato, 1024);
        $disposicion = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($resultado['contenido'], 200, [
            'Content-Type' => $resultado['mime'],
            'Content-Disposition' => $disposicion.'; filename="qr-'.$pagina->slug.'.'.($formato === 'svg' ? 'svg' : 'png').'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
        ]);
    }
}
