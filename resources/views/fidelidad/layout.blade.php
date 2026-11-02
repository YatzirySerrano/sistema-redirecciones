<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
        <title>@yield('titulo') · Programa de recompensas</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8 sm:py-12">
            @yield('contenido')
            <p class="mt-auto pt-10 text-center text-xs text-muted-foreground">
                Programa de recompensas operado con <span class="font-semibold tracking-[0.18em]">DASTI</span>.
                Esta página es solo de consulta: las visitas las registra el negocio al atenderte.
            </p>
        </main>
    </body>
</html>
