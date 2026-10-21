<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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

            if (app()->hasDebugModeEnabled() && $estado >= 500) {
                return $response;
            }

            if ($request->is('r/*') || ! in_array($estado, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($estado === 419) {
                return back()->with('status', 'La página expiró. Inténtalo de nuevo.');
            }

            return inertia('Error', ['estado' => $estado])
                ->toResponse($request)
                ->setStatusCode($estado);
        });
    })->create();
