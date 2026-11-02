<?php

use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Migración controlada del esquema de roles anterior (columna users.rol)
     * a roles y permisos dinámicos.
     *
     * - Crea los permisos del catálogo y los roles del sistema.
     * - Los administradores existentes tenían control total: reciben el rol
     *   Superadministrador, de modo que conservan exactamente su acceso.
     * - Los clientes existentes reciben el rol Cliente (mismo alcance: solo
     *   su negocio).
     *
     * No crea ni elimina usuarios, no toca contraseñas ni sesiones y solo
     * asigna rol a quien aún no tiene uno (es repetible sin duplicar).
     * users.rol se conserva como ámbito de la cuenta.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        $roles = DB::table('roles')->whereIn('clave', ['superadministrador', 'cliente'])->pluck('id', 'clave');

        $sinRol = DB::table('users')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', 'usuario'))
            ->get(['id', 'rol']);

        foreach ($sinRol as $usuario) {
            $clave = $usuario->rol === 'admin' ? 'superadministrador' : 'cliente';

            DB::table('model_has_roles')->insert([
                'role_id' => $roles[$clave],
                'model_type' => 'usuario',
                'model_id' => $usuario->id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Retira solo las asignaciones; las tablas las elimina la migración
     * anterior al revertirse.
     */
    public function down(): void
    {
        DB::table('model_has_roles')->where('model_type', 'usuario')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
