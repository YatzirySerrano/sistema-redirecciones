<?php

namespace App\Enums;

/**
 * Quién originó un cambio de destino.
 */
enum OrigenCambio: string
{
    use EnumConEtiqueta;

    case Admin = 'admin';
    case Cliente = 'cliente';
    case Sistema = 'sistema';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cliente => 'Cliente',
            self::Sistema => 'Sistema',
        };
    }
}
