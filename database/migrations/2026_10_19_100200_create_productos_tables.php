<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos físicos vendidos (QR, NFC o combinados) con su enlace
     * permanente, y el historial inmutable de cambios de destino.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            // Identificador público del enlace permanente: aleatorio, en
            // minúsculas y sin relación con el id interno.
            $table->string('codigo', 16)->unique();
            $table->string('nombre', 120);
            $table->string('tipo', 20);
            $table->string('url_destino', 2048)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->string('estado_preparacion', 20)->default('pendiente');
            $table->date('fecha_entrega')->nullable();
            $table->string('referencia_comercial', 80)->nullable();
            $table->text('notas_internas')->nullable();
            $table->json('opciones_qr')->nullable();
            $table->unsignedBigInteger('total_accesos')->default(0);
            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->timestamp('destino_actualizado_en')->nullable();
            $table->timestamp('suspendido_en')->nullable();
            $table->timestamps();

            $table->index(['negocio_id', 'estado']);
            $table->index(['tipo', 'estado']);
            $table->index('created_at');
        });

        Schema::create('cambios_destino', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            // Negocio propietario en el momento del cambio (sobrevive a
            // transferencias para que cada negocio vea solo su historia).
            $table->foreignId('negocio_id')->constrained('negocios')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 20);
            $table->string('url_anterior', 2048)->nullable();
            $table->string('url_nueva', 2048)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['producto_id', 'created_at']);
            $table->index(['negocio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_destino');
        Schema::dropIfExists('productos');
    }
};
