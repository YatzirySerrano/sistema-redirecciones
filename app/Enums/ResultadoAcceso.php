<?php

namespace App\Enums;

/**
 * Resultado de una solicitud a un enlace permanente.
 */
enum ResultadoAcceso: string
{
    use EnumConEtiqueta;

    case Redirigido = 'redirigido';
    case Suspendido = 'suspendido';
    case NegocioSuspendido = 'negocio_suspendido';
    case SinDestino = 'sin_destino';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Redirigido => 'Redirigido',
            self::Suspendido => 'Producto suspendido',
            self::NegocioSuspendido => 'Negocio suspendido',
            self::SinDestino => 'Sin destino',
        };
    }
}
