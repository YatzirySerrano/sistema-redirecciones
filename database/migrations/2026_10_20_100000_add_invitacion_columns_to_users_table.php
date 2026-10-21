<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seguimiento de las invitaciones de acceso.
     *
     * - invitacion_enviada_en: último enlace generado. También forma parte de
     *   la firma del enlace, de modo que un reenvío invalida los anteriores.
     * - invitacion_fallida_en: último intento de envío fallido (SMTP); se
     *   limpia al enviarse con éxito.
     * - invitacion_aceptada_en: el usuario definió su contraseña con el enlace.
     *
     * Columnas nulas: no alteran los registros existentes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invitacion_enviada_en')->nullable()->after('debe_cambiar_password');
            $table->timestamp('invitacion_fallida_en')->nullable()->after('invitacion_enviada_en');
            $table->timestamp('invitacion_aceptada_en')->nullable()->after('invitacion_fallida_en');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitacion_enviada_en', 'invitacion_fallida_en', 'invitacion_aceptada_en']);
        });
    }
};
