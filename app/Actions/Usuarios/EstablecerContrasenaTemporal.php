<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\SesionesUsuario;

/**
 * El administrador asigna una contraseña temporal; el usuario deberá
 * cambiarla en su siguiente inicio de sesión. La contraseña nunca se audita.
 */
class EstablecerContrasenaTemporal
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly SesionesUsuario $sesiones,
    ) {}

    public function ejecutar(User $usuario, string $password): User
    {
        $usuario->forceFill([
            'password' => $password,
            'debe_cambiar_password' => true,
        ])->save();

        // Quien tuviera una sesión abierta con la contraseña anterior la pierde.
        $this->sesiones->cerrarTodas($usuario);

        $this->auditoria->registrar(
            accion: 'usuario.contrasena_temporal',
            descripcion: "Asignación de contraseña temporal a {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
        );

        return $usuario;
    }
}
