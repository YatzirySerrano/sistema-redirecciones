<?php

namespace App\Enums;

/**
 * Estado de un pago. Los datos de tarjeta nunca se guardan.
 */
enum EstadoPago: string
{
    use EnumConEtiqueta;

    case Pendiente = 'pendiente';
    case Pagado = 'pagado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';
    case Expirado = 'expirado';
    case Reembolsado = 'reembolsado';
    case ReembolsoParcial = 'reembolso_parcial';
    case EnDisputa = 'en_disputa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagado => 'Pagado',
            self::Fallido => 'Rechazado',
            self::Cancelado => 'Cancelado',
            self::Expirado => 'Caducado',
            self::Reembolsado => 'Reembolsado',
            self::ReembolsoParcial => 'Reembolso parcial',
            self::EnDisputa => 'En disputa',
        };
    }
}
