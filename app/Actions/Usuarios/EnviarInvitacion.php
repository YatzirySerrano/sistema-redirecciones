<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Notifications\InvitacionAcceso;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Envía (o reenvía) el enlace para que el usuario defina su contraseña.
 */
class EnviarInvitacion
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function ejecutar(User $usuario, bool $registrar = true): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($usuario);

        $usuario->notify(new InvitacionAcceso($token));

        if ($registrar) {
            $this->auditoria->registrar(
                accion: 'usuario.invitacion_enviada',
                descripcion: "Envío de enlace de acceso a {$usuario->email}",
                recurso: $usuario,
                negocio: $usuario->negocio_id,
            );
        }
    }
}
