<script setup lang="ts">
import { BarChart3, Link2, ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Toaster } from '@/components/ui/sonner';

/**
 * Pantallas de acceso (login, recuperación, 2FA, invitación): una sola
 * tarjeta centrada, con la propuesta de valor a la izquierda en pantallas
 * grandes y solo el formulario en móvil. Usa tokens de color para respetar
 * la personalización visual.
 */
defineProps<{
    title?: string;
    description?: string;
}>();

const beneficios = [
    {
        icono: Link2,
        titulo: 'Enlace permanente',
        texto: 'Cambia el destino sin reimprimir el QR ni reprogramar la tarjeta.',
    },
    {
        icono: BarChart3,
        titulo: 'Estadísticas claras',
        texto: 'Consulta cuántas veces se usan tus productos.',
    },
    {
        icono: ShieldCheck,
        titulo: 'Acceso protegido',
        texto: 'Verificación en dos pasos y registro de cada cambio.',
    },
];
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-background px-4 py-8 sm:px-6 sm:py-12"
    >
        <div
            class="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
            aria-hidden="true"
        />

        <main
            class="relative grid w-full max-w-[25rem] overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-xl shadow-primary/5 lg:max-w-[58rem] lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)]"
        >
            <aside
                class="relative hidden flex-col justify-between gap-10 overflow-hidden bg-sidebar p-9 text-sidebar-foreground lg:flex"
            >
                <div
                    class="pointer-events-none absolute -right-24 -bottom-24 size-72 rounded-full bg-sidebar-primary/25 blur-3xl"
                    aria-hidden="true"
                />
                <div class="relative flex">
                    <AppLogo />
                </div>

                <div class="relative space-y-6">
                    <h2
                        class="text-[1.65rem] leading-tight font-semibold tracking-tight text-sidebar-accent-foreground"
                    >
                        Tus QR y tarjetas NFC, siempre apuntando al lugar
                        correcto.
                    </h2>
                    <ul class="space-y-4">
                        <li
                            v-for="b in beneficios"
                            :key="b.titulo"
                            class="flex gap-3"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-primary"
                                aria-hidden="true"
                            >
                                <component :is="b.icono" class="size-4" />
                            </span>
                            <span class="text-sm leading-snug">
                                <span
                                    class="block font-medium text-sidebar-accent-foreground"
                                    >{{ b.titulo }}</span
                                >
                                {{ b.texto }}
                            </span>
                        </li>
                    </ul>
                </div>

                <p class="relative text-xs text-sidebar-foreground/70">
                    Acceso exclusivo para clientes y personal de DASTI.
                </p>
            </aside>

            <section class="flex flex-col justify-center p-6 sm:p-9 lg:p-11">
                <div class="mb-7 flex lg:hidden">
                    <AppLogo invertido />
                </div>
                <header class="mb-7 space-y-1.5">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </header>
                <slot />
            </section>
        </main>

        <Toaster rich-colors position="top-center" />
    </div>
</template>
