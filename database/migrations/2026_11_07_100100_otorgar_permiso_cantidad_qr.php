<?php

use App\Models\Rol;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registra «productos.cambiar_cantidad_qr» (ampliar a 2 QR o reducir a 1).
 *
 * El número de QR lo fija DASTI en el alta: el permiso se otorga solo al rol
 * Administrador (el superadministrador siempre tiene todos). Ningún rol de
 * cliente lo recibe; DASTI puede habilitarlo desde «Roles y permisos».
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        Rol::porClave('administrador')?->givePermissionTo('productos.cambiar_cantidad_qr');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // El permiso se retira al sincronizar con un catálogo que ya no lo
        // contenga; no se elimina aquí para no alterar roles.
    }
};
