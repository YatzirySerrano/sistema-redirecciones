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

    /*
    |--------------------------------------------------------------------------
    | Archivos publicados (Página de enlaces y tarjeta de Fidelidad)
    |--------------------------------------------------------------------------
    |
    | Límites por archivo y cuota por negocio y módulo.
    |
    */

    'medios' => [
        // Límites por tipo (KB). El efectivo es el menor entre estos y lo que
        // acepta PHP (upload_max_filesize/post_max_size): ver
        // App\Services\Medios\LimitesArchivos y docs/despliegue.md.
        'logo_max_kb' => (int) env('DASTI_MEDIOS_LOGO_MAX_KB', 5120),
        'imagen_max_kb' => (int) env('DASTI_MEDIOS_IMAGEN_MAX_KB', 10240),
        'video_max_kb' => (int) env('DASTI_MEDIOS_VIDEO_MAX_KB', 20480),
        'max_archivos' => (int) env('DASTI_MEDIOS_MAX_ARCHIVOS', 300),
        'cuota_mb' => (int) env('DASTI_MEDIOS_CUOTA_MB', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mostrador de Fidelidad
    |--------------------------------------------------------------------------
    |
    | Espera (milisegundos) entre la última tecla y la búsqueda automática.
    |
    */

    'fidelidad' => [
        'busqueda_espera_ms' => (int) env('DASTI_FIDELIDAD_BUSQUEDA_ESPERA_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Página de enlaces
    |--------------------------------------------------------------------------
    |
    | Prefijo público estable (/{prefijo}/{slug}). Igual que los enlaces
    | permanentes, NO debe cambiar una vez que los negocios compartieron su
    | dirección. Los dominios personalizados apuntan por CNAME al destino
    | configurado o por A/AAAA a las IP de entrada de DASTI.
    |
    */

    'paginas' => [
        'prefijo' => (string) env('DASTI_PAGINAS_PREFIJO', 'l'),

        // Hosts adicionales del propio DASTI (además de APP_URL y
        // DASTI_URL_PUBLICA), separados por comas. Nunca se tratan como
        // dominios personalizados.
        'hosts_propios' => array_values(array_filter(array_map('trim', explode(',', (string) env('DASTI_PAGINAS_HOSTS_PROPIOS', ''))))),

        // Dominios de DASTI que un negocio nunca puede registrar (ni sus
        // subdominios).
        'dominios_reservados' => array_values(array_filter(array_map('trim', explode(',', (string) env('DASTI_PAGINAS_DOMINIOS_RESERVADOS', 'dasti.cloud,dasti.mx,dasti.com'))))),

        'dominios' => [
            // Destino del CNAME para subdominios (links.minegocio.com).
            'cname' => (string) env('DASTI_PAGINAS_CNAME', 'paginas.dasti.cloud'),
            // IP de entrada (Caddy) para dominios raíz (registros A/AAAA).
            // Sin ellas no se puede comprobar HTTPS de forma segura.
            'ips_ingreso' => array_values(array_filter(array_map('trim', explode(',', (string) env('DASTI_PAGINAS_IPS_INGRESO', ''))))),
            'registro_txt' => '_dasti-verificacion',
            // Secreto opcional para el endpoint on_demand_tls (además de la
            // restricción a localhost): ?secreto=...
            'secreto_autorizacion' => (string) env('DASTI_PAGINAS_SECRETO_TLS', ''),
            'max_por_pagina' => (int) env('DASTI_PAGINAS_MAX_DOMINIOS', 3),
            // Revisiones fallidas consecutivas antes de marcar un dominio
            // activo como «con error».
            'fallos_para_error' => 3,
        ],

        'limites' => [
            'subpaginas' => 20,
            'bloques_por_pagina' => 150,
            'bloques_totales' => 600,
            'elementos_grupo' => 60,
            'imagenes_carrusel' => 12,
            'redes' => 20,
            'documento_kb' => 400,
            'versiones' => 30,
        ],

        'slug' => [
            // Horas mínimas entre dos cambios de dirección.
            'horas_entre_cambios' => (int) env('DASTI_PAGINAS_HORAS_CAMBIO_SLUG', 24),
            // Direcciones anteriores que siguen redirigiendo (y reservadas).
            'max_alias' => 10,
            // Una dirección anterior no se libera antes de estos días.
            'dias_reserva_alias' => 90,
        ],

        // Eventos individuales (visitas, clics). Los totales diarios se
        // conservan; la huella diaria usa dias_conservacion_huella.
        'dias_retencion_eventos' => (int) env('DASTI_PAGINAS_DIAS_RETENCION', 400),

        'contacto' => [
            'segundos_minimos' => 3,
            'horas_validez_marca' => 48,
            'por_ip_cada_10_min' => 5,
            'por_pagina_por_hora' => 60,
            'max_enlaces' => 2,
        ],
    ],

];
