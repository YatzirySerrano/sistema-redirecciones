<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Publico\MedioController;
use App\Http\Controllers\Publico\PaginaPublicaController;
use App\Models\ArchivoPublico;
use App\Services\Paginas\Dominios\GestorDominios;
use App\Services\Paginas\HostsPropios;
use App\Services\Paginas\RespuestaPublica;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aislamiento por host. Las peticiones a hosts de DASTI siguen el
 * enrutamiento normal. Cualquier otro host debe ser un dominio personalizado
 * con DNS verificado y SOLO puede servir, para el negocio dueño de ese
 * dominio:
 *
 *   GET  /                               página de inicio
 *   GET  /{subpagina}                    subpágina
 *   GET|POST /ir/{elemento}              clic registrado + redirección
 *   POST /contacto                       formulario de contacto
 *   GET  /qr.svg                         QR de la página
 *   GET  /medios/{uuid}.{ext}            archivos de ESE negocio
 *   GET  /.well-known/dasti-verificacion/{token}   comprobación HTTPS
 *   GET  /robots.txt
 *
 * Todo lo demás (panel, administración, inicio de sesión, webhooks, enlaces
 * /r/, páginas de otros negocios) responde 404 sin llegar al enrutador ni
 * iniciar sesión. Los hosts desconocidos también responden 404.
 */
class EnrutarDominiosPagina
{
    public function __construct(private readonly HostsPropios $hosts) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->hosts->esPropio($host)) {
            return $next($request);
        }

        $request->attributes->set('pagina_publica', true);
        $respuesta = app(RespuestaPublica::class);
        $ruta = '/'.trim($request->path(), '/');
        $ruta = $ruta === '//' ? '/' : $ruta;

        // Pregunta de Caddy (on_demand_tls) desde el propio servidor: puede
        // llegar con cualquier Host.
        if ($ruta === '/interno/dominios/autorizar') {
            return $next($request);
        }

        $dominio = app(GestorDominios::class)->enrutable($host);

        if ($dominio === null) {
            return $respuesta->noDisponible(404);
        }

        $pagina = $dominio->pagina;
        $controlador = app(PaginaPublicaController::class);
        $metodo = $request->getMethod();
        $lectura = in_array($metodo, ['GET', 'HEAD'], true);

        if ($lectura && preg_match('#^/\.well-known/dasti-verificacion/([a-z0-9-]{10,64})$#', $ruta, $m)) {
            return hash_equals($dominio->token, $m[1])
                ? response($dominio->token, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'])
                : $respuesta->noDisponible(404);
        }

        if ($lectura && $ruta === '/robots.txt') {
            return response("User-agent: *\nAllow: /\nDisallow: /ir/\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($lectura && preg_match('#^/medios/([0-9a-f-]{36})\.(webp|png|jpg|gif|mp4|webm)$#', $ruta, $m)) {
            $propio = ArchivoPublico::query()->where('uuid', $m[1])->where('negocio_id', $dominio->negocio_id)
                ->where('modulo', ArchivoPublico::MODULO_PAGINAS)->exists();

            return $propio ? app(MedioController::class)($m[1], $m[2]) : $respuesta->noDisponible(404);
        }

        if (preg_match('#^/ir/([0-9a-f-]{36})$#', $ruta, $m) && ($lectura || $metodo === 'POST')) {
            return $controlador->servirClic($request, $pagina, '', $m[1]);
        }

        if ($ruta === '/contacto' && $metodo === 'POST') {
            return $controlador->servirContacto($request, $pagina);
        }

        if ($lectura && $ruta === '/qr.svg') {
            return $controlador->servirQr($request, $pagina);
        }

        if ($lectura && $ruta === '/') {
            return $controlador->servirPagina($request, $pagina, '', null, $dominio);
        }

        if ($lectura && preg_match('#^/([a-z0-9-]{1,40})$#', $ruta, $m)) {
            return $controlador->servirPagina($request, $pagina, '', $m[1], $dominio);
        }

        return $respuesta->noDisponible(404);
    }
}
