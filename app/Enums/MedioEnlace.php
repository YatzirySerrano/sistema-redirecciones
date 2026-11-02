<?php

namespace App\Enums;

/**
 * Medio físico de un enlace en un producto con destinos independientes.
 */
enum MedioEnlace: string
{
    use EnumConEtiqueta;

    case Nfc = 'nfc';
    case Qr = 'qr';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Nfc => 'NFC (frente)',
            self::Qr => 'QR (reverso)',
        };
    }
}
