<?php

namespace App\Services\Autorizacion;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reglas contra el escalamiento de privilegios y el bloqueo accidental.
 *
 * - Solo un superadministrador asigna, retira o administra el rol
 *   Superadministrador.
 * - Fuera del superadministrador, nadie otorga (ni retira) permisos de
 *   plataforma que no tenga él mismo, ni asigna roles de plataforma con más
 *   permisos que los suyos, ni modifica a usuarios con más permisos.
 * - Los roles de negocio (clientes) solo admiten permisos de negocio, que
 *   nunca salen del negocio del usuario; por eso no se comparan con los del
 *   personal que los asigna.
 * - Siempre debe quedar al menos un superadministrador activo.
 */
class GuardianRoles
{
    /**
     * @param  list<string>  $permisos
     */
    public function puedeOtorgar(User $actor, array $permisos, string $ambito): bool
    {
        if ($actor->esSuperadministrador() || $ambito === CatalogoPermisos::AMBITO_NEGOCIO) {
            return true;
        }

        return array_diff($permisos, $actor->permisosEfectivos()) === [];
    }

    public function puedeAsignar(User $actor, Rol $rol): bool
    {
        if ($rol->esSuperadministrador()) {
            return $actor->esSuperadministrador();
        }

        $rol->loadMissing('permissions');

        return $this->puedeOtorgar($actor, $rol->nombresPermisos(), $rol->ambito);
    }

    /**
     * ¿Puede el actor cambiar el rol (o el acceso) de este usuario?
     */
    public function puedeGestionar(User $actor, User $objetivo): bool
    {
        $rolActual = $objetivo->rolAsignado();

        return $rolActual === null || $this->puedeAsignar($actor, $rolActual);
    }

    /**
     * Roles que el actor puede asignar a cuentas del ámbito indicado.
     *
     * @return Collection<int, Rol>
     */
    public function rolesAsignables(User $actor, string $ambito): Collection
    {
        return Rol::query()
            ->delAmbito($ambito)
            ->with('permissions')
            ->orderByRaw('clave IS NULL')
            ->orderBy('name')
            ->get()
            ->filter(fn (Rol $rol) => $this->puedeAsignar($actor, $rol))
            ->values();
    }

    public function esUltimoSuperadministradorActivo(User $usuario): bool
    {
        if (! $usuario->esSuperadministrador() || $usuario->estado !== EstadoCuenta::Activo) {
            return false;
        }

        return ! User::query()
            ->where('rol', RolUsuario::Admin)
            ->where('estado', EstadoCuenta::Activo)
            ->whereKeyNot($usuario->id)
            ->whereHas('roles', fn ($q) => $q->where('clave', CatalogoPermisos::ROL_SUPERADMINISTRADOR))
            ->exists();
    }

    /**
     * Rol predeterminado de un ámbito al crear cuentas sin rol explícito.
     */
    public function rolPredeterminado(string $ambito): ?Rol
    {
        return Rol::porClave($ambito === CatalogoPermisos::AMBITO_NEGOCIO
            ? CatalogoPermisos::ROL_CLIENTE
            : CatalogoPermisos::ROL_ADMINISTRADOR);
    }
}
