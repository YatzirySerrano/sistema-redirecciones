<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diseño de la tarjeta digital de Fidelidad (solo apariencia).
     *
     * - Un diseño general por negocio (`campana_clave` = 0) y, opcionalmente,
     *   una variante por campaña (`campana_clave` = id de la campaña). La
     *   columna `campana_clave` existe porque un índice único con NULL no
     *   impide duplicados en MariaDB.
     * - `borrador` y `publicado` son documentos JSON saneados en el servidor;
     *   `version` sirve de bloqueo optimista entre pestañas o personas.
     * - El historial guarda cada publicación y cada restauración.
     *
     * Estas tablas no tienen relación de escritura con consumidores,
     * participaciones, movimientos ni recompensas: cambiar la apariencia nunca
     * altera datos de operación. Solo se agregan tablas.
     */
    public function up(): void
    {
        Schema::create('fidelidad_disenos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('campana_id')->nullable()->constrained('fidelidad_campanas')->cascadeOnDelete();
            $table->unsignedBigInteger('campana_clave')->default(0);
            $table->json('borrador');
            $table->json('publicado')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('publicado_en')->nullable();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['negocio_id', 'campana_clave']);
        });

        Schema::create('fidelidad_diseno_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diseno_id')->constrained('fidelidad_disenos')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('motivo', 20);
            $table->json('documento');
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['diseno_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fidelidad_diseno_versiones');
        Schema::dropIfExists('fidelidad_disenos');
    }
};
