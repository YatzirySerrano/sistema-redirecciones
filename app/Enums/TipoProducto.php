<?php

namespace App\Enums;

/**
 * Tipo de producto físico. El combinado comparte un mismo enlace entre QR y NFC.
 */
enum TipoProducto: string
{
    use EnumConEtiqueta;

    case Qr = 'qr';
    case Nfc = 'nfc';
    case Combinado = 'combinado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Qr => 'QR',
            self::Nfc => 'NFC',
            self::Combinado => 'QR + NFC',
        };
    }
}
