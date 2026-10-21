<?php

namespace App\Enums;

/**
 * Estado operativo de un producto y de su enlace permanente.
 */
enum EstadoProducto: string
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
