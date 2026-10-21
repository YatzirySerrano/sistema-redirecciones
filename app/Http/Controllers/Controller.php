<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Mensaje tras crear un acceso, según el método elegido y el resultado
     * real del envío del enlace (ver EnviarInvitacion).
     */
    protected function notificarAcceso(string $prefijo, User $usuario, bool $porInvitacion): void
    {
        $usuario->refresh();

        if (! $porInvitacion) {
            $this->notificar("{$prefijo} Comparte la contraseña temporal por un canal seguro.");

            return;
        }

        match ($usuario->estadoInvitacion()) {
            'pendiente' => $this->notificar("{$prefijo} Se envió un enlace de acceso a {$usuario->email}."),
            'fallida' => $this->notificar(
                "{$prefijo} El correo a {$usuario->email} no pudo enviarse: revisa la configuración de correo y usa «Enviar enlace de acceso».",
                'warning',
            ),
            default => $this->notificar(
                "{$prefijo} No se envió el enlace porque el acceso o su negocio están suspendidos; envíalo al reactivarlos.",
                'warning',
            ),
        };
    }

    /**
     * Notificación discreta (toast) para la siguiente vista.
     */
    protected function notificar(string $mensaje, string $tipo = 'success'): void
    {
        Inertia::flash('toast', ['type' => $tipo, 'message' => $mensaje]);
    }
}
