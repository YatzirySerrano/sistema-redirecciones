<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Enlace no disponible</title>
    <style>
        :root { color-scheme: light dark; --bg: #f7f7fb; --card: #ffffff; --text: #18181b; --muted: #6b6b76; --accent: #6d28d9; --ring: #ede9fe; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0e0d13; --card: #17161f; --text: #f4f4f5; --muted: #a1a1aa; --accent: #a78bfa; --ring: #2e1065; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--text); font: 16px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 420px; background: var(--card); border-radius: 20px; padding: 40px 32px; text-align: center; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 12px 32px -12px rgb(0 0 0 / .12); }
        .icono { width: 56px; height: 56px; margin: 0 auto 20px; border-radius: 16px; display: grid; place-items: center; background: var(--ring); color: var(--accent); }
        h1 { font-size: 1.25rem; margin: 0 0 8px; letter-spacing: -0.01em; }
        p { margin: 0; color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
    <main role="main">
        <div class="icono" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" x2="12" y1="12" y2="12"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
        </div>
        <h1>Este enlace no está disponible</h1>
        <p>
            @if ($estado === 410)
                El enlace existe, pero en este momento se encuentra inactivo. Inténtalo más tarde.
            @elseif ($estado === 429)
                Recibimos demasiadas solicitudes desde tu conexión. Espera un minuto e inténtalo de nuevo.
            @else
                No encontramos el destino de este enlace. Verifica el código QR o la tarjeta e inténtalo de nuevo.
            @endif
        </p>
    </main>
</body>
</html>
