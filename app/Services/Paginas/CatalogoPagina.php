<?php

namespace App\Services\Paginas;

/**
 * Listas cerradas de la Página de enlaces (tipos de elemento, íconos, redes,
 * tipografías y opciones de diseño). Todo valor que no aparezca aquí se
 * descarta al validar el documento. La presentación de cada opción vive en
 * resources/js/components/paginas/catalogo.ts (mismas claves).
 */
final class CatalogoPagina
{
    /** Elementos que se muestran como botón de acción. */
    public const TIPOS_BOTON = ['enlace', 'whatsapp', 'telefono', 'correo', 'subpagina'];

    public const TIPOS = [
        'enlace', 'whatsapp', 'telefono', 'correo', 'subpagina',
        'redes', 'mapa', 'video', 'musica', 'imagen', 'carrusel',
        'titulo', 'texto', 'separador', 'grupo', 'tarjeta', 'contacto', 'qr',
    ];

    /** Elementos permitidos dentro de un grupo (un solo nivel de anidación). */
    public const TIPOS_EN_GRUPO = ['enlace', 'whatsapp', 'telefono', 'correo', 'subpagina', 'imagen'];

    public const ICONOS = [
        'enlace', 'globo', 'mapa', 'telefono', 'correo', 'mensaje', 'estrella', 'corazon', 'info',
        'documento', 'musica', 'video', 'camara', 'imagen', 'menu', 'cafe', 'regalo', 'etiqueta',
        'descuento', 'reloj', 'usuario', 'equipo', 'maletin', 'libro', 'escuela', 'salud', 'brillo',
        'tijeras', 'auto', 'casa', 'edificio', 'boleto', 'noticias', 'compartir', 'pregunta',
        'escudo', 'flecha', 'wifi', 'hoja', 'pata', 'paleta', 'microfono', 'podcast', 'trofeo',
        'mundo', 'pin', 'calendario',
    ];

    /**
     * Redes sociales: dominios aceptados para cada una (incluye
     * subdominios). `null` = cualquier dominio público (sitio web).
     */
    public const REDES = [
        'instagram' => ['instagram.com'],
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'tiktok' => ['tiktok.com'],
        'x' => ['x.com', 'twitter.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'linkedin' => ['linkedin.com'],
        'whatsapp' => [],
        'telegram' => ['t.me', 'telegram.me'],
        'pinterest' => ['pinterest.com', 'pinterest.com.mx', 'pin.it'],
        'threads' => ['threads.net', 'threads.com'],
        'spotify' => ['spotify.com'],
        'sitio' => null,
    ];

    public const NOMBRES_REDES = [
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'x' => 'X',
        'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram',
        'pinterest' => 'Pinterest', 'threads' => 'Threads', 'spotify' => 'Spotify', 'sitio' => 'Sitio web',
    ];

    /** Tipografías de fonts.bunny.net (clave = identificador de Bunny). */
    public const FUENTES = [
        'sistema', 'inter', 'dm-sans', 'manrope', 'plus-jakarta-sans', 'outfit', 'poppins', 'sora',
        'space-grotesk', 'work-sans', 'nunito', 'josefin-sans', 'bricolage-grotesque', 'lora',
        'playfair-display', 'fraunces', 'cormorant-garamond', 'libre-baskerville', 'dm-serif-display',
    ];

    public const COLORES = [
        'fondo', 'texto', 'titulos', 'texto_suave', 'enlaces', 'boton_fondo', 'boton_texto', 'boton_borde',
        'boton_hover_fondo', 'boton_hover_texto', 'tarjeta_fondo', 'tarjeta_texto', 'borde', 'acento',
    ];

    public const FONDOS = ['solido', 'degradado', 'imagen', 'textura', 'video'];

    public const TEXTURAS = ['puntos', 'cuadricula', 'lineas', 'diagonales', 'ondas', 'rombos'];

    public const ESTILOS_BOTON = ['solido', 'contorno', 'suave', 'vidrio'];

    public const SOMBRAS = ['ninguna', 'suave', 'media', 'marcada'];

    public const TAMANOS_BOTON = ['compacto', 'normal', 'grande'];

    public const ANIMACIONES = ['ninguna', 'elevar', 'pulso', 'brillo'];

    public const POSICIONES_ICONO = ['izquierda', 'derecha', 'oculto'];

    public const ANCHOS = ['estrecho', 'normal', 'amplio'];

    public const ESPACIADOS = ['compacto', 'normal', 'amplio'];

    public const ENCABEZADOS = ['centrado', 'izquierda', 'portada'];

    public const FORMAS_FOTO = ['circulo', 'redondeada', 'cuadrada'];

    public const PROPORCIONES = ['original', 'cuadrada', 'horizontal', 'vertical'];

    /** Segmentos que no pueden usarse como dirección de una subpágina. */
    public const SUBPAGINAS_RESERVADAS = ['ir', 'contacto', 'qr', 'medios', 'inicio', 'interno', 'build', 'well-known', 'robots', 'favicon', 'sitemap'];

    /**
     * Palabras que ningún negocio puede usar como dirección pública (y
     * cualquier dirección que contenga «dasti»).
     */
    public const SLUGS_RESERVADOS = [
        'admin', 'administrador', 'administracion', 'soporte', 'ayuda', 'help', 'support', 'api', 'www',
        'login', 'logout', 'salir', 'entrar', 'panel', 'registro', 'cuenta', 'cuentas', 'usuario', 'usuarios',
        'pagos', 'pago', 'openpay', 'stripe', 'factura', 'facturacion', 'billing', 'servicios', 'servicio',
        'medios', 'interno', 'static', 'build', 'assets', 'app', 'root', 'sistema', 'seguridad', 'security',
        'oficial', 'verificado', 'verificacion', 'mail', 'correo', 'email', 'ftp', 'cdn', 'null', 'undefined',
        'dashboard', 'inicio', 'contacto', 'ir', 'qr', 'nfc', 'paginas', 'pagina', 'fidelidad', 'mi-fidelidad',
        'legal', 'terminos', 'privacidad', 'aviso-de-privacidad', 'abuse', 'abuso', 'status', 'estado', 'blog',
        'test', 'prueba', 'demo', 'webhook', 'webhooks', 'oauth', 'auth', 'sesion', 'contrasena', 'password',
        'configuracion', 'ajustes', 'settings', 'redirecciones', 'redireccion', 'enlace', 'enlaces', 'links',
        'link', 'tienda', 'shop', 'store', 'whatsapp', 'instagram', 'facebook', 'tiktok', 'google', 'apple',
        'microsoft', 'banco', 'sat', 'gobierno', 'imss', 'policia',
    ];
}
