<?php

namespace App\Actions\Roles;

use App\Models\Rol;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\GuardianRoles;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea o actualiza un rol y sus permisos, con las protecciones contra
 * escalamiento de privilegios (GuardianRoles) y auditoría de los permisos
 * agregados y retirados.
 */
class GuardarRol
{
    public function __construct(
        private readonly GuardianRoles $guardian,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @param  array{name: string, descripcion?: string|null, ambito: string, permisos?: list<string>}  $datos
     */
    public function crear(User $actor, array $datos): Rol
    {
        $ambito = $datos['ambito'];
        $permisos = $this->normalizar($datos['permisos'] ?? [], $ambito);

        if ($permisos !== [] && ! $actor->tienePermiso('roles.administrar_permisos')) {
            throw new AuthorizationException('No tienes permiso para asignar permisos a los roles.');
        }

        $this->exigirOtorgables($actor, $permisos, $ambito);

        return DB::transaction(function () use ($datos, $ambito, $permisos) {
            $rol = new Rol;
            $rol->forceFill([
                'name' => trim($datos['name']),
                'guard_name' => SincronizadorPermisos::GUARD,
                'descripcion' => $datos['descripcion'] ?? null,
                'ambito' => $ambito,
            ])->save();
            $rol->syncPermissions($permisos);

            $this->auditoria->registrar(
                accion: 'rol.creado',
                descripcion: "Alta del rol «{$rol->name}»",
                recurso: $rol,
                nuevos: ['nombre' => $rol->name, 'ambito' => $ambito, 'permisos' => $permisos],
            );

            return $rol;
        });
    }

    /**
     * @param  array{name?: string, descripcion?: string|null, permisos?: list<string>|null}  $datos
     */
    public function actualizar(User $actor, Rol $rol, array $datos): Rol
    {
        $rol->loadMissing('permissions');

        if ($rol->esSuperadministrador() && ! $actor->esSuperadministrador()) {
            throw new AuthorizationException('Solo un superadministrador puede modificar este rol.');
        }

        $anteriores = $rol->nombresPermisos();
        $permisos = array_key_exists('permisos', $datos) && $datos['permisos'] !== null && ! $rol->esSuperadministrador()
            ? $this->normalizar($datos['permisos'], $rol->ambito)
            : $anteriores;

        $agregados = array_values(array_diff($permisos, $anteriores));
        $retirados = array_values(array_diff($anteriores, $permisos));
        $cambianPermisos = $agregados !== [] || $retirados !== [];

        if ($cambianPermisos) {
            if (! $actor->tienePermiso('roles.administrar_permisos')) {
                throw new AuthorizationException('No tienes permiso para modificar los permisos de los roles.');
            }

            // Nadie (salvo el superadministrador) cambia los permisos del rol
            // que tiene asignado: evita otorgarse o quitarse privilegios.
            if (! $actor->esSuperadministrador() && $actor->rolAsignado()?->is($rol)) {
                throw new AuthorizationException('No puedes modificar los permisos de tu propio rol.');
            }

            $this->exigirOtorgables($actor, [...$agregados, ...$retirados], $rol->ambito);
        }

        $nombre = $rol->esDelSistema() ? $rol->name : trim($datos['name'] ?? $rol->name);
        $descripcion = array_key_exists('descripcion', $datos) ? $datos['descripcion'] : $rol->descripcion;
        $cambios = [];

        if ($nombre !== $rol->name) {
            $cambios['nombre'] = [$rol->name, $nombre];
        }

        if ($descripcion !== $rol->descripcion) {
            $cambios['descripcion'] = [$rol->descripcion, $descripcion];
        }

        if ($cambios === [] && ! $cambianPermisos) {
            return $rol;
        }

        return DB::transaction(function () use ($rol, $nombre, $descripcion, $permisos, $agregados, $retirados, $cambios, $cambianPermisos) {
            $rol->forceFill(['name' => $nombre, 'descripcion' => $descripcion])->save();

            if ($cambianPermisos) {
                $rol->syncPermissions($permisos);
            }

            $this->auditoria->registrar(
                accion: 'rol.actualizado',
                descripcion: "Actualización del rol «{$rol->name}»",
                recurso: $rol,
                anteriores: array_filter([
                    'nombre' => isset($cambios['nombre']) ? $cambios['nombre'][0] : null,
                    'descripcion' => isset($cambios['descripcion']) ? $cambios['descripcion'][0] : null,
                    'permisos_retirados' => $retirados ?: null,
                ], fn ($v) => $v !== null),
                nuevos: array_filter([
                    'nombre' => isset($cambios['nombre']) ? $cambios['nombre'][1] : null,
                    'descripcion' => isset($cambios['descripcion']) ? $cambios['descripcion'][1] : null,
                    'permisos_agregados' => $agregados ?: null,
                ], fn ($v) => $v !== null),
            );

            return $rol->refresh();
        });
    }

    /**
     * @param  list<string>  $permisos
     * @return list<string>
     */
    private function normalizar(array $permisos, string $ambito): array
    {
        $permisos = array_values(array_unique($permisos));
        $invalidos = array_filter($permisos, fn (string $p) => ! CatalogoPermisos::admiteAmbito($p, $ambito));

        if ($invalidos !== []) {
            throw ValidationException::withMessages([
                'permisos' => $ambito === CatalogoPermisos::AMBITO_NEGOCIO
                    ? 'Un rol de cliente solo puede tener permisos sobre los recursos de su negocio.'
                    : 'Hay permisos que no existen.',
            ]);
        }

        sort($permisos);

        return $permisos;
    }

    /**
     * @param  list<string>  $permisos
     */
    private function exigirOtorgables(User $actor, array $permisos, string $ambito): void
    {
        if (! $this->guardian->puedeOtorgar($actor, $permisos, $ambito)) {
            throw new AuthorizationException('No puedes otorgar ni retirar permisos que tú no tienes.');
        }
    }
}
