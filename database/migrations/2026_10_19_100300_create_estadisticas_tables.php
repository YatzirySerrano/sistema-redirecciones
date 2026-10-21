<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de accesos a los enlaces permanentes.
     *
     * - eventos_acceso: detalle mínimo por acceso (sin IP completa; solo una
     *   huella diaria irreversible que se elimina tras el periodo configurado).
     * - accesos_diarios: agregado por producto y día, que mantiene el
     *   histórico de las gráficas aunque los eventos se depuren.
     */
    public function up(): void
    {
        Schema::create('eventos_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->timestamp('ocurrido_en');
            $table->string('resultado', 20);
            $table->string('dispositivo', 20);
            $table->string('navegador', 30)->nullable();
            $table->string('sistema_operativo', 30)->nullable();
            $table->boolean('es_bot')->default(false);
            $table->char('huella_diaria', 64)->nullable();

            $table->index(['producto_id', 'ocurrido_en']);
            $table->index(['negocio_id', 'ocurrido_en']);
            $table->index('ocurrido_en');
        });

        Schema::create('accesos_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('bots')->default(0);

            $table->unique(['producto_id', 'negocio_id', 'fecha']);
            $table->index(['negocio_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_diarios');
        Schema::dropIfExists('eventos_acceso');
    }
};
