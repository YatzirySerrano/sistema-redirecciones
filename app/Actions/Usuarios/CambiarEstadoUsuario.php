<?php

namespace App\Actions\Usuarios;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\SesionesUsuario;
use Illuminate\Validation\ValidationException;

/**
 * Suspende o reactiva el acceso de un usuario. Al suspender se cierran sus
 * sesiones activas y se revocan sus tokens de "recordarme".
 */
class CambiarEstadoUsuario
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly SesionesUsuario $sesiones,
    ) {}

    public function ejecutar(User $usuario, EstadoCuenta $estado, User $actor, ?string $motivo = null): User
    {
        if ($usuario->estado === $estado) {
            return $usuario;
        }

        $suspender = $estado === EstadoCuenta::Suspendido;

        if ($suspender && $usuario->is($actor)) {
            throw ValidationException::withMessages(['usuario' => 'No puedes suspender tu propio acceso.']);
        }

        if ($suspender && $usuario->esAdministrador() && $this->esUltimoAdministradorActivo($usuario)) {
            throw ValidationException::withMessages(['usuario' => 'Debe existir al menos un administrador activo.']);
        }

        $anterior = $usuario->estado;
        $usuario->forceFill([
            'estado' => $estado,
            'suspendido_en' => $suspender ? now() : null,
        ])->save();

        if ($suspender) {
            $this->sesiones->cerrarTodas($usuario);
        }

        $this->auditoria->registrar(
            accion: $suspender ? 'usuario.suspendido' : 'usuario.reactivado',
            descripcion: ($suspender ? 'Suspensión' : 'Reactivación')." del acceso de {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            anteriores: ['estado' => $anterior],
            nuevos: array_filter(['estado' => $estado, 'motivo' => $motivo]),
            actor: $actor,
        );

        return $usuario;
    }

    private function esUltimoAdministradorActivo(User $usuario): bool
    {
        return ! User::query()
            ->where('rol', RolUsuario::Admin)
            ->where('estado', EstadoCuenta::Activo)
            ->whereKeyNot($usuario->id)
            ->exists();
    }
}
