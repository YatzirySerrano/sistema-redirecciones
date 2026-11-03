<?php

namespace App\Http\Controllers\Pagos;

use App\Http\Controllers\Controller;
use App\Services\Pagos\ProcesadorNotificacionOpenpay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Notificaciones de Openpay (`POST /openpay/notificaciones`), fuera del
 * grupo «web»: sin sesión, cookies ni CSRF.
 *
 * Autenticación: HTTP Basic con el usuario y la contraseña configurados
 * al registrar el webhook en el panel de Openpay (OPENPAY_WEBHOOK_USER y
 * OPENPAY_WEBHOOK_PASSWORD). Opcionalmente, lista de IP permitidas.
 * Además, el contenido nunca se usa como fuente de verdad: el procesador
 * consulta el cargo a la API de Openpay.
 *
 * - 401: credenciales incorrectas.  - 503: webhook sin configurar.
 * - 500: error al procesar (Openpay reintentará).  - 200: procesado,
 *   duplicado o ignorado.
 */
class NotificacionOpenpayController extends Controller
{
    public function __invoke(Request $request, ProcesadorNotificacionOpenpay $procesador): JsonResponse
    {
        $usuario = (string) config('services.openpay.webhook_user');
        $contrasena = (string) config('services.openpay.webhook_password');

        if ($usuario === '' || $contrasena === '') {
            return response()->json(['error' => 'Notificaciones no configuradas.'], 503);
        }

        $ips = (array) config('services.openpay.webhook_ips', []);

        if ($ips !== [] && ! IpUtils::checkIp((string) $request->ip(), $ips)) {
            return response()->json(['error' => 'Origen no permitido.'], 403);
        }

        if (! hash_equals($usuario, (string) $request->getUser()) || ! hash_equals($contrasena, (string) $request->getPassword())) {
            return response()->json(['error' => 'No autorizado.'], 401, ['WWW-Authenticate' => 'Basic realm="openpay"']);
        }

        $datos = json_decode($request->getContent(), true);

        if (! is_array($datos)) {
            return response()->json(['error' => 'Contenido no válido.'], 400);
        }

        try {
            $resultado = $procesador->procesar($datos);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo procesar la notificación.'], 500);
        }

        return response()->json(['recibido' => true, 'resultado' => $resultado]);
    }
}
