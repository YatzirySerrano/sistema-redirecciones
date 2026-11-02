<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fidelidad DASTI (programas de visitas por negocio).
     *
     * - Las visitas pertenecen a la PARTICIPACIÓN del consumidor (cuenta),
     *   nunca a la tarjeta física: reemplazar credenciales no pierde progreso.
     * - Cada participación queda ligada a la versión de campaña con la que se
     *   inscribió (las condiciones prometidas no cambian retroactivamente).
     * - Movimientos y recompensas nunca se borran; `operacion_uuid` único
     *   garantiza idempotencia ante doble clic, reintentos y concurrencia.
     * - Escanear una credencial no crea movimientos (no hay relación entre
     *   `eventos_acceso` y estas tablas).
     */
    public function up(): void
    {
        Schema::create('fidelidad_campanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('estado', 20)->default('activa');
            $table->unsignedInteger('version_actual')->default(1);
            $table->timestamps();

            $table->index(['negocio_id', 'estado']);
        });

        Schema::create('fidelidad_campana_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('fidelidad_campanas')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('descripcion', 500)->nullable();
            $table->unsignedSmallInteger('meta_visitas');
            $table->string('recompensa', 160);
            $table->unsignedSmallInteger('vigencia_visitas_dias')->nullable();
            $table->unsignedSmallInteger('vigencia_recompensa_dias')->nullable();
            $table->date('inicia_en');
            $table->date('termina_en')->nullable();
            $table->unsignedInteger('limite_participantes')->nullable();
            $table->unsignedTinyInteger('max_visitas_por_dia')->default(1);
            $table->unsignedSmallInteger('intervalo_minimo_minutos')->default(0);
            $table->boolean('repetir_ciclo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['campana_id', 'version']);
        });

        Schema::create('fidelidad_consumidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('alias', 60)->nullable();
            $table->string('telefono', 10)->nullable();
            $table->string('email', 255)->nullable();
            $table->timestamp('email_verificado_en')->nullable();
            $table->timestamp('consentimiento_en');
            $table->string('consentimiento_version', 20);
            $table->string('estado', 20)->default('activo');
            $table->char('token_acceso_hash', 64)->nullable()->unique();
            $table->timestamp('token_acceso_emitido_en')->nullable();
            $table->foreignId('fusionado_en_id')->nullable()->constrained('fidelidad_consumidores')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['negocio_id', 'telefono']);
            $table->unique(['negocio_id', 'email']);
            $table->index(['negocio_id', 'nombre']);
        });

        Schema::create('fidelidad_credenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('consumidor_id')->constrained('fidelidad_consumidores')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('codigo', 40)->unique();
            $table->string('estado', 20)->default('activa');
            $table->string('motivo', 255)->nullable();
            $table->timestamp('revocada_en')->nullable();
            $table->foreignId('emitida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['consumidor_id', 'estado']);
        });

        Schema::create('fidelidad_participaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('consumidor_id')->constrained('fidelidad_consumidores')->restrictOnDelete();
            $table->foreignId('campana_id')->constrained('fidelidad_campanas')->restrictOnDelete();
            $table->foreignId('campana_version_id')->constrained('fidelidad_campana_versiones')->restrictOnDelete();
            $table->unsignedInteger('ciclo')->default(1);
            $table->unsignedSmallInteger('visitas_ciclo')->default(0);
            $table->unsignedInteger('visitas_totales')->default(0);
            $table->timestamp('ultima_visita_en')->nullable();
            $table->string('estado', 20)->default('activa');
            $table->timestamps();

            $table->unique(['consumidor_id', 'campana_id']);
            $table->index(['negocio_id', 'campana_id']);
        });

        Schema::create('fidelidad_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('participacion_id')->constrained('fidelidad_participaciones')->restrictOnDelete();
            $table->foreignId('consumidor_id')->constrained('fidelidad_consumidores')->restrictOnDelete();
            $table->foreignId('credencial_id')->nullable()->constrained('fidelidad_credenciales')->nullOnDelete();
            $table->string('tipo', 20);
            $table->smallInteger('cantidad');
            $table->unsignedInteger('ciclo');
            $table->uuid('operacion_uuid')->unique();
            $table->string('motivo', 255)->nullable();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['negocio_id', 'created_at']);
            $table->index(['participacion_id', 'created_at']);
            $table->index(['autorizado_por', 'created_at']);
        });

        Schema::create('fidelidad_recompensas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('participacion_id')->constrained('fidelidad_participaciones')->restrictOnDelete();
            $table->foreignId('consumidor_id')->constrained('fidelidad_consumidores')->restrictOnDelete();
            $table->unsignedInteger('ciclo');
            $table->string('descripcion', 160);
            $table->string('estado', 20)->default('disponible');
            $table->timestamp('vence_en')->nullable();
            $table->uuid('canje_uuid')->nullable()->unique();
            $table->timestamp('canjeada_en')->nullable();
            $table->foreignId('canjeada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->unique(['participacion_id', 'ciclo']);
            $table->index(['negocio_id', 'estado']);
        });

        Schema::create('fidelidad_recuperaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumidor_id')->constrained('fidelidad_consumidores')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('proposito', 20);
            $table->timestamp('expira_en');
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fidelidad_recuperaciones');
        Schema::dropIfExists('fidelidad_recompensas');
        Schema::dropIfExists('fidelidad_movimientos');
        Schema::dropIfExists('fidelidad_participaciones');
        Schema::dropIfExists('fidelidad_credenciales');
        Schema::dropIfExists('fidelidad_consumidores');
        Schema::dropIfExists('fidelidad_campana_versiones');
        Schema::dropIfExists('fidelidad_campanas');
    }
};
