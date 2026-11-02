<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - preferencias: ajustes de interfaz por usuario (p. ej. vista «tabla» o
     *   «tarjetas» de cada listado). Nula: no altera los registros actuales.
     * - Índice por nombre para los selectores con búsqueda remota de
     *   usuarios, que ordenan por nombre con límite de resultados.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferencias')->nullable()->after('suspendido_en');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropColumn('preferencias');
        });
    }
};
