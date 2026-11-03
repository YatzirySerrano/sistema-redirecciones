@extends('fidelidad.layout')

@section('titulo', $titulo)

@push('cabecera')
    @if ($fuentes)
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="{{ $fuentes }}">
    @endif
@endpush

@php
    $pagina = $datos['disenos']['general']['pagina'];
    $colores = $datos['disenos']['general']['colores'];
@endphp

@section('cuerpo')
    {{-- Se reemplaza al montar la tarjeta (resources/js/publico/fidelidad.ts). --}}
    <div id="tarjeta-fidelidad" style="background-color: {{ $pagina['color1'] }}; color: {{ $colores['texto'] }};" class="min-h-screen">
        <div class="mx-auto flex min-h-screen max-w-md items-center justify-center px-6" aria-hidden="true">
            <div class="h-64 w-full animate-pulse rounded-3xl bg-black/5"></div>
        </div>
    </div>

    <noscript>
        <style>#tarjeta-fidelidad { display: none; }</style>
        <main class="mx-auto min-h-screen w-full max-w-md px-5 py-10" style="background-color: {{ $pagina['color1'] }}; color: {{ $colores['texto'] }};">
            <p class="text-sm opacity-75">{{ $datos['disenos']['general']['programa']['nombre'] }}</p>
            <h1 class="text-2xl font-semibold">{{ $datos['negocio'] }}</h1>
            @if ($datos['disenos']['general']['contenido']['saludo'] === 'alias')
                <p class="mt-1">Hola, {{ $datos['consumidor'] }}.</p>
            @endif
            @if ($datos['aviso'])
                <p class="mt-4 rounded-xl border border-current/20 p-3 text-sm">{{ $datos['aviso'] }}</p>
            @endif
            <ul class="mt-6 space-y-4">
                @forelse ($datos['programas'] as $programa)
                    <li class="rounded-2xl border border-current/15 p-4">
                        <h2 class="font-semibold">{{ $programa['campana'] }}</h2>
                        <p class="mt-1">Llevas {{ $programa['visitas'] }} de {{ $programa['meta'] }} visitas.</p>
                        @if ($programa['estado'] === 'disponible')
                            <p class="mt-1 font-medium">Recompensa disponible: {{ $programa['recompensas'][0]['descripcion'] ?? $programa['recompensa'] }}</p>
                        @elseif ($programa['estado'] === 'canjeada')
                            <p class="mt-1">Tu última recompensa ya fue canjeada. Te faltan {{ $programa['faltan'] }} para: {{ $programa['recompensa'] }}</p>
                        @elseif ($programa['estado'] === 'completada')
                            <p class="mt-1">Completaste este programa.</p>
                        @else
                            <p class="mt-1">Te faltan {{ $programa['faltan'] }} para: {{ $programa['recompensa'] }}</p>
                        @endif
                    </li>
                @empty
                    <li>Aún no tienes visitas registradas. Se sumarán cuando el negocio confirme tu compra o servicio.</li>
                @endforelse
            </ul>
            <p class="mt-10 text-xs opacity-70">Esta página es solo de consulta: las visitas las registra el negocio al atenderte.</p>
        </main>
    </noscript>

    <script type="application/json" id="datos-fidelidad">@json($datos)</script>
    @vite(['resources/js/publico/fidelidad.ts'])
@endsection
