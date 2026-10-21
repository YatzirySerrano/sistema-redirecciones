<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\SesionesUsuario;

/**
 * El usuario define su contraseña desde el enlace de invitación. Al cambiar
 * el hash de la contraseña, el enlace deja de ser válido (uso único).
 */
class AceptarInvitacion
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly SesionesUsuario $sesiones,
    ) {}

    public function ejecutar(User $usuario, string $password): User
    {
        $usuario->forceFill([
            'password' => $password,
            'debe_cambiar_password' => false,
            'invitacion_aceptada_en' => now(),
            'invitacion_fallida_en' => null,
        ])->save();

        $this->sesiones->cerrarTodas($usuario);

        $this->auditoria->registrar(
            accion: 'usuario.invitacion_aceptada',
            descripcion: "{$usuario->email} aceptó su invitación y definió su contraseña",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            actor: $usuario,
        );

        return $usuario;
    }
}
