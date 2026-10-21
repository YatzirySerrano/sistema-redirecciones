<?php

namespace App\Enums;

/**
 * Seguimiento administrativo de la preparación física (no verificable por el sistema).
 */
enum EstadoPreparacion: string
{
    use EnumConEtiqueta;

    case Pendiente = 'pendiente';
    case Listo = 'listo';
    case Entregado = 'entregado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Listo => 'Listo',
            self::Entregado => 'Entregado',
        };
    }
}
