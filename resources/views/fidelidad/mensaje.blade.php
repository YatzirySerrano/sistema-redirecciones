@extends('fidelidad.layout')

@section('titulo', $titulo)

@section('contenido')
    <section class="rounded-2xl border bg-card p-6 shadow-xs">
        <h1 class="text-xl font-semibold tracking-tight">{{ $titulo }}</h1>
        <p class="mt-2 text-sm text-muted-foreground">{{ $mensaje }}</p>
        @if ($recuperar ?? false)
            <a href="{{ route('fidelidad.publico.recuperar') }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground">Recuperar con mi correo</a>
        @endif
    </section>
@endsection
