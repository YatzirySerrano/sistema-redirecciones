<?php

namespace App\Services\Pagos;

/**
 * Cargo tal como lo reporta el proveedor (normalizado). Importes en
 * centavos; estado en minúsculas (Openpay: charge_pending, in_progress,
 * completed, failed, cancelled, refunded, chargeback_pending,
 * chargeback_accepted, chargeback_adjustment).
 */
final readonly class CargoExterno
{
    public function __construct(
        public string $id,
        public string $estado,
        public int $importeCentavos,
        public string $moneda,
        public ?string $ordenId,
        public ?string $urlPago = null,
        public int $reembolsadoCentavos = 0,
        public ?string $codigoError = null,
        public ?string $mensajeError = null,
        public ?string $autorizacion = null,
        public ?string $marcaTarjeta = null,
        public ?string $tipoTarjeta = null,
        public ?string $terminacionTarjeta = null,
    ) {}

    /**
     * Construye el cargo a partir de un objeto transacción de Openpay.
     *
     * @param  array<int|string, mixed>  $datos  Objeto JSON decodificado.
     */
    public static function desdeOpenpay(array $datos): self
    {
        $tarjeta = is_array($datos['card'] ?? null) ? $datos['card'] : [];
        $reembolso = is_array($datos['refund'] ?? null) ? $datos['refund'] : null;
        $numero = preg_replace('/\D/', '', (string) ($tarjeta['card_number'] ?? ''));
        $estado = strtolower((string) ($datos['status'] ?? ''));

        $reembolsado = 0;

        if ($reembolso !== null && strtolower((string) ($reembolso['status'] ?? 'completed')) === 'completed') {
            $reembolsado = self::centavos($reembolso['amount'] ?? 0);
        }

        // Reembolso total reportado solo por el estado del cargo.
        if ($estado === 'refunded' && $reembolsado === 0) {
            $reembolsado = self::centavos($datos['amount'] ?? 0);
        }

        $metodo = is_array($datos['payment_method'] ?? null) ? $datos['payment_method'] : [];

        return new self(
            id: (string) ($datos['id'] ?? ''),
            estado: $estado,
            importeCentavos: self::centavos($datos['amount'] ?? 0),
            moneda: strtoupper((string) ($datos['currency'] ?? '')),
            ordenId: isset($datos['order_id']) ? (string) $datos['order_id'] : null,
            urlPago: isset($metodo['url']) ? (string) $metodo['url'] : null,
            reembolsadoCentavos: $reembolsado,
            codigoError: isset($datos['error_code']) ? (string) $datos['error_code'] : null,
            mensajeError: isset($datos['error_message']) ? mb_substr((string) $datos['error_message'], 0, 255) : null,
            autorizacion: isset($datos['authorization']) ? (string) $datos['authorization'] : null,
            marcaTarjeta: isset($tarjeta['brand']) ? mb_substr((string) $tarjeta['brand'], 0, 30) : null,
            tipoTarjeta: isset($tarjeta['type']) ? mb_substr((string) $tarjeta['type'], 0, 20) : null,
            terminacionTarjeta: $numero !== null && strlen($numero) >= 4 ? substr($numero, -4) : null,
        );
    }

    /**
     * Convierte un importe decimal del proveedor a centavos sin errores de
     * coma flotante (100.75 → 10075).
     */
    public static function centavos(mixed $importe): int
    {
        $texto = is_string($importe) ? $importe : number_format((float) $importe, 2, '.', '');

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?/', $texto, $m)) {
            return 0;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
