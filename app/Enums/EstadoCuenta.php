<?php

namespace App\Enums;

/**
 * Estado de acceso compartido por usuarios y negocios.
 */
enum EstadoCuenta: string
{
    use EnumConEtiqueta;

    case Activo = 'activo';
    case Suspendido = 'suspendido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
        };
    }
}
