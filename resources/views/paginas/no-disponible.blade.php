<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Página no disponible</title>
    <style>
        :root { color-scheme: light dark; --bg: #f5f5f4; --card: #ffffff; --text: #1c1917; --muted: #78716c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0a09; --card: #1c1917; --text: #f5f5f4; --muted: #a8a29e; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--text); font: 16px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 400px; background: var(--card); border-radius: 20px; padding: 40px 28px; text-align: center; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 12px 32px -12px rgb(0 0 0 / .12); }
        h1 { font-size: 1.2rem; margin: 0 0 8px; letter-spacing: -0.01em; }
        p { margin: 0; color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
    <main role="main">
        <h1>Esta página no está disponible</h1>
        <p>
            @if ($estado === 429)
                Recibimos demasiadas solicitudes desde tu conexión. Espera un minuto e inténtalo de nuevo.
            @elseif ($estado === 410)
                La página existe, pero en este momento no está disponible. Inténtalo más tarde.
            @else
                No encontramos la página que buscas. Revisa la dirección e inténtalo de nuevo.
            @endif
        </p>
    </main>
</body>
</html>
