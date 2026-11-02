<?php

namespace App\Enums;

/**
 * Cómo se relacionan los medios de un producto QR + NFC con sus destinos.
 */
enum ModalidadDestino: string
{
    use EnumConEtiqueta;

    case Compartido = 'compartido';
    case Independiente = 'independiente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Compartido => 'Destino compartido',
            self::Independiente => 'Destinos independientes',
        };
    }
}
