<?php

namespace App\Enums;

/**
 * Movimiento del saldo de visitas de una participación.
 */
enum TipoMovimiento: string
{
    use EnumConEtiqueta;

    case Visita = 'visita';
    case Ajuste = 'ajuste';
    case Anulacion = 'anulacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Visita => 'Visita',
            self::Ajuste => 'Ajuste',
            self::Anulacion => 'Anulación',
        };
    }
}
