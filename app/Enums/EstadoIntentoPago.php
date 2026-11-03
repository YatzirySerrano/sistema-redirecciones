<?php

namespace App\Enums;

/**
 * Estado de un intento de cobro (cargo creado en el proveedor de pagos).
 */
enum EstadoIntentoPago: string
{
    use EnumConEtiqueta;

    case Creado = 'creado';
    case Pendiente = 'pendiente';
    case Completado = 'completado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';
    case Expirado = 'expirado';
    case Reembolsado = 'reembolsado';
    case ReembolsoParcial = 'reembolso_parcial';
    case EnDisputa = 'en_disputa';
    case ContracargoPerdido = 'contracargo_perdido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Pendiente => 'Esperando pago',
            self::Completado => 'Aprobado',
            self::Fallido => 'Rechazado',
            self::Cancelado => 'Cancelado',
            self::Expirado => 'Caducado',
            self::Reembolsado => 'Reembolsado',
            self::ReembolsoParcial => 'Reembolso parcial',
            self::EnDisputa => 'Contracargo en revisión',
            self::ContracargoPerdido => 'Contracargo perdido',
        };
    }

    /**
     * ¿Puede todavía convertirse en un pago aprobado?
     */
    public function abierto(): bool
    {
        return $this === self::Creado || $this === self::Pendiente;
    }
}
