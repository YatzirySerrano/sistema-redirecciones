<?php

use App\Models\Rol;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Amplía el catálogo de permisos con capacidades que ya existían pero no se
 * podían asignar por separado:
 *
 * - pagos.gestionar / pagos.reembolsar (antes incluidos en
 *   servicios.gestionar): los reciben los roles que ya tenían ese permiso.
 * - paginas.estadisticas (antes incluido en paginas.ver): lo reciben los
 *   roles que ya tenían paginas.ver.
 * - fidelidad.disenar_negocios / paginas.disenar_negocios (nuevo flujo de
 *   diseño para clientes): se asignan al rol Administrador (personal DASTI);
 *   el superadministrador siempre tiene todos.
 *
 * Solo agrega permisos: ningún rol pierde lo que tenía.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        $equivalencias = [
            'servicios.gestionar' => ['pagos.gestionar', 'pagos.reembolsar'],
            'paginas.ver' => ['paginas.estadisticas'],
        ];

        Rol::query()->with('permissions')->get()->each(function (Rol $rol) use ($equivalencias) {
            $tiene = $rol->permissions->pluck('name')->all();

            foreach ($equivalencias as $anterior => $nuevos) {
                if (in_array($anterior, $tiene, true)) {
                    $rol->givePermissionTo($nuevos);
                }
            }
        });

        Rol::porClave('administrador')?->givePermissionTo(['fidelidad.disenar_negocios', 'paginas.disenar_negocios']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Los permisos se retiran al sincronizar con un catálogo que ya no
        // los contenga; no se eliminan aquí para no alterar roles.
    }
};
