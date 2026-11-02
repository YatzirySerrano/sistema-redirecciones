<?php

namespace App\Enums;

/**
 * Estado de una recompensa de Fidelidad.
 */
enum EstadoRecompensa: string
{
    use EnumConEtiqueta;

    case Disponible = 'disponible';
    case Canjeada = 'canjeada';
    case Vencida = 'vencida';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Canjeada => 'Canjeada',
            self::Vencida => 'Vencida',
            self::Anulada => 'Anulada',
        };
    }
}
