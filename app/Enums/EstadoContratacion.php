<?php

namespace App\Enums;

/**
 * Estado de una contratación de servicio por un negocio.
 */
enum EstadoContratacion: string
{
    use EnumConEtiqueta;

    case Pendiente = 'pendiente';
    case Activa = 'activa';
    case Restringida = 'restringida';
    case Suspendida = 'suspendida';
    case Vencida = 'vencida';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de pago',
            self::Activa => 'Activa',
            self::Restringida => 'Restringida (en revisión)',
            self::Suspendida => 'Suspendida',
            self::Vencida => 'Vencida',
            self::Cancelada => 'Cancelada',
        };
    }
}
