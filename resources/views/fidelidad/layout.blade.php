<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
        <meta name="color-scheme" content="light">
        <title>@yield('titulo') · Mi tarjeta de recompensas</title>
        @stack('cabecera')
        @vite(['resources/css/publico.css'])
    </head>
    <body class="min-h-screen bg-[#f5f1ea] text-[#1c1917] antialiased [font-family:ui-sans-serif,system-ui,-apple-system,'Segoe_UI',Roboto,sans-serif]">
        @hasSection('cuerpo')
            @yield('cuerpo')
        @else
            <main class="mx-auto flex min-h-screen w-full max-w-md flex-col px-5 py-10 sm:py-16">
                <div class="mb-6 flex justify-center" aria-hidden="true">
                    <span class="inline-flex size-14 items-center justify-center rounded-2xl bg-[#1f3a5f] text-[#f5f1ea] shadow-lg shadow-[#1f3a5f]/20">
                        <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/><path d="M7 15h3"/></svg>
                    </span>
                </div>
                @yield('contenido')
                <p class="mt-auto pt-12 text-center text-xs leading-relaxed text-[#78716c]">
                    Esta página es solo de consulta: las visitas las registra el negocio al atenderte.
                </p>
            </main>
        @endif
    </body>
</html>
