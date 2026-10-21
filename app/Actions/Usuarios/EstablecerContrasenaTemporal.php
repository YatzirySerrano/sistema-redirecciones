<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * El administrador asigna una contraseña temporal; el usuario deberá
 * cambiarla en su siguiente inicio de sesión. La contraseña nunca se audita.
 */
class EstablecerContrasenaTemporal
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function ejecutar(User $usuario, string $password): User
    {
        $usuario->forceFill([
            'password' => $password,
            'debe_cambiar_password' => true,
            'remember_token' => null,
        ])->save();

        $this->auditoria->registrar(
            accion: 'usuario.contrasena_temporal',
            descripcion: "Asignación de contraseña temporal a {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
        );

        return $usuario;
    }
}
