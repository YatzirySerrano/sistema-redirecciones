{{-- Metadatos SEO y para compartir (Open Graph / X) de la plataforma.

     Solo la portada pública y el inicio de sesión son indexables; el resto
     (panel, administración, cuentas) lleva noindex. Las Páginas de enlaces
     de los negocios NO usan esta vista (resources/views/paginas): no llevan
     marca de DASTI. --}}
@php
    $seoComponente = (string) ($page['component'] ?? '');
    $seoIndexable = in_array($seoComponente, ['Bienvenida', 'auth/Login'], true);
    $seoBase = rtrim((string) config('app.url'), '/');
    $seoRuta = '/'.ltrim(request()->path(), '/');
    $seoCanonica = $seoBase.($seoRuta === '/' ? '/' : $seoRuta);
    $seoTitulo = match ($seoComponente) {
        'auth/Login' => 'Iniciar sesión · DASTI',
        default => 'DASTI · Redirecciones QR y NFC dinámicas',
    };
    $seoDescripcion = 'DASTI: redirecciones dinámicas para códigos QR y tarjetas NFC. Cambia el destino sin reimprimir, mide los escaneos y suma un programa de fidelidad digital y una página de enlaces para tu negocio.';
    $seoImagen = $seoBase.'/og/dasti-og.png';
@endphp
<meta name="description" content="{{ $seoDescripcion }}">
<meta name="robots" content="{{ $seoIndexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
@if ($seoIndexable)
<meta name="keywords" content="redirecciones dinámicas, códigos QR dinámicos, QR personalizados, NFC, tarjetas NFC, programa de fidelidad digital, página de enlaces, link in bio, estadísticas de escaneos, DASTI">
<link rel="canonical" href="{{ $seoCanonica }}">
@endif
<meta name="author" content="DASTI">
<meta name="application-name" content="DASTI">
<meta property="og:type" content="website">
<meta property="og:site_name" content="DASTI">
<meta property="og:locale" content="es_MX">
<meta property="og:title" content="{{ $seoTitulo }}">
<meta property="og:description" content="{{ $seoDescripcion }}">
<meta property="og:url" content="{{ $seoIndexable ? $seoCanonica : $seoBase.'/' }}">
<meta property="og:image" content="{{ $seoImagen }}">
<meta property="og:image:secure_url" content="{{ $seoImagen }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="DASTI · Redirecciones QR y NFC dinámicas">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitulo }}">
<meta name="twitter:description" content="{{ $seoDescripcion }}">
<meta name="twitter:image" content="{{ $seoImagen }}">
<meta name="twitter:image:alt" content="DASTI · Redirecciones QR y NFC dinámicas">
@if ($seoComponente === 'Bienvenida')
@php
    $seoGrafo = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $seoBase.'/#organizacion',
                'name' => 'DASTI',
                'url' => $seoBase.'/',
                'logo' => $seoBase.'/iconos/icono-512.png',
                'image' => $seoImagen,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $seoBase.'/#sitio',
                'name' => 'DASTI',
                'url' => $seoBase.'/',
                'inLanguage' => 'es-MX',
                'publisher' => ['@id' => $seoBase.'/#organizacion'],
            ],
            [
                '@type' => 'Service',
                'name' => 'Redirecciones QR y NFC dinámicas',
                'serviceType' => 'Códigos QR dinámicos, tarjetas NFC, programa de fidelidad digital y página de enlaces',
                'description' => $seoDescripcion,
                'provider' => ['@id' => $seoBase.'/#organizacion'],
                'areaServed' => ['@type' => 'Country', 'name' => 'México'],
                'url' => $seoBase.'/',
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $p) => [
                    '@type' => 'Question',
                    'name' => $p['pregunta'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p['respuesta']],
                ], \App\Http\Controllers\BienvenidaController::PREGUNTAS),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($seoGrafo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
