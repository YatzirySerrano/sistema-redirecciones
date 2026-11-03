<?php

namespace App\Services\Paginas;

use RuntimeException;

/**
 * Otro usuario (u otra pestaña) guardó la página después de que se abrió el
 * editor: no se sobrescribe sin confirmación.
 */
class ConflictoVersion extends RuntimeException
{
    public function __construct(public readonly int $versionActual, public readonly ?string $guardadoPor, public readonly ?string $guardadoEn)
    {
        parent::__construct('La página se modificó en otra ventana o por otra persona.');
    }
}
