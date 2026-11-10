<?php

use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra «productos.editar_nombre» (el cliente cambia solo el nombre
 * descriptivo de sus productos). Migración solo de datos: no cambia el
 * esquema. Ningún rol lo recibe aquí; DASTI lo habilita a los roles de
 * cliente que decida desde «Roles y permisos».
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();
    }

    public function down(): void
    {
        // El permiso se retira al sincronizar con un catálogo que ya no lo
        // contenga; no se elimina aquí para no alterar roles.
    }
};
