<?php

namespace App\Enums;

/**
 * Resultado del envío de un enlace de acceso.
 */
enum ResultadoInvitacion: string
{
    case Enviada = 'enviada';
    case Fallida = 'fallida';
    // El usuario o su negocio están suspendidos: no se envía.
    case NoPermitida = 'no_permitida';
}
