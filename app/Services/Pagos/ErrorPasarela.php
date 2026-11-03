<?php

namespace App\Services\Pagos;

use RuntimeException;

/**
 * Fallo al comunicarse con el proveedor de pagos. `$codigo` es el código
 * del proveedor (si lo devolvió); el mensaje nunca incluye credenciales.
 */
class ErrorPasarela extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $codigo = null, public readonly ?int $estadoHttp = null)
    {
        parent::__construct($mensaje);
    }

    /**
     * El proveedor respondió que el recurso no existe.
     */
    public function noEncontrado(): bool
    {
        return $this->estadoHttp === 404;
    }
}
