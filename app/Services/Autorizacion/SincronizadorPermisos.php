<?php

namespace App\Services\Autorizacion;

use App\Models\Rol;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mantiene en MariaDB los permisos del catálogo y los roles del sistema.
 *
 * Es idempotente: crea lo que falta, retira de la base los permisos que ya
 * no existen en el código y, en roles de negocio, los permisos que ese
 * ámbito no admite. No sobrescribe los permisos configurados de los roles
 * Administrador y Cliente (solo los asigna al crearlos). El
 * superadministrador siempre conserva todos.
 */
class SincronizadorPermisos
{
    public const GUARD = 'web';

    public function __construct(private readonly PermissionRegistrar $registro) {}

    public function sincronizar(): void
    {
        $catalogo = CatalogoPermisos::todos();

        foreach ($catalogo as $nombre) {
            Permission::query()->firstOrCreate(['name' => $nombre, 'guard_name' => self::GUARD]);
        }

        Permission::query()->where('guard_name', self::GUARD)->whereNotIn('name', $catalogo)->delete();
        $this->registro->forgetCachedPermissions();

        foreach (CatalogoPermisos::ROLES_SISTEMA as $clave => $datos) {
            $rol = Rol::query()->where('clave', $clave)->first();

            if ($rol === null) {
                $rol = new Rol;
                $rol->forceFill([
                    'name' => $datos['nombre'],
                    'guard_name' => self::GUARD,
                    'clave' => $clave,
                    'descripcion' => $datos['descripcion'],
                    'ambito' => $datos['ambito'],
                ])->save();
                $rol->syncPermissions(CatalogoPermisos::predeterminados($clave));
            } elseif ($rol->esSuperadministrador()) {
                $rol->syncPermissions(CatalogoPermisos::predeterminados($clave));
            }
        }

        // Roles de negocio: nunca conservan permisos de plataforma.
        Rol::query()->delAmbito(CatalogoPermisos::AMBITO_NEGOCIO)->with('permissions')->get()
            ->each(function (Rol $rol) {
                $validos = array_values(array_intersect(
                    $rol->permissions->pluck('name')->all(),
                    CatalogoPermisos::delAmbito(CatalogoPermisos::AMBITO_NEGOCIO),
                ));

                if (count($validos) !== $rol->permissions->count()) {
                    $rol->syncPermissions($validos);
                }
            });

        $this->registro->forgetCachedPermissions();
    }
}
