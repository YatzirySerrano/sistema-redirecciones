<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Negocios (clientes de DASTI). Cada negocio agrupa sus usuarios y los
     * productos físicos que compró; es la frontera del aislamiento
     * multiempresa.
     */
    public function up(): void
    {
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercial', 150);
            $table->string('responsable', 150)->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('notas_internas')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamp('suspendido_en')->nullable();
            $table->timestamps();

            $table->index('nombre_comercial');
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negocios');
    }
};
