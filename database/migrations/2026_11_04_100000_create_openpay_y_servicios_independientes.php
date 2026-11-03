<?php

use App\Models\Rol;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Fase 5: Openpay como pasarela y servicios adicionales independientes.
     *
     * - `servicios`: presentación comercial configurable (beneficios, orden,
     *   impuestos incluidos) y nuevo servicio «Página de enlaces» SIN precio
     *   (DASTI lo configura; no se inventan importes).
     * - `pagos` (orden de pago): folio público, concepto y revisión. Las
     *   columnas `stripe_*` se conservan intactas para leer el historial.
     * - `intentos_pago`: cada cargo creado en el proveedor (una orden puede
     *   reintentarse si la tarjeta se rechaza).
     * - `ajustes_pago`: reembolsos y contracargos detectados.
     * - `notificaciones_pago`: notificaciones recibidas (idempotencia).
     * - `archivos_publicos`: imágenes y videos que los negocios publican en
     *   sus páginas (validados y re-codificados).
     *
     * Solo agrega columnas y tablas; no modifica datos existentes salvo
     * rellenar el folio de los pagos anteriores.
     */
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->json('beneficios')->nullable()->after('descripcion');
            $table->boolean('impuestos_incluidos')->default(true)->after('moneda');
            $table->unsignedSmallInteger('orden')->default(0)->after('activo');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('folio', 40)->nullable()->unique()->after('id');
            $table->string('concepto', 250)->nullable()->after('moneda');
            $table->foreignId('revisado_por')->nullable()->after('requiere_revision')->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
        });

        DB::table('pagos')->whereNull('folio')->orderBy('id')->each(function (object $pago) {
            DB::table('pagos')->where('id', $pago->id)->update(['folio' => 'DASTI-'.str_pad((string) $pago->id, 6, '0', STR_PAD_LEFT)]);
        });

        Schema::create('intentos_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->string('proveedor', 20);
            $table->string('orden_externa', 100)->unique();
            $table->string('transaccion_id', 100)->nullable()->unique();
            $table->string('estado', 20);
            $table->string('estado_externo', 40)->nullable();
            $table->unsignedInteger('importe_centavos');
            $table->char('moneda', 3);
            $table->boolean('livemode')->default(false);
            $table->string('url_pago', 500)->nullable();
            $table->string('codigo_error', 40)->nullable();
            $table->string('mensaje_error', 255)->nullable();
            $table->string('autorizacion', 40)->nullable();
            $table->string('marca_tarjeta', 30)->nullable();
            $table->string('tipo_tarjeta', 20)->nullable();
            $table->char('terminacion_tarjeta', 4)->nullable();
            $table->timestamp('consultado_en')->nullable();
            $table->timestamp('completado_en')->nullable();
            $table->foreignId('iniciado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pago_id', 'estado']);
            $table->index(['estado', 'created_at']);
        });

        Schema::create('ajustes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->foreignId('intento_pago_id')->nullable()->constrained('intentos_pago')->nullOnDelete();
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->unsignedInteger('importe_centavos')->default(0);
            $table->string('referencia_externa', 100)->nullable();
            $table->string('detalle', 255)->nullable();
            $table->timestamp('detectado_en');
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamps();

            $table->index(['pago_id', 'tipo']);
        });

        Schema::create('notificaciones_pago', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 20);
            $table->char('clave_unica', 64)->unique();
            $table->string('tipo', 60);
            $table->string('transaccion_id', 100)->nullable()->index();
            $table->foreignId('intento_pago_id')->nullable()->constrained('intentos_pago')->nullOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->timestamp('ocurrido_en')->nullable();
            $table->text('codigo_verificacion')->nullable();
            $table->string('resultado', 255)->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();

            $table->index(['proveedor', 'tipo', 'created_at']);
        });

        Schema::create('archivos_publicos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->string('modulo', 20);
            $table->string('tipo', 10);
            $table->string('ruta', 255);
            $table->string('mime', 60);
            $table->string('extension', 8);
            $table->unsignedInteger('bytes');
            $table->unsignedSmallInteger('ancho')->nullable();
            $table->unsignedSmallInteger('alto')->nullable();
            $table->char('hash', 64);
            $table->string('nombre_original', 120)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['negocio_id', 'modulo']);
        });

        $ahora = now();

        DB::table('servicios')->where('clave', 'redirecciones')->update(['orden' => 1, 'beneficios' => json_encode([
            'Enlaces permanentes para tus códigos QR y tarjetas NFC.',
            'Cambia el destino cuando quieras sin reimprimir.',
            'Estadísticas de accesos por producto.',
        ], JSON_UNESCAPED_UNICODE)]);
        DB::table('servicios')->where('clave', 'fidelidad')->update(['orden' => 2, 'beneficios' => json_encode([
            'Tarjetas de sellos digitales con el diseño de tu negocio.',
            'Registro de visitas desde el mostrador con QR o NFC.',
            'Campañas, recompensas y canjes controlados por tu personal.',
            'Consulta del progreso desde el teléfono del consumidor.',
        ], JSON_UNESCAPED_UNICODE)]);

        if (! DB::table('servicios')->where('clave', 'pagina_enlaces')->exists()) {
            DB::table('servicios')->insert([
                'clave' => 'pagina_enlaces',
                'nombre' => 'Página de enlaces',
                'descripcion' => 'Una página pública con tu identidad que reúne redes sociales, contacto y contenidos en una sola dirección.',
                'beneficios' => json_encode([
                    'Enlaces ilimitados, redes sociales, WhatsApp, mapas, videos y más.',
                    'Editor visual con plantillas, colores, fondos y tipografías.',
                    'Sin publicidad ni marcas de agua de DASTI.',
                    'Estadísticas de visitas y clics.',
                    'Conexión de un dominio propio (el dominio lo adquiere y renueva su titular).',
                ], JSON_UNESCAPED_UNICODE),
                'es_base' => false,
                'precio_centavos' => null,
                'moneda' => 'MXN',
                'impuestos_incluidos' => true,
                'vigencia_meses' => null,
                'contratable_en_linea' => false,
                'activo' => true,
                'orden' => 3,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        // Permisos nuevos: Página de enlaces y diseño de la tarjeta de
        // Fidelidad. Solo se agregan; no se retiran los configurados.
        app(SincronizadorPermisos::class)->sincronizar();
        Rol::porClave('cliente')?->givePermissionTo([
            'fidelidad.diseno', 'paginas.ver', 'paginas.editar', 'paginas.publicar', 'paginas.dominios', 'paginas.mensajes',
        ]);
        Rol::porClave('administrador')?->givePermissionTo(['paginas.supervisar']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_publicos');
        Schema::dropIfExists('notificaciones_pago');
        Schema::dropIfExists('ajustes_pago');
        Schema::dropIfExists('intentos_pago');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['folio', 'concepto', 'revisado_en']);
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['beneficios', 'impuestos_incluidos', 'orden']);
        });

        DB::table('servicios')->where('clave', 'pagina_enlaces')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('contrataciones')->whereColumn('contrataciones.servicio_id', 'servicios.id'))
            ->delete();
    }
};
