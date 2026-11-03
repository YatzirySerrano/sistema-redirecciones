<?php

namespace App\Enums;

/**
 * Estado de publicación de una Página de enlaces.
 */
enum EstadoPagina: string
{
    use EnumConEtiqueta;

    case Borrador = 'borrador';
    case Publicada = 'publicado';
    case Despublicada = 'despublicado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Sin publicar',
            self::Publicada => 'Publicada',
            self::Despublicada => 'Despublicada',
        };
    }
}
