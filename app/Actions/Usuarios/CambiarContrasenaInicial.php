<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * El propio usuario reemplaza su contraseña temporal.
 */
class CambiarContrasenaInicial
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function ejecutar(User $usuario, string $password): User
    {
        $usuario->forceFill([
            'password' => $password,
            'debe_cambiar_password' => false,
        ])->save();

        $this->auditoria->registrar(
            accion: 'usuario.contrasena_inicial',
            descripcion: "{$usuario->email} definió su contraseña personal",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            actor: $usuario,
        );

        return $usuario;
    }
}
