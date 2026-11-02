<?php

namespace App\Enums;

/**
 * Tipo (ámbito) de la cuenta: define el ALCANCE de sus permisos. El
 * personal de DASTI opera sobre todos los negocios; los clientes, solo sobre
 * el suyo. Lo que cada uno puede hacer lo define su rol dinámico
 * (App\Models\Rol).
 */
enum RolUsuario: string
{
    use EnumConEtiqueta;

    case Admin = 'admin';
    case Cliente = 'cliente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Personal DASTI',
            self::Cliente => 'Cliente',
        };
    }
}
