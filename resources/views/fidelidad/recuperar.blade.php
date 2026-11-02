@extends('fidelidad.layout')

@section('titulo', 'Recuperar mi enlace')

@section('contenido')
    <section class="rounded-2xl border bg-card p-6 shadow-xs">
        <h1 class="text-xl font-semibold tracking-tight">Recuperar mi enlace</h1>
        @if ($enviado)
            <p class="mt-2 text-sm text-muted-foreground" role="status">Si tu correo está registrado y confirmado en algún programa, te enviamos un enlace. Revisa tu bandeja de entrada (caduca en 60 minutos).</p>
        @else
            <p class="mt-2 text-sm text-muted-foreground">Escribe el correo que confirmaste en el negocio. Te enviaremos un enlace nuevo; el anterior dejará de funcionar.</p>
            <form method="POST" action="{{ route('fidelidad.publico.recuperar.enviar') }}" class="mt-4 space-y-3" novalidate>
                @csrf
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                    class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p id="email-error" class="text-sm text-destructive">{{ $message }}</p>@enderror
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-primary text-sm font-medium text-primary-foreground">Enviar enlace</button>
            </form>
            <p class="mt-4 text-xs text-muted-foreground">¿No registraste correo? Pide ayuda en el negocio: verificarán tu identidad y te darán un enlace nuevo.</p>
        @endif
    </section>
@endsection
