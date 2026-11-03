<?php

namespace App\Services\Pagos;

use App\Models\IntentoPago;
use App\Models\Pago;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Openpay (México) con el formulario de pago alojado por Openpay
 * («Redireccionamiento», documentación: documents.openpay.mx/docs/op-form-charge).
 *
 * Se crea un cargo a nivel comercio con `confirm: false` y `redirect_url`;
 * Openpay responde con `payment_method.url`, a donde se envía al cliente
 * para capturar su tarjeta. Nuestro servidor NUNCA recibe datos de tarjeta.
 *
 * Autenticación: HTTP Basic con la llave privada (sin contraseña). La llave
 * pública no se usa en esta modalidad (sería necesaria solo para
 * tokenizar tarjetas en el navegador con Openpay.js).
 *
 * Ambientes: sandbox-api.openpay.mx (pruebas) y api.openpay.mx
 * (producción), según OPENPAY_PRODUCTION.
 */
class PasarelaOpenpay implements PasarelaPago
{
    public function clave(): string
    {
        return Pago::PROVEEDOR_OPENPAY;
    }

    public function configurada(): bool
    {
        return $this->comercio() !== '' && $this->llavePrivada() !== '';
    }

    public function modoReal(): bool
    {
        return (bool) config('services.openpay.production', false);
    }

    public function crearCargo(Pago $pago, IntentoPago $intento, array $cliente, string $urlRetorno): CargoExterno
    {
        $cuerpo = [
            'method' => 'card',
            // Importe exacto con dos decimales a partir de centavos.
            'amount' => (float) number_format($intento->importe_centavos / 100, 2, '.', ''),
            'currency' => $intento->moneda,
            'description' => mb_substr((string) $pago->concepto, 0, 250),
            'order_id' => $intento->orden_externa,
            'confirm' => false,
            'send_email' => false,
            'redirect_url' => $urlRetorno,
            'customer' => array_filter([
                'name' => mb_substr($cliente['nombre'], 0, 100),
                'last_name' => mb_substr($cliente['apellidos'], 0, 100),
                'email' => $cliente['email'],
                'phone_number' => $cliente['telefono'],
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        $cargo = CargoExterno::desdeOpenpay($this->json(fn () => $this->cliente()->post($this->url('charges'), $cuerpo)));

        if ($cargo->urlPago === null || ! str_starts_with($cargo->urlPago, 'https://')) {
            throw new ErrorPasarela('Openpay no devolvió la dirección del formulario de pago.');
        }

        return $cargo;
    }

    public function consultarCargo(string $transaccionId): CargoExterno
    {
        $this->exigirIdentificador($transaccionId);

        return CargoExterno::desdeOpenpay($this->json(fn () => $this->cliente()->get($this->url('charges/'.$transaccionId))));
    }

    public function buscarPorOrden(string $ordenExterna): ?CargoExterno
    {
        $lista = $this->json(fn () => $this->cliente()->get($this->url('charges'), ['order_id' => $ordenExterna, 'limit' => 5]));

        foreach ($lista as $cargo) {
            if (is_array($cargo) && ($cargo['order_id'] ?? null) === $ordenExterna) {
                return CargoExterno::desdeOpenpay($cargo);
            }
        }

        return null;
    }

    public function reembolsar(string $transaccionId, ?int $centavos, string $descripcion): CargoExterno
    {
        $this->exigirIdentificador($transaccionId);

        $cuerpo = array_filter([
            'description' => mb_substr($descripcion, 0, 250),
            'amount' => $centavos === null ? null : (float) number_format($centavos / 100, 2, '.', ''),
        ], fn ($v) => $v !== null);

        return CargoExterno::desdeOpenpay($this->json(fn () => $this->cliente()->post($this->url('charges/'.$transaccionId.'/refund'), $cuerpo)));
    }

    private function cliente(): PendingRequest
    {
        if (! $this->configurada()) {
            throw new ErrorPasarela('Openpay no está configurado.');
        }

        return Http::withBasicAuth($this->llavePrivada(), '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.openpay.timeout', 20))
            ->connectTimeout(10);
    }

    private function url(string $ruta): string
    {
        $base = $this->modoReal() ? 'https://api.openpay.mx' : 'https://sandbox-api.openpay.mx';

        return $base.'/v1/'.rawurlencode($this->comercio()).'/'.$ruta;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function json(\Closure $peticion): array
    {
        try {
            $respuesta = $peticion();
        } catch (ConnectionException) {
            throw new ErrorPasarela('No fue posible conectar con Openpay.');
        }

        $datos = $respuesta->json();

        if ($respuesta->failed()) {
            $codigo = is_array($datos) && isset($datos['error_code']) ? (string) $datos['error_code'] : null;
            $descripcion = is_array($datos) && isset($datos['description']) ? mb_substr((string) $datos['description'], 0, 200) : 'sin detalle';

            throw new ErrorPasarela("Openpay respondió {$respuesta->status()}: {$descripcion}", $codigo, $respuesta->status());
        }

        if (! is_array($datos)) {
            throw new ErrorPasarela('Openpay devolvió una respuesta no válida.');
        }

        return $datos;
    }

    private function exigirIdentificador(string $id): void
    {
        if (! preg_match('/^[a-z0-9]{1,64}$/i', $id)) {
            throw new ErrorPasarela('Identificador de transacción no válido.');
        }
    }

    private function comercio(): string
    {
        return (string) config('services.openpay.merchant_id');
    }

    private function llavePrivada(): string
    {
        return (string) config('services.openpay.private_key');
    }
}
