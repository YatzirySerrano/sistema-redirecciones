@extends('fidelidad.layout')

@section('titulo', 'Recuperar mi enlace')

@section('contenido')
    <section class="rounded-3xl border border-[#e7e5e4] bg-white p-7 shadow-[0_18px_50px_-24px_rgba(28,25,23,0.35)]">
        <h1 class="text-center text-xl font-semibold tracking-tight">Recuperar mi enlace</h1>
        @if ($enviado)
            <p class="mt-3 text-center text-sm leading-relaxed text-[#57534e]" role="status">Si tu correo está registrado y confirmado en algún programa, te enviamos un enlace. Revisa tu bandeja de entrada (caduca en 60 minutos).</p>
        @else
            <p class="mt-3 text-center text-sm leading-relaxed text-[#57534e]">Escribe el correo que confirmaste en el negocio. Te enviaremos un enlace nuevo; el anterior dejará de funcionar.</p>
            <form method="POST" action="{{ route('fidelidad.publico.recuperar.enviar') }}" class="mt-6 space-y-3" novalidate>
                @csrf
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                    class="h-12 w-full rounded-xl border border-[#d6d3d1] bg-white px-4 text-base outline-none transition focus-visible:border-[#1f3a5f] focus-visible:ring-4 focus-visible:ring-[#1f3a5f]/15"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p id="email-error" class="text-sm text-[#b91c1c]">{{ $message }}</p>@enderror
                <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#1f3a5f] text-sm font-medium text-white transition hover:bg-[#18304f] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f3a5f]">Enviar enlace</button>
            </form>
            <p class="mt-5 text-center text-xs leading-relaxed text-[#78716c]">¿No registraste correo? Pide ayuda en el negocio: verificarán tu identidad y te darán un enlace nuevo.</p>
        @endif
    </section>
@endsection
