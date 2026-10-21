<?php

namespace App\Enums;

/**
 * Categoría aproximada del dispositivo, deducida del User-Agent.
 */
enum CategoriaDispositivo: string
{
    use EnumConEtiqueta;

    case Movil = 'movil';
    case Tablet = 'tablet';
    case Escritorio = 'escritorio';
    case Bot = 'bot';
    case Desconocido = 'desconocido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Movil => 'Móvil',
            self::Tablet => 'Tableta',
            self::Escritorio => 'Escritorio',
            self::Bot => 'Bot / automatizado',
            self::Desconocido => 'Desconocido',
        };
    }
}
