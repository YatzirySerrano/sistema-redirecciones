<?php

namespace App\Actions\Usuarios;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Crea un acceso (cliente o administrador).
 *
 * - Con contraseña: se toma como temporal y se exige cambiarla al entrar.
 * - Sin contraseña: se asigna una aleatoria que nadie conoce y se envía una
 *   invitación por correo para que el usuario defina la suya.
 */
class CrearUsuario
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly EnviarInvitacion $enviarInvitacion,
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string|null}  $datos
     */
    public function ejecutar(RolUsuario $rol, array $datos, ?Negocio $negocio = null): User
    {
        if ($rol === RolUsuario::Cliente && $negocio === null) {
            throw new InvalidArgumentException('Un usuario cliente debe pertenecer a un negocio.');
        }

        $password = $datos['password'] ?? null;
        $invitar = $password === null || $password === '';

        $usuario = new User([
            'name' => $datos['name'],
            'email' => Str::lower($datos['email']),
            'password' => $invitar ? Str::password(40) : $password,
        ]);
        $usuario->forceFill([
            'rol' => $rol,
            'estado' => EstadoCuenta::Activo,
            'negocio_id' => $rol === RolUsuario::Cliente ? $negocio->id : null,
            'debe_cambiar_password' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->auditoria->registrar(
            accion: 'usuario.creado',
            descripcion: "Alta del acceso de {$usuario->name} ({$usuario->email})",
            recurso: $usuario,
            negocio: $negocio,
            nuevos: [
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $rol,
                'metodo_acceso' => $invitar ? 'invitacion_por_correo' : 'contrasena_temporal',
            ],
        );

        if ($invitar) {
            $this->enviarInvitacion->ejecutar($usuario, registrar: false);
        }

        return $usuario;
    }
}
