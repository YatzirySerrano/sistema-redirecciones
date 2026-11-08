<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Productos con uno o dos QR impresos, cada uno con su propio enlace.
 *
 * Reutiliza el modelo de enlaces secundarios (producto_principal_id): el
 * segundo QR es otra fila de `productos` con medio «qr», su propio código,
 * destino, diseño (opciones_qr) y estadísticas.
 *
 * - cantidad_qr (solo en la fila principal): 1 o 2 en productos QR y
 *   combinados; NULL en productos NFC y en los enlaces secundarios.
 * - numero_qr: 1 o 2 en los enlaces QR de un producto con dos QR (etiquetas
 *   «QR 1» / «QR 2»); NULL en el resto. El medio sigue siendo «qr», así que
 *   las estadísticas por canal (COALESCE(medio, tipo)) no cambian.
 * - archivado_en: al reducir de 2 a 1 QR el segundo enlace no se borra (su
 *   historial y estadísticas se conservan): queda suspendido y archivado. Si
 *   se vuelve a ampliar, se reactiva el mismo enlace (mismo código).
 *
 * Los productos existentes quedan con 1 QR (los NFC sin valor): no cambia
 * ningún código, destino ni estadística.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedTinyInteger('cantidad_qr')->nullable()->after('medio');
            $table->unsignedTinyInteger('numero_qr')->nullable()->after('cantidad_qr');
            $table->timestamp('archivado_en')->nullable()->after('suspendido_en');
        });

        DB::table('productos')
            ->whereNull('producto_principal_id')
            ->whereIn('tipo', ['qr', 'combinado'])
            ->update(['cantidad_qr' => 1]);
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['cantidad_qr', 'numero_qr', 'archivado_en']);
        });
    }
};
