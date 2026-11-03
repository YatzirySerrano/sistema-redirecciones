@extends('fidelidad.layout')

@section('titulo', $titulo)

@section('contenido')
    <section class="rounded-3xl border border-[#e7e5e4] bg-white p-7 text-center shadow-[0_18px_50px_-24px_rgba(28,25,23,0.35)]">
        <h1 class="text-xl font-semibold tracking-tight text-balance">{{ $titulo }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-[#57534e] text-pretty">{{ $mensaje }}</p>
        @if ($recuperar ?? false)
            <a href="{{ route('fidelidad.publico.recuperar') }}" class="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-[#1f3a5f] px-5 text-sm font-medium text-white transition hover:bg-[#18304f] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f3a5f]">Recuperar con mi correo</a>
        @endif
    </section>
@endsection
