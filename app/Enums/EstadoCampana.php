<?php

namespace App\Enums;

/**
 * Estado de una campaña de Fidelidad.
 */
enum EstadoCampana: string
{
    use EnumConEtiqueta;

    case Activa = 'activa';
    case Pausada = 'pausada';
    case Finalizada = 'finalizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Pausada => 'Pausada',
            self::Finalizada => 'Finalizada',
        };
    }
}
