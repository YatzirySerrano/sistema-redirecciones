<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\ArchivoPublico;
use App\Models\Paginas\AliasPagina;
use App\Models\Paginas\DominioPagina;
use App\Models\Paginas\EventoPagina;
use App\Models\Paginas\MensajePagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\Paginas\NuevoMensajePagina;
use App\Services\Apariencia\Color;
use App\Services\Paginas\CatalogoPagina;
use App\Services\Paginas\DocumentoPagina;
use App\Services\Paginas\FuentesPagina;
use App\Services\Paginas\ProteccionContacto;
use App\Services\Paginas\QrPagina;
use App\Services\Paginas\RegistroEventosPagina;
use App\Services\Paginas\RespuestaPublica;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas de enlaces públicas: /{prefijo}/{slug}[/{subpagina}] en el
 * dominio de DASTI y la raíz de los dominios personalizados (ver
 * EnrutarDominiosPagina). Sin sesión ni cookies. Sin marca de DASTI.
 *
 * - Solo se muestra el documento PUBLICADO, y solo si el negocio está
 *   activo y el servicio vigente.
 * - Los clics pasan por /ir/{elemento}, que registra el evento y redirige
 *   al destino ACTUAL publicado (nunca a una URL recibida en la petición).
 */
class PaginaPublicaController extends Controller
{
    public function __construct(
        private readonly DocumentoPagina $documento,
        private readonly RegistroEventosPagina $eventos,
        private readonly AccesoServicios $acceso,
        private readonly RespuestaPublica $respuesta,
    ) {}

    // ---- Rutas en el dominio de DASTI ----

    public function mostrar(Request $request, string $slug, ?string $subpagina = null): Response
    {
        $pagina = $this->resolver($slug);

        if (! $pagina instanceof PaginaEnlaces) {
            return $pagina ?? $this->respuesta->noDisponible(404);
        }

        return $this->servirPagina($request, $pagina, $pagina->rutaPublica(), $subpagina, null);
    }

    public function clic(Request $request, string $slug, string $bloque): Response
    {
        $pagina = $this->resolver($slug, "/ir/{$bloque}");

        if (! $pagina instanceof PaginaEnlaces) {
            return $pagina ?? $this->respuesta->noDisponible(404);
        }

        return $this->servirClic($request, $pagina, $pagina->rutaPublica(), $bloque);
    }

    public function contacto(Request $request, string $slug): Response
    {
        $pagina = PaginaEnlaces::query()->where('slug', strtolower($slug))->first();

        return $pagina === null
            ? response()->json(['message' => 'La página no está disponible.'], 404)
            : $this->servirContacto($request, $pagina);
    }

    public function qr(Request $request, string $slug): Response
    {
        $pagina = $this->resolver($slug, '/qr.svg');

        if (! $pagina instanceof PaginaEnlaces) {
            return $pagina ?? $this->respuesta->noDisponible(404);
        }

        return $this->servirQr($request, $pagina);
    }

    // ---- Compartido con los dominios personalizados ----

    public function servirPagina(Request $request, PaginaEnlaces $pagina, string $base, ?string $subpagina, ?DominioPagina $dominio): Response
    {
        if (($limite = $this->limitar($request, 'pagina', 240)) !== null) {
            return $limite;
        }

        if (($estado = $this->estado($pagina)) !== 200) {
            return $this->respuesta->noDisponible($estado);
        }

        /** @var array<string, mixed> $publicado */
        $publicado = $pagina->publicado;
        $slugSub = $subpagina !== null ? strtolower($subpagina) : 'inicio';
        $actual = DocumentoPagina::buscarPagina($publicado, $slugSub);

        if ($actual === null || ($subpagina !== null && $slugSub === 'inicio')) {
            return $this->respuesta->noDisponible(404);
        }

        $pagina->loadMissing('dominios');
        $principal = $pagina->dominioPrincipal();

        // Un dominio alterno redirige al principal (URL canónica única).
        if ($dominio !== null && $principal !== null && $principal->id !== $dominio->id) {
            return redirect()->away('https://'.$principal->host.($subpagina !== null ? '/'.$slugSub : '/'), 301);
        }

        $documento = $this->documento->paraPublico($publicado, $base, now(), $this->mediosExistentes($publicado, $pagina));
        $documento['paginas'] = array_map(
            fn (array $p) => $p['slug'] === $slugSub ? $p : [...$p, 'bloques' => []],
            $documento['paginas'],
        );

        $this->eventos->registrar($request, $pagina, EventoPagina::VISITA, null, $subpagina !== null ? $slugSub : null);

        $perfil = $documento['perfil'];
        $ajustes = $documento['ajustes'];
        $origen = $request->getSchemeAndHttpHost();
        $canonica = rtrim($pagina->urlCanonica(), '/').($subpagina !== null ? '/'.$slugSub : ($principal !== null ? '/' : ''));
        $nombre = $perfil['nombre'] !== '' ? $perfil['nombre'] : $pagina->negocio->nombre_comercial;
        $titulo = $ajustes['seo_titulo'] !== '' ? $ajustes['seo_titulo'] : $nombre;

        if ($subpagina !== null) {
            $titulo = $actual['titulo'].' · '.$titulo;
        }

        $descripcion = $actual['descripcion'] !== '' && $subpagina !== null
            ? $actual['descripcion']
            : ($ajustes['seo_descripcion'] !== '' ? $ajustes['seo_descripcion'] : mb_substr(str_replace(["\n", '**', '_'], [' ', '', ''], $perfil['descripcion']), 0, 160));
        $imagen = $ajustes['imagen_social'] ?? $perfil['portada'] ?? $perfil['logo'] ?? $perfil['foto'] ?? null;
        $tema = $documento['tema'];
        $indexar = (bool) $ajustes['indexar'];
        $nonce = $this->respuesta->nonce();
        Vite::useCspNonce($nonce);

        return response()->view('paginas.publica', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'canonica' => $canonica,
            'imagen' => $imagen !== null ? $origen.$imagen['url'] : null,
            'favicon' => $perfil['favicon']['url'] ?? $perfil['logo']['url'] ?? $perfil['foto']['url'] ?? null,
            'fondo' => $tema['colores']['fondo'],
            'fuentes' => FuentesPagina::url($tema['tipografia']),
            'indexar' => $indexar,
            'nonce' => $nonce,
            'enlaces' => $this->enlacesNoscript($documento, $slugSub),
            'nombre' => $nombre,
            'datos' => [
                'documento' => $documento,
                'pagina' => $slugSub,
                'base' => $base,
                'contacto' => [
                    'accion' => $base.'/contacto',
                    'marca' => app(ProteccionContacto::class)->marca($pagina),
                ],
                'qr' => $base.'/qr.svg',
                'url_publica' => $canonica,
            ],
        ])->withHeaders($this->respuesta->cabeceras($indexar));
    }

    /**
     * GET: registra el clic y redirige al destino actual publicado.
     * POST: solo registra (tel: y mailto:, que se abren directamente).
     */
    public function servirClic(Request $request, PaginaEnlaces $pagina, string $base, string $bloque): Response
    {
        if (($limite = $this->limitar($request, 'clic', 120)) !== null) {
            return $limite;
        }

        if (! preg_match('/^[0-9a-f-]{36}$/', $bloque) || $this->estado($pagina) !== 200) {
            return $this->respuesta->noDisponible(404);
        }

        /** @var array<string, mixed> $publicado */
        $publicado = $pagina->publicado;
        $encontrado = $this->documento->buscarClicable($publicado, $bloque, now());
        $slugs = DocumentoPagina::slugs($publicado);
        $destino = $encontrado !== null ? $this->documento->destino($encontrado['bloque'], $base, $slugs) : null;

        if ($destino === null) {
            return $this->respuesta->noDisponible(404);
        }

        $this->eventos->registrar($request, $pagina, EventoPagina::CLIC, $bloque, $encontrado['pagina'] !== 'inicio' ? $encontrado['pagina'] : null);

        if ($request->isMethod('POST')) {
            return response()->noContent()->withHeaders(['Cache-Control' => 'no-store']);
        }

        return redirect()->away($destino === '' ? '/' : $destino, 302, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function servirContacto(Request $request, PaginaEnlaces $pagina): JsonResponse
    {
        $cabeceras = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        $config = (array) config('dasti.paginas.contacto');
        $llaveIp = 'pagina-contacto-ip:'.hash('sha256', (string) $request->ip());
        $llavePagina = 'pagina-contacto-pagina:'.$pagina->id;

        if (RateLimiter::tooManyAttempts($llaveIp, (int) ($config['por_ip_cada_10_min'] ?? 5))
            || RateLimiter::tooManyAttempts($llavePagina, (int) ($config['por_pagina_por_hora'] ?? 60))) {
            return response()->json(['message' => 'Recibimos varios mensajes seguidos. Inténtalo más tarde.'], 429, $cabeceras);
        }

        if ($this->estado($pagina) !== 200) {
            return response()->json(['message' => 'La página no está disponible.'], 404, $cabeceras);
        }

        /** @var array<string, mixed> $publicado */
        $publicado = $pagina->publicado;
        $bloqueId = (string) $request->input('bloque', '');
        $bloque = $this->documento->buscarClicable($publicado, $bloqueId, now());

        if ($bloque === null || $bloque['bloque']['tipo'] !== 'contacto') {
            return response()->json(['message' => 'El formulario ya no está disponible. Recarga la página.'], 404, $cabeceras);
        }

        // Campo trampa: los robots lo llenan. Se responde como si se hubiera
        // enviado, sin guardar nada.
        if (trim((string) $request->input('sitio_web', '')) !== '') {
            RateLimiter::hit($llaveIp, 600);

            return response()->json(['ok' => true, 'mensaje' => $bloque['bloque']['exito']], 200, $cabeceras);
        }

        if (($error = app(ProteccionContacto::class)->errorMarca($request->input('marca'), $pagina)) !== null) {
            return response()->json(['message' => $error, 'errors' => ['marca' => [$error]]], 422, $cabeceras);
        }

        $validador = validator($request->all(), [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+\-().]{7,30}$/'],
            'mensaje' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['nombre' => 'nombre', 'email' => 'correo electrónico', 'telefono' => 'teléfono', 'mensaje' => 'mensaje']);

        if ($validador->fails()) {
            return response()->json(['message' => (string) $validador->errors()->first(), 'errors' => $validador->errors()], 422, $cabeceras);
        }

        $datos = $validador->validated();
        $limpiar = fn (string $v, int $max, bool $multi = false) => $this->documento->texto($v, $max, $multi);
        $mensaje = $limpiar((string) $datos['mensaje'], 2000, true);

        if (app(ProteccionContacto::class)->enlacesEn($mensaje) > (int) ($config['max_enlaces'] ?? 2)) {
            return response()->json(['message' => 'Tu mensaje tiene demasiados enlaces.', 'errors' => ['mensaje' => ['Tu mensaje tiene demasiados enlaces.']]], 422, $cabeceras);
        }

        RateLimiter::hit($llaveIp, 600);
        RateLimiter::hit($llavePagina, 3600);

        MensajePagina::query()->create([
            'pagina_id' => $pagina->id,
            'negocio_id' => $pagina->negocio_id,
            'bloque_id' => $bloqueId,
            'nombre' => $limpiar((string) $datos['nombre'], 100),
            'email' => strtolower(trim((string) $datos['email'])),
            'telefono' => isset($datos['telefono']) ? $limpiar((string) $datos['telefono'], 30) : null,
            'mensaje' => $mensaje,
        ]);

        $this->eventos->registrar($request, $pagina, EventoPagina::CONTACTO, $bloqueId, $bloque['pagina'] !== 'inicio' ? $bloque['pagina'] : null);

        if (($publicado['ajustes']['notificar_mensajes'] ?? false) === true) {
            $this->avisarNegocio($pagina);
        }

        return response()->json(['ok' => true, 'mensaje' => $bloque['bloque']['exito']], 201, $cabeceras);
    }

    public function servirQr(Request $request, PaginaEnlaces $pagina): Response
    {
        if (($limite = $this->limitar($request, 'qr', 60)) !== null) {
            return $limite;
        }

        if ($this->estado($pagina) !== 200) {
            return $this->respuesta->noDisponible(404);
        }

        /** @var array<string, mixed> $publicado */
        $publicado = $pagina->publicado;
        $colores = $publicado['tema']['colores'];
        [$color, $fondo] = Color::contraste($colores['titulos'], $colores['tarjeta_fondo']) >= 7
            ? [$colores['titulos'], $colores['tarjeta_fondo']]
            : ['#111111', '#ffffff'];
        $pagina->loadMissing('dominios');
        $qr = app(QrPagina::class)->generar($pagina->urlCanonica(), 'svg', 512, $color, $fondo);

        return response($qr['contenido'], 200, [
            'Content-Type' => $qr['mime'],
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }

    /**
     * 200 si se puede mostrar; 404 sin publicar; 410 servicio o negocio
     * inactivo (la configuración se conserva).
     */
    public function estado(PaginaEnlaces $pagina): int
    {
        $pagina->loadMissing('negocio');

        return match (true) {
            ! $pagina->negocio->estaActivo() => 410,
            ! $this->acceso->tiene($pagina->negocio, Servicio::PAGINA_ENLACES) => 410,
            ! $pagina->estaPublicada() => 404,
            default => 200,
        };
    }

    /**
     * Página por slug; un slug anterior (alias) redirige con 301 a la
     * dirección actual conservando el resto de la ruta.
     */
    private function resolver(string $slug, string $resto = ''): PaginaEnlaces|RedirectResponse|null
    {
        $slug = strtolower($slug);

        if (! preg_match('/^[a-z0-9-]{1,40}$/', $slug)) {
            return null;
        }

        $pagina = PaginaEnlaces::query()->with('negocio')->where('slug', $slug)->first();

        if ($pagina !== null) {
            return $pagina;
        }

        $alias = AliasPagina::query()->with('pagina')->where('slug', $slug)->first();

        if ($alias === null) {
            return null;
        }

        $ruta = request()->path();
        $prefijo = trim((string) config('dasti.paginas.prefijo', 'l'), '/').'/'.$slug;
        $resto = substr($ruta, strlen($prefijo)) ?: $resto;

        return redirect()->to($alias->pagina->rutaPublica().$resto, 301, ['Cache-Control' => 'public, max-age=86400']);
    }

    private function limitar(Request $request, string $tipo, int $porMinuto): ?Response
    {
        $llave = 'pagina-publica-'.$tipo.':'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($llave, $porMinuto)) {
            return $this->respuesta->noDisponible(429)->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($llave)]);
        }

        RateLimiter::hit($llave, 60);

        return null;
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return list<string>
     */
    private function mediosExistentes(array $documento, PaginaEnlaces $pagina): array
    {
        $uuids = [];

        array_walk_recursive($documento, function (mixed $v, int|string $k) use (&$uuids) {
            if ($k === 'id' && is_string($v) && strlen($v) === 36) {
                $uuids[] = $v;
            }
        });

        return array_values(ArchivoPublico::query()->where('negocio_id', $pagina->negocio_id)
            ->where('modulo', ArchivoPublico::MODULO_PAGINAS)
            ->whereIn('uuid', array_slice(array_unique($uuids), 0, 3000))
            ->pluck('uuid')->map(fn (mixed $u) => (string) $u)->all());
    }

    /**
     * Respaldo sin JavaScript: lista semántica de los enlaces visibles.
     *
     * @param  array<string, mixed>  $documento
     * @return list<array{titulo: string, href: string}>
     */
    private function enlacesNoscript(array $documento, string $slug): array
    {
        $actual = DocumentoPagina::buscarPagina($documento, $slug);
        $enlaces = [];

        foreach (DocumentoPagina::recorrer(['paginas' => $actual !== null ? [$actual] : []]) as ['bloque' => $b]) {
            $href = $b['tipo'] === 'telefono' || $b['tipo'] === 'correo' ? ($b['rastreo'] ?? null) : ($b['href'] ?? null);

            if (is_string($href) && $href !== '') {
                $enlaces[] = ['titulo' => DocumentoPagina::nombre($b), 'href' => $href];
            }

            if ($b['tipo'] === 'redes') {
                foreach ($b['elementos'] as $e) {
                    $enlaces[] = ['titulo' => CatalogoPagina::NOMBRES_REDES[$e['red']] ?? 'Red social', 'href' => (string) $e['href']];
                }
            }
        }

        return $enlaces;
    }

    private function avisarNegocio(PaginaEnlaces $pagina): void
    {
        $destinatarios = User::query()
            ->where('negocio_id', $pagina->negocio_id)
            ->where('estado', 'activo')
            ->get()
            ->filter(fn (User $u) => $u->tienePermiso('paginas.mensajes'));

        foreach ($destinatarios as $usuario) {
            $usuario->notify(new NuevoMensajePagina);
        }
    }
}
