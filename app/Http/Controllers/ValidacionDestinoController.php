<?php

namespace App\Http\Controllers;

use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comprobación en vivo de una URL de destino con las mismas reglas que se
 * aplican al guardar (ValidadorUrlDestino). Así el formulario nunca muestra
 * como válida una dirección que el servidor rechazará.
 *
 * Solo valida el texto: no descarga ni consulta la página de destino.
 */
class ValidacionDestinoController extends Controller
{
    public function __invoke(Request $request, ValidadorUrlDestino $validador): JsonResponse
    {
        $request->validate(['url' => ['required', 'string', 'max:4096']]);

        $url = (string) $request->input('url');
        $error = $validador->error($url);

        if ($error !== null) {
            return response()->json(['valida' => false, 'mensaje' => $error]);
        }

        $normalizada = $validador->normalizar($url);
        $partes = parse_url($normalizada);

        return response()->json([
            'valida' => true,
            'mensaje' => null,
            'url' => $normalizada,
            'dominio' => preg_replace('/^www\./', '', strtolower((string) ($partes['host'] ?? ''))),
            'segura' => strtolower((string) ($partes['scheme'] ?? '')) === 'https',
        ]);
    }
}
