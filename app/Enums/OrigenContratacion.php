<?php

namespace App\Enums;

/**
 * Cómo se originó una contratación.
 */
enum OrigenContratacion: string
{
    use EnumConEtiqueta;

    case Stripe = 'stripe';
    case Manual = 'manual';
    case FueraDeLinea = 'fuera_de_linea';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Stripe => 'Pago en línea (Stripe)',
            self::Manual => 'Activación manual',
            self::FueraDeLinea => 'Pago fuera de línea',
        };
    }
}
