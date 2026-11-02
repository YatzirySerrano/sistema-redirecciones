<?php

namespace App\Actions\Usuarios;

use App\Models\Rol;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Autorizacion\GuardianRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el rol de un usuario. El rol debe corresponder al ámbito de la
 * cuenta (un cliente nunca recibe un rol de plataforma) y respetar las
 * reglas contra escalamiento. No cierra sesiones: los permisos se evalúan
 * en cada petición.
 */
class AsignarRol
{
    public function __construct(
        private readonly GuardianRoles $guardian,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function ejecutar(User $actor, User $usuario, Rol $rol): bool
    {
        $anterior = $usuario->rolAsignado();

        if ($anterior?->is($rol)) {
            return false;
        }

        if ($usuario->is($actor)) {
            throw new AuthorizationException('No puedes cambiar tu propio rol.');
        }

        if ($rol->ambito !== $usuario->ambito()) {
            throw ValidationException::withMessages([
                'rol_id' => $usuario->esCliente()
                    ? 'Un usuario de cliente solo puede tener roles de cliente.'
                    : 'El personal de DASTI solo puede tener roles de plataforma.',
            ]);
        }

        if (! $this->guardian->puedeGestionar($actor, $usuario) || ! $this->guardian->puedeAsignar($actor, $rol)) {
            throw new AuthorizationException('No puedes asignar un rol con permisos que tú no tienes.');
        }

        if ($this->guardian->esUltimoSuperadministradorActivo($usuario)) {
            throw ValidationException::withMessages(['rol_id' => 'Debe existir al menos un superadministrador activo.']);
        }

        DB::transaction(function () use ($usuario, $rol, $anterior) {
            $usuario->syncRoles([$rol]);
            $usuario->olvidarPermisos();

            $this->auditoria->registrar(
                accion: 'usuario.rol_cambiado',
                descripcion: "Cambio de rol de {$usuario->email}: «".($anterior->name ?? 'sin rol')."» → «{$rol->name}»",
                recurso: $usuario,
                negocio: $usuario->negocio_id,
                anteriores: ['rol' => $anterior?->name],
                nuevos: ['rol' => $rol->name],
            );
        });

        return true;
    }

    /**
     * Asignación inicial al crear la cuenta (sin comprobaciones de actor; las
     * hace quien llama).
     */
    public static function inicial(User $usuario, Rol $rol): void
    {
        if ($rol->ambito !== $usuario->ambito()) {
            throw new \InvalidArgumentException('El rol no corresponde al ámbito de la cuenta.');
        }

        $usuario->syncRoles([$rol]);
        $usuario->olvidarPermisos();
    }
}
