<?php

namespace App\Http\Controllers\Pagos;

use App\Http\Controllers\Controller;
use App\Services\Pagos\ProcesadorWebhookStripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Webhook de Stripe (`POST /stripe/webhook`), fuera del grupo «web»: sin
 * sesión, cookies ni CSRF. La firma (`Stripe-Signature`) se verifica con el
 * secreto del endpoint antes de leer nada del contenido.
 *
 * - 400: firma o contenido inválidos (Stripe no debe reintentar).
 * - 500: error al procesar (Stripe reintentará; el evento no queda marcado).
 * - 200: procesado, duplicado o ignorado.
 */
class WebhookStripeController extends Controller
{
    public function __invoke(Request $request, ProcesadorWebhookStripe $procesador): JsonResponse
    {
        $secreto = (string) config('services.stripe.webhook_secret');

        if ($secreto === '') {
            return response()->json(['error' => 'Webhook no configurado.'], 503);
        }

        try {
            $evento = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secreto,
                (int) config('services.stripe.tolerancia_webhook', 300),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['error' => 'Firma inválida.'], 400);
        }

        try {
            $resultado = $procesador->procesar($evento);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo procesar el evento.'], 500);
        }

        return response()->json(['recibido' => true, 'resultado' => $resultado]);
    }
}
