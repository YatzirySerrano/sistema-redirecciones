<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\ArchivoPublico;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve los archivos publicados por los negocios (/medios/{uuid}.{ext}).
 * Sin sesión ni cookies. El nombre es aleatorio (UUID) y el contenido ya
 * fue validado y re-codificado al subirse. Se sirven con `nosniff` y una
 * CSP que impide ejecutar nada aunque se abran directamente.
 */
class MedioController extends Controller
{
    public function __invoke(string $uuid, string $extension): Response
    {
        $archivo = ArchivoPublico::query()->where('uuid', $uuid)->where('extension', $extension)->first();

        abort_if($archivo === null || ! Storage::disk('local')->exists($archivo->ruta), 404);

        return Storage::disk('local')->response($archivo->ruta, null, [
            'Content-Type' => $archivo->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Content-Disposition' => 'inline',
        ]);
    }
}
