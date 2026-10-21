<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enlaces permanentes
    |--------------------------------------------------------------------------
    |
    | URL base pública con la que se construyen los enlaces permanentes que se
    | imprimen en los QR y se graban en las tarjetas NFC. Debe coincidir con el
    | dominio definitivo (https://redireccion.dasti.cloud). Una vez entregados
    | productos físicos, este valor NO debe cambiar.
    |
    */

    'url_publica' => rtrim((string) env('DASTI_URL_PUBLICA', env('APP_URL', 'http://localhost')), '/'),

    'prefijo_redireccion' => 'r',

    // Alfabeto en minúsculas sin caracteres ambiguos (0/o, 1/l/i). Al no
    // depender de mayúsculas, el código es inmune a la intercalación
    // insensible a mayúsculas de MariaDB y fácil de dictar o transcribir.
    'codigo' => [
        'alfabeto' => '23456789abcdefghjkmnpqrstuvwxyz',
        'longitud' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registro de accesos
    |--------------------------------------------------------------------------
    |
    | Por defecto el evento se registra después de enviar la respuesta al
    | visitante (sin necesidad de worker). Con tráfico elevado se puede
    | activar la cola para delegar el trabajo a `php artisan queue:work`.
    |
    */

    'eventos_en_cola' => (bool) env('DASTI_EVENTOS_EN_COLA', false),

    // Tras este número de días se elimina la huella anónima diaria (huella_diaria)
    // de los eventos, conservando el resto de datos agregables.
    'dias_conservacion_huella' => (int) env('DASTI_DIAS_CONSERVACION_HUELLA', 30),

    // Límite de solicitudes por minuto e IP sobre los enlaces públicos.
    'limite_redirecciones_por_minuto' => (int) env('DASTI_LIMITE_REDIRECCIONES', 120),

    /*
    |--------------------------------------------------------------------------
    | Invitaciones de acceso
    |--------------------------------------------------------------------------
    |
    | Horas de validez del enlace de un solo uso con el que un usuario nuevo
    | define su contraseña. Un reenvío invalida los enlaces anteriores.
    |
    */

    'horas_validez_invitacion' => (int) env('DASTI_HORAS_VALIDEZ_INVITACION', 72),

    /*
    |--------------------------------------------------------------------------
    | Seguridad de administradores
    |--------------------------------------------------------------------------
    |
    | Exige verificación en dos pasos a los administradores antes de usar el
    | panel de DASTI (un administrador puede cambiar todos los destinos). Por
    | defecto se exige en producción.
    |
    */

    'exigir_2fa_administradores' => (bool) env('DASTI_EXIGIR_2FA_ADMIN', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Datos de demostración
    |--------------------------------------------------------------------------
    |
    | Contraseña usada por los seeders de demostración. Solo se utiliza en
    | entornos locales; los seeders se niegan a ejecutarse en producción.
    |
    */

    'demo_password' => env('DASTI_DEMO_PASSWORD', 'Demo-Dasti-2026'),

];
