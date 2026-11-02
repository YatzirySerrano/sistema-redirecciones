<?php

namespace App\Services\Pagos;

use App\Models\Pago;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Stripe Checkout (pago único, MXN). No recibe ni guarda datos de
 * tarjeta: el cliente paga en la página de Stripe.
 */
class PasarelaStripe implements PasarelaPago
{
    public function configurada(): bool
    {
        return (string) config('services.stripe.secret') !== '';
    }

    public function modoReal(): bool
    {
        return str_starts_with((string) config('services.stripe.secret'), 'sk_live_')
            || str_starts_with((string) config('services.stripe.secret'), 'rk_live_');
    }

    public function crearCheckout(Pago $pago, string $concepto, string $urlExito, string $urlCancelacion): array
    {
        if (! $this->configurada()) {
            throw new RuntimeException('Stripe no está configurado.');
        }

        $metadata = [
            'pago_id' => (string) $pago->id,
            'negocio_id' => (string) $pago->negocio_id,
            'servicio_id' => (string) $pago->servicio_id,
        ];

        $sesion = (new StripeClient((string) config('services.stripe.secret')))->checkout->sessions->create([
            'mode' => 'payment',
            'locale' => 'es',
            'client_reference_id' => (string) $pago->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($pago->moneda),
                    'unit_amount' => $pago->importe_centavos,
                    'product_data' => ['name' => $concepto],
                ],
            ]],
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'success_url' => $urlExito,
            'cancel_url' => $urlCancelacion,
        ], ['idempotency_key' => 'dasti-checkout-pago-'.$pago->id]);

        return ['id' => (string) $sesion->id, 'url' => (string) $sesion->url];
    }
}
