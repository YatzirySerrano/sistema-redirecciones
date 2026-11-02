<?php

namespace App\Actions\Roles;

use App\Models\Rol;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Autorizacion\GuardianRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Elimina un rol creado desde el panel. Los roles del sistema y los que
 * tienen usuarios asignados no se eliminan (nadie se queda sin rol).
 */
class EliminarRol
{
    public function __construct(
        private readonly GuardianRoles $guardian,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function ejecutar(User $actor, Rol $rol): void
    {
        if ($rol->esDelSistema()) {
            throw ValidationException::withMessages(['rol' => 'Los roles del sistema no se pueden eliminar.']);
        }

        if ($rol->users()->exists()) {
            throw ValidationException::withMessages(['rol' => 'El rol tiene usuarios asignados. Asígnales otro rol antes de eliminarlo.']);
        }

        if (! $this->guardian->puedeAsignar($actor, $rol)) {
            throw new AuthorizationException('No puedes eliminar un rol con permisos que tú no tienes.');
        }

        DB::transaction(function () use ($rol) {
            $datos = ['nombre' => $rol->name, 'ambito' => $rol->ambito, 'permisos' => $rol->nombresPermisos()];
            $this->auditoria->registrar(
                accion: 'rol.eliminado',
                descripcion: "Eliminación del rol «{$rol->name}»",
                recurso: $rol,
                anteriores: $datos,
            );
            $rol->delete();
        });
    }
}
