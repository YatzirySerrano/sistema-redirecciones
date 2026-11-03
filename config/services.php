<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Openpay (pagos en línea de servicios adicionales; formulario de pago
    | alojado por Openpay). Sin OPENPAY_MERCHANT_ID / OPENPAY_PRIVATE_KEY la
    | compra en línea se deshabilita; sin OPENPAY_WEBHOOK_USER /
    | OPENPAY_WEBHOOK_PASSWORD las notificaciones se rechazan (503).
    | OPENPAY_PRODUCTION=false usa sandbox-api.openpay.mx (pruebas).
    */
    'openpay' => [
        'merchant_id' => env('OPENPAY_MERCHANT_ID'),
        'private_key' => env('OPENPAY_PRIVATE_KEY'),
        // No se usa con el formulario alojado (solo con Openpay.js).
        'public_key' => env('OPENPAY_PUBLIC_KEY'),
        'production' => (bool) env('OPENPAY_PRODUCTION', false),
        'webhook_user' => env('OPENPAY_WEBHOOK_USER'),
        'webhook_password' => env('OPENPAY_WEBHOOK_PASSWORD'),
        // Opcional: IP o rangos CIDR desde los que Openpay envía
        // notificaciones, separados por comas.
        'webhook_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('OPENPAY_WEBHOOK_IPS', ''))))),
        'timeout' => (int) env('OPENPAY_TIMEOUT', 20),
        // Horas tras las que un pago no completado se da por caducado.
        'horas_expiracion' => (int) env('OPENPAY_HORAS_EXPIRACION', 24),
    ],

];
