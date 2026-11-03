<?php

namespace App\Enums;

/**
 * Cómo se originó una contratación.
 */
enum OrigenContratacion: string
{
    use EnumConEtiqueta;

    case Openpay = 'openpay';
    // Histórico: contrataciones pagadas con la pasarela anterior.
    case Stripe = 'stripe';
    case Manual = 'manual';
    case FueraDeLinea = 'fuera_de_linea';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Openpay => 'Pago en línea (Openpay)',
            self::Stripe => 'Pago en línea (Stripe, histórico)',
            self::Manual => 'Activación manual',
            self::FueraDeLinea => 'Pago fuera de línea',
        };
    }
}
