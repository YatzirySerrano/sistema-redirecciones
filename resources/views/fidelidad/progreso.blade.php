@extends('fidelidad.layout')

@section('titulo', $negocio)

@section('contenido')
    <header class="mb-6">
        <p class="text-sm text-muted-foreground">Programa de recompensas</p>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $negocio }}</h1>
        <p class="mt-1 text-sm">Hola, {{ $nombre }}.</p>
    </header>

    @if (count($recompensas) > 0)
        <section class="mb-5 rounded-2xl border border-primary/30 bg-accent p-4 text-accent-foreground" aria-labelledby="recompensas">
            <h2 id="recompensas" class="text-sm font-semibold">Recompensas disponibles</h2>
            <ul class="mt-2 space-y-1.5 text-sm">
                @foreach ($recompensas as $recompensa)
                    <li>🎁 {{ $recompensa['descripcion'] }}@if ($recompensa['vence_en']) <span class="text-xs opacity-80">· vence el {{ $recompensa['vence_en'] }}</span>@endif</li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs opacity-80">Pide tu recompensa en el negocio; el personal la entregará.</p>
        </section>
    @endif

    <section class="space-y-3" aria-label="Tu progreso">
        @forelse ($programas as $programa)
            @php($porcentaje = $programa['meta'] > 0 ? min(100, round($programa['visitas'] * 100 / $programa['meta'])) : 0)
            <article class="rounded-2xl border bg-card p-4 shadow-xs">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="font-semibold">{{ $programa['campana'] }}</h2>
                    <span class="text-sm tabular-nums text-muted-foreground">{{ $programa['visitas'] }} / {{ $programa['meta'] }}</span>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $programa['meta'] }}" aria-valuenow="{{ $programa['visitas'] }}" aria-label="Visitas acumuladas">
                    <div class="h-full rounded-full bg-primary" style="width: {{ $porcentaje }}%"></div>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    @if ($programa['completada'])
                        Completaste este programa.
                    @else
                        Te faltan {{ max(0, $programa['meta'] - $programa['visitas']) }} para: <span class="font-medium text-foreground">{{ $programa['recompensa'] }}</span>
                    @endif
                </p>
            </article>
        @empty
            <p class="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">Aún no tienes visitas registradas. Se sumarán cuando el negocio confirme tu compra o servicio.</p>
        @endforelse
    </section>
@endsection
