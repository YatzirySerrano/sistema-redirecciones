<?php

namespace App\Enums;

/**
 * Rol del usuario dentro de la plataforma.
 */
enum RolUsuario: string
{
    use EnumConEtiqueta;

    case Admin = 'admin';
    case Cliente = 'cliente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador DASTI',
            self::Cliente => 'Cliente',
        };
    }
}
