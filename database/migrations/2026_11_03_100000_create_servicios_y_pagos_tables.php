<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Servicios comerciales (base: redirecciones; adicional: Fidelidad),
     * contrataciones por negocio, pagos y eventos de Stripe procesados.
     *
     * - `servicios`: catálogo; DASTI configura precio (MXN, centavos) y
     *   vigencia en meses. Sin precio no se puede contratar en línea.
     * - `contrataciones`: un registro por periodo contratado (historial). Su
     *   `modalidad` permite añadir cobros periódicos sin cambiar el esquema.
     * - `pagos`: importe y moneda los fija el servidor al crear el pago. No
     *   se guarda ningún dato de tarjeta.
     * - `eventos_stripe`: idempotencia de webhooks (id de evento único).
     *
     * Solo crea tablas nuevas e inserta el catálogo; no toca datos existentes.
     */
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_base')->default(false);
            $table->unsignedInteger('precio_centavos')->nullable();
            $table->char('moneda', 3)->default('MXN');
            $table->unsignedSmallInteger('vigencia_meses')->nullable();
            $table->boolean('contratable_en_linea')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('contrataciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->string('estado', 20);
            $table->string('origen', 20);
            $table->string('modalidad', 20)->default('unico');
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('vence_en')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('estado_cambiado_en')->nullable();
            $table->boolean('restriccion_manual')->default(false);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['negocio_id', 'servicio_id', 'estado']);
            $table->index(['servicio_id', 'estado']);
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('contratacion_id')->nullable()->constrained('contrataciones')->nullOnDelete();
            $table->string('proveedor', 20);
            $table->string('estado', 20);
            $table->unsignedInteger('importe_centavos');
            $table->char('moneda', 3);
            $table->unsignedInteger('reembolsado_centavos')->default(0);
            $table->string('stripe_checkout_session_id', 255)->nullable()->unique();
            $table->string('stripe_payment_intent_id', 255)->nullable()->index();
            $table->boolean('livemode')->default(false);
            $table->string('referencia', 120)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->boolean('requiere_revision')->default(false);
            $table->timestamp('pagado_en')->nullable();
            $table->timestamp('ultimo_evento_en')->nullable();
            $table->foreignId('iniciado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['negocio_id', 'estado']);
            $table->index(['estado', 'created_at']);
        });

        Schema::table('contrataciones', function (Blueprint $table) {
            $table->foreignId('pago_id')->nullable()->after('origen')->constrained('pagos')->nullOnDelete();
        });

        Schema::create('eventos_stripe', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id', 255)->unique();
            $table->string('tipo', 80);
            $table->timestamp('creado_stripe_en')->nullable();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('resultado', 255)->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('servicios')->insert([
            [
                'clave' => 'redirecciones', 'nombre' => 'Redirecciones QR y NFC',
                'descripcion' => 'Enlaces permanentes con destino editable. Incluido al comprar los productos.',
                'es_base' => true, 'precio_centavos' => null, 'moneda' => 'MXN', 'vigencia_meses' => null,
                'contratable_en_linea' => false, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ],
            [
                'clave' => 'fidelidad', 'nombre' => 'Fidelidad DASTI',
                'descripcion' => 'Tarjetas de sellos digitales: visitas autorizadas, recompensas y canjes.',
                'es_base' => false, 'precio_centavos' => null, 'moneda' => 'MXN', 'vigencia_meses' => null,
                'contratable_en_linea' => false, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('contrataciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pago_id');
        });
        Schema::dropIfExists('eventos_stripe');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('contrataciones');
        Schema::dropIfExists('servicios');
    }
};
