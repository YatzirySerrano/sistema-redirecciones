<?php

namespace App\Enums;

/**
 * Medio con el que se identifica un consumidor de Fidelidad.
 */
enum TipoCredencial: string
{
    use EnumConEtiqueta;

    case Nfc = 'nfc';
    case Qr = 'qr';
    case Digital = 'digital';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Nfc => 'Tarjeta NFC',
            self::Qr => 'Código QR',
            self::Digital => 'Enlace digital',
        };
    }
}
