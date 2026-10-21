<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rol, estado y pertenencia de cada usuario. Un usuario cliente pertenece a
     * un único negocio; un negocio puede tener varios usuarios.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('cliente')->after('password');
            $table->string('estado', 20)->default('activo')->after('rol');
            $table->foreignId('negocio_id')->nullable()->after('estado')
                ->constrained('negocios')->restrictOnDelete();
            $table->boolean('debe_cambiar_password')->default(false)->after('negocio_id');
            $table->timestamp('ultimo_acceso_en')->nullable()->after('debe_cambiar_password');
            $table->timestamp('suspendido_en')->nullable()->after('ultimo_acceso_en');

            $table->index(['rol', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('negocio_id');
            $table->dropIndex(['rol', 'estado']);
            $table->dropColumn(['rol', 'estado', 'debe_cambiar_password', 'ultimo_acceso_en', 'suspendido_en']);
        });
    }
};
