<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos QR + NFC con destinos independientes.
     *
     * Un producto físico combinado puede tener un enlace permanente por
     * medio: la fila principal (frente, NFC) y un enlace secundario (reverso,
     * QR) en otra fila de `productos` ligada mediante producto_principal_id.
     * Cada enlace conserva su propio código, destino, historial y
     * estadísticas, y el motor público /r/{codigo} no cambia. Los enlaces
     * secundarios no cuentan como productos vendidos.
     *
     * - modalidad_destino: «compartido» (comportamiento actual: un enlace para
     *   ambos medios) o «independiente». Todos los productos existentes
     *   quedan como «compartido»: no se convierte ninguno.
     * - medio: «nfc» o «qr» solo en productos combinados independientes.
     *
     * Columnas nuevas con valores por defecto: no altera códigos, destinos ni
     * estadísticas existentes.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('producto_principal_id')->nullable()->after('negocio_id')
                ->constrained('productos')->restrictOnDelete();
            $table->string('modalidad_destino', 20)->default('compartido')->after('tipo');
            $table->string('medio', 10)->nullable()->after('modalidad_destino');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_principal_id');
            $table->dropColumn(['modalidad_destino', 'medio']);
        });
    }
};
