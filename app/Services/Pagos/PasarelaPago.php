<?php

namespace App\Services\Pagos;

use App\Models\Pago;

/**
 * Pasarela de pago en línea. Hoy: Stripe Checkout. La interfaz permite
 * simularla en pruebas y añadir otra pasarela sin tocar el dominio.
 */
interface PasarelaPago
{
    public function configurada(): bool;

    /**
     * ¿Opera en modo real (cargos reales)?
     */
    public function modoReal(): bool;

    /**
     * Crea una sesión de pago para el pago ya registrado (importe y moneda
     * del servidor) y devuelve su identificador y la URL de pago.
     *
     * @return array{id: string, url: string}
     */
    public function crearCheckout(Pago $pago, string $concepto, string $urlExito, string $urlCancelacion): array;
}
