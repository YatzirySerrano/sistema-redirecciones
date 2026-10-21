<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-sidebar p-10 text-sidebar-foreground lg:flex"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-sidebar-primary/25 blur-3xl"
                aria-hidden="true"
            />
            <div class="relative flex items-center gap-3">
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-sidebar-primary font-bold text-sidebar-primary-foreground"
                    aria-hidden="true"
                    >D</span
                >
                <span class="text-sm font-bold tracking-[0.2em] text-white"
                    >DASTI</span
                >
            </div>
            <div class="relative max-w-md space-y-4">
                <h2 class="text-3xl leading-tight font-semibold text-white">
                    Tus QR y tarjetas NFC, siempre apuntando al lugar correcto.
                </h2>
                <p class="text-sm leading-relaxed">
                    Cambia el destino de tus productos cuando lo necesites, sin
                    volver a imprimir el código ni reprogramar la tarjeta.
                </p>
            </div>
            <p class="relative text-xs text-sidebar-foreground/60">
                Acceso exclusivo para clientes de DASTI.
            </p>
        </aside>

        <div
            class="flex flex-col items-center justify-center bg-background px-6 py-12 sm:px-10"
        >
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground"
                        aria-hidden="true"
                        >D</span
                    >
                    <span class="text-sm font-bold tracking-[0.2em]"
                        >DASTI</span
                    >
                </div>
                <div class="mb-8 space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
        <Toaster rich-colors position="top-center" />
    </div>
</template>
