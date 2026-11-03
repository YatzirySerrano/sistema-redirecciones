<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\Paginas\Dominios\GestorDominios;
use App\Services\Paginas\HostsPropios;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoint `ask` de Caddy (on_demand_tls): Caddy pregunta antes de emitir o
 * renovar un certificado para un host. Solo responde 200 para dominios cuyo
 * DNS ya demostró propiedad y destino (y para los hosts de DASTI). Así nadie
 * puede hacer que el servidor solicite certificados para dominios ajenos.
 *
 * Acceso: solo desde el propio servidor (127.0.0.1/::1, sin cabeceras de
 * proxy) o con el secreto compartido DASTI_PAGINAS_SECRETO_TLS.
 */
class AutorizacionDominioController extends Controller
{
    public function __invoke(Request $request, GestorDominios $dominios, HostsPropios $hosts): Response
    {
        $secreto = (string) config('dasti.paginas.dominios.secreto_autorizacion');
        $directo = in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && ! $request->headers->has('X-Forwarded-For');
        $conSecreto = $secreto !== '' && hash_equals($secreto, (string) $request->query('secreto', ''));

        if (! $directo && ! $conSecreto) {
            return response('', 403);
        }

        $host = strtolower(trim((string) $request->query('domain', '')));

        $autorizado = $host !== '' && ($hosts->esPropio($host) || $dominios->enrutable($host) !== null);

        return response('', $autorizado ? 200 : 404, ['Cache-Control' => 'no-store']);
    }
}
