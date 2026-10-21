<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Enlaces permanentes: sin el grupo "web" (sin sesión ni cookies).
            Route::middleware([])->group(base_path('routes/redirecciones.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Detrás de Nginx en el mismo servidor: confiar en el proxy local para
        // obtener la IP y el esquema reales (HTTPS).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Errores HTTP en el panel: página Inertia en español en lugar de la
        // genérica, salvo en modo depuración.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $estado = $response->getStatusCode();

            // Enlaces permanentes: siempre la página neutra, sin datos del
            // negocio (también al superar el límite de solicitudes).
            if ($request->is('r/*') || $request->is('r')) {
                if (! in_array($estado, [404, 410, 429], true)) {
                    return $response;
                }

                return response()
                    ->view('publico.no-disponible', ['estado' => $estado], $estado)
                    ->withHeaders(array_filter([
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'X-Robots-Tag' => 'noindex, nofollow',
                        'Retry-After' => $response->headers->get('Retry-After'),
                    ]));
            }

            if (app()->hasDebugModeEnabled() && $estado >= 500) {
                return $response;
            }

            // Peticiones fetch (vista previa del QR, comprobación de destinos):
            // se conserva la respuesta JSON para que la interfaz muestre el motivo.
            if ($request->expectsJson() || ! in_array($estado, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($estado === 419) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'La sesión expiró. Vuelve a intentarlo.']);

                return back();
            }

            // Mensajes explícitos de las políticas (p. ej. «producto
            // suspendido»); los genéricos del framework no se muestran.
            $mensaje = $exception instanceof HttpExceptionInterface || $exception instanceof AuthorizationException
                ? $exception->getMessage()
                : '';
            $mensaje = $estado === 403 && $mensaje !== '' && $mensaje !== 'This action is unauthorized.' ? $mensaje : null;

            return inertia('Error', ['estado' => $estado, 'mensaje' => $mensaje])
                ->toResponse($request)
                ->setStatusCode($estado);
        });
    })->create();
