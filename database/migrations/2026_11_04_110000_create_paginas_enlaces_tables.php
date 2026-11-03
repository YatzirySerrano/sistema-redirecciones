<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Página de enlaces: una página por negocio (con subpáginas dentro del
 * documento), historial de versiones, direcciones anteriores (alias),
 * dominios personalizados, eventos y totales diarios, y mensajes del
 * formulario de contacto. Solo añade tablas: no modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paginas_enlaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->unique()->constrained('negocios')->restrictOnDelete();
            $table->string('slug', 40)->unique();
            $table->string('estado', 20)->default('borrador');
            // Documento completo validado (DocumentoPagina): borrador y la
            // copia publicada que ven los visitantes.
            $table->longText('borrador');
            $table->longText('publicado')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('borrador_actualizado_en')->nullable();
            $table->foreignId('borrador_actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_en')->nullable();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('despublicado_en')->nullable();
            $table->timestamp('slug_cambiado_en')->nullable();
            $table->string('incidencia', 255)->nullable();
            $table->timestamp('incidencia_en')->nullable();
            $table->timestamps();

            $table->index('estado');
        });

        Schema::create('paginas_enlaces_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('motivo', 30);
            $table->longText('documento');
            $table->unsignedInteger('version_borrador')->nullable();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['pagina_id', 'id']);
        });

        Schema::create('paginas_enlaces_alias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('slug', 40)->unique();
            $table->timestamp('retirado_en');
            $table->timestamps();

            $table->index(['pagina_id', 'retirado_en']);
        });

        Schema::create('paginas_enlaces_dominios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('host', 253);
            $table->string('host_unicode', 253);
            // Igual a host solo mientras el dominio está verificado: un mismo
            // host puede estar «pendiente» en varios negocios (cada uno con su
            // token), pero solo uno puede probar la propiedad y quedar activo.
            $table->string('host_verificado', 253)->nullable()->unique();
            $table->string('estado', 20)->default('pendiente');
            $table->string('token', 64);
            $table->boolean('es_principal')->default(false);
            $table->foreignId('reemplaza_a_id')->nullable()->constrained('paginas_enlaces_dominios')->nullOnDelete();
            $table->timestamp('dns_verificado_en')->nullable();
            $table->timestamp('https_verificado_en')->nullable();
            $table->timestamp('ultima_revision_en')->nullable();
            $table->timestamp('proxima_revision_en')->nullable();
            $table->string('ultimo_error', 255)->nullable();
            $table->unsignedSmallInteger('fallos_consecutivos')->default(0);
            $table->json('diagnostico')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['host', 'negocio_id']);
            $table->index(['estado', 'proxima_revision_en']);
        });

        Schema::create('paginas_enlaces_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->char('bloque_id', 36)->nullable();
            $table->string('subpagina', 40)->nullable();
            $table->timestamp('ocurrido_en');
            $table->date('fecha');
            $table->unsignedTinyInteger('hora');
            $table->string('dispositivo', 20);
            $table->string('referente', 120)->nullable();
            $table->boolean('es_bot')->default(false);
            $table->char('huella_diaria', 64)->nullable();

            $table->index(['negocio_id', 'fecha']);
            $table->index(['pagina_id', 'tipo', 'fecha']);
            $table->index('ocurrido_en');
        });

        Schema::create('paginas_enlaces_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 10);
            // Cadena vacía para los totales de la página (visitas).
            $table->char('bloque_id', 36)->default('');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('bots')->default(0);

            $table->unique(['pagina_id', 'fecha', 'tipo', 'bloque_id'], 'paginas_diarios_unico');
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('paginas_enlaces_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas_enlaces')->cascadeOnDelete();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->char('bloque_id', 36)->nullable();
            $table->string('nombre', 100);
            $table->string('email', 190)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('mensaje');
            $table->timestamp('leido_en')->nullable();
            $table->timestamps();

            $table->index(['negocio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paginas_enlaces_mensajes');
        Schema::dropIfExists('paginas_enlaces_diarios');
        Schema::dropIfExists('paginas_enlaces_eventos');
        Schema::dropIfExists('paginas_enlaces_dominios');
        Schema::dropIfExists('paginas_enlaces_alias');
        Schema::dropIfExists('paginas_enlaces_versiones');
        Schema::dropIfExists('paginas_enlaces');
    }
};
