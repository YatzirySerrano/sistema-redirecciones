<?php

use App\Models\Rol;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crea los permisos de Servicios, Pagos y Fidelidad y el rol de sistema
     * «Empleado», y otorga los permisos nuevos a los roles existentes:
     *
     * - Superadministrador: todos (lo hace la sincronización).
     * - Administrador: servicios.ver, servicios.gestionar y pagos.ver (el
     *   personal de DASTI administra contrataciones). Nunca permisos de
     *   Fidelidad: los datos de consumidores son privados de cada negocio.
     * - Cliente (propietario del negocio): todos los permisos de negocio
     *   nuevos (Fidelidad y contratar servicios).
     *
     * Solo agrega permisos; no retira los configurados por DASTI.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        Rol::porClave('administrador')?->givePermissionTo(['servicios.ver', 'servicios.gestionar', 'pagos.ver']);
        Rol::porClave('cliente')?->givePermissionTo([
            'servicios.contratar', 'fidelidad.ver', 'fidelidad.visitas', 'fidelidad.canjear', 'fidelidad.consumidores',
            'fidelidad.credenciales', 'fidelidad.ajustes', 'fidelidad.campanas', 'fidelidad.personal', 'fidelidad.auditoria',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Los permisos se retiran al revertir el catálogo (sincronización).
    }
};
