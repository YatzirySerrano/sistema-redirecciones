<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
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

        // El cuerpo supera post_max_size: PHP descarta todos los campos. Se
        // responde con un mensaje comprensible en lugar de la página 413.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $mensaje = 'El archivo es demasiado grande. Elige una imagen PNG, JPG o WEBP de hasta 1 MB.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $mensaje], 413);
            }

            // ValidatePostSize se ejecuta antes de iniciar la sesión, así que
            // no es posible volver con errores flash: se muestra la página de
            // error del panel con el motivo.
            return inertia('Error', ['estado' => 413, 'mensaje' => $mensaje])
                ->toResponse($request)
                ->setStatusCode(413);
        });

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

            // Respuestas JSON (fetch): los mensajes genéricos del framework
            // en inglés se sustituyen por textos comprensibles en español.
            if ($response instanceof JsonResponse && $estado >= 400) {
                $datos = $response->getData(true);
                $original = is_array($datos) ? (string) ($datos['message'] ?? '') : '';
                $genericos = ['', 'Unauthenticated.', 'This action is unauthorized.', 'Forbidden', 'Not Found', 'Server Error',
                    'Too Many Attempts.', 'CSRF token mismatch.', 'Service Unavailable', 'Method Not Allowed', 'Page Expired',
                    'Unauthorized', 'Content Too Large', 'Payload Too Large'];

                if (is_array($datos) && (in_array($original, $genericos, true) || $estado >= 500)) {
                    $datos['message'] = match (true) {
                        $estado === 401 => 'Tu sesión expiró. Inicia sesión de nuevo.',
                        $estado === 403 => 'No tienes permiso para realizar esta acción.',
                        $estado === 404 => 'El recurso solicitado no existe o ya no está disponible.',
                        $estado === 405 => 'La operación solicitada no está permitida.',
                        $estado === 413 => 'El archivo es demasiado grande.',
                        $estado === 419 => 'La sesión expiró. Recarga la página e inténtalo nuevamente.',
                        $estado === 429 => 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo nuevamente.',
                        $estado === 503 => 'Estamos realizando mejoras. Inténtalo nuevamente en unos minutos.',
                        $estado >= 500 => 'Ocurrió un problema al procesar la solicitud. Inténtalo nuevamente.',
                        default => 'No pudimos completar la solicitud. Inténtalo nuevamente.',
                    };
                    $response->setData($datos);
                }

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
