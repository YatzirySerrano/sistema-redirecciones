{{-- Página de enlaces pública. Sin marca de DASTI: título, íconos y
     colores son los del negocio. El documento ya viene validado y filtrado
     (DocumentoPagina::paraPublico) y se incrusta como JSON con las
     etiquetas escapadas (@json usa JSON_HEX_TAG). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $titulo }}</title>
    @if ($descripcion !== '')
        <meta name="description" content="{{ $descripcion }}">
    @endif
    <meta name="robots" content="{{ $indexar ? 'index, follow' : 'noindex, nofollow' }}">
    <link rel="canonical" href="{{ $canonica }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $titulo }}">
    @if ($descripcion !== '')
        <meta property="og:description" content="{{ $descripcion }}">
    @endif
    <meta property="og:url" content="{{ $canonica }}">
    <meta property="og:site_name" content="{{ $nombre }}">
    <meta property="og:locale" content="es_MX">
    @if ($imagen)
        <meta property="og:image" content="{{ $imagen }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $imagen }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $titulo }}">
    @if ($descripcion !== '')
        <meta name="twitter:description" content="{{ $descripcion }}">
    @endif
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        {{-- Evita que el navegador pida /favicon.ico (ícono de la plataforma). --}}
        <link rel="icon" href="data:,">
    @endif
    <meta name="theme-color" content="{{ $fondo }}">
    <style nonce="{{ $nonce }}">html, body { margin: 0; background-color: {{ $fondo }}; }</style>
    @if ($fuentes)
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="{{ $fuentes }}" id="pagina-fuentes">
    @endif
    @vite(['resources/css/publico.css', 'resources/js/publico/pagina.ts'])
</head>
<body>
    <div id="pagina-enlaces"></div>
    <noscript>
        <main class="pagina-sin-js">
            <h1>{{ $nombre }}</h1>
            @if ($descripcion !== '')
                <p>{{ $descripcion }}</p>
            @endif
            <ul>
                @foreach ($enlaces as $enlace)
                    <li><a href="{{ $enlace['href'] }}" rel="noopener noreferrer nofollow">{{ $enlace['titulo'] }}</a></li>
                @endforeach
            </ul>
        </main>
    </noscript>
    <script type="application/json" id="pagina-datos">@json($datos)</script>
</body>
</html>
