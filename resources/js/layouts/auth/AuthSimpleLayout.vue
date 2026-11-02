<script setup lang="ts">
import { BarChart3, Link2, Nfc, QrCode, ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import IndicadorConexion from '@/components/comun/IndicadorConexion.vue';
import { Toaster } from '@/components/ui/sonner';

/**
 * Pantallas de acceso (login, recuperación, 2FA, invitación) a altura
 * completa:
 *
 * - Escritorio: panel de marca a la izquierda (qué es la plataforma y una
 *   representación del flujo enlace → destino) y el formulario centrado en
 *   su columna, con anchura cómoda de lectura.
 * - Móvil y tableta vertical: franja de marca compacta y el formulario.
 *
 * Solo usa tokens de color, para respetar la personalización visual. La
 * identidad es tipográfica: no existe un logotipo oficial de DASTI.
 */
defineProps<{
    title?: string;
    description?: string;
}>();

const beneficios = [
    {
        icono: Link2,
        titulo: 'Un enlace que no cambia',
        texto: 'Actualiza el destino sin reimprimir el QR ni reprogramar la tarjeta.',
    },
    {
        icono: BarChart3,
        titulo: 'Uso real de tus productos',
        texto: 'Accesos por día, dispositivo y producto.',
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
        class="grid min-h-dvh grid-rows-1 bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]"
    >
        <!-- Marca (escritorio) -->
        <aside
            class="relative hidden overflow-hidden bg-sidebar text-sidebar-foreground lg:flex lg:flex-col"
        >
            <div
                class="pointer-events-none absolute -top-32 -left-24 size-[28rem] rounded-full bg-sidebar-primary/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-40 bottom-0 size-[30rem] rounded-full bg-sidebar-primary/15 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] [background-size:44px_44px] opacity-[0.07]"
                aria-hidden="true"
            />

            <div
                class="relative flex flex-1 flex-col justify-between gap-12 px-10 py-10 xl:px-16 xl:py-14"
            >
                <div class="flex"><AppLogo /></div>

                <div class="max-w-lg space-y-9">
                    <div class="space-y-4">
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-sidebar-primary uppercase"
                        >
                            Plataforma de enlaces dinámicos
                        </p>
                        <h2
                            class="text-3xl leading-[1.15] font-semibold tracking-tight text-balance text-sidebar-accent-foreground xl:text-[2.6rem]"
                        >
                            Tus QR y tarjetas NFC, siempre apuntando al lugar
                            correcto.
                        </h2>
                    </div>

                    <!-- Representación del flujo: medio físico → enlace → destino -->
                    <div
                        class="flex items-center gap-3 rounded-2xl border border-sidebar-border bg-sidebar-accent/60 p-4 backdrop-blur"
                        aria-hidden="true"
                    >
                        <div class="flex -space-x-2">
                            <span
                                class="flex size-10 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground ring-4 ring-sidebar"
                            >
                                <Nfc class="size-5" />
                            </span>
                            <span
                                class="flex size-10 items-center justify-center rounded-xl bg-sidebar-accent-foreground text-sidebar ring-4 ring-sidebar"
                            >
                                <QrCode class="size-5" />
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-mono text-xs text-sidebar-accent-foreground"
                            >
                                redireccion.dasti.cloud/r/abc123
                            </p>
                            <div
                                class="mt-1.5 h-1 overflow-hidden rounded-full bg-sidebar-border"
                            >
                                <div
                                    class="h-full w-2/3 rounded-full bg-sidebar-primary motion-safe:animate-pulse"
                                />
                            </div>
                        </div>
                        <span
                            class="rounded-lg bg-sidebar px-2.5 py-1.5 text-xs font-medium text-sidebar-accent-foreground"
                            >instagram.com</span
                        >
                    </div>

                    <ul class="grid gap-5">
                        <li
                            v-for="b in beneficios"
                            :key="b.titulo"
                            class="flex gap-3.5"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-primary ring-1 ring-sidebar-border"
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

                <p class="text-xs text-sidebar-foreground/70">
                    Acceso exclusivo para clientes y personal de DASTI.
                </p>
            </div>
        </aside>

        <!-- Formulario -->
        <div class="flex min-w-0 flex-col">
            <div
                class="relative overflow-hidden bg-sidebar px-5 pt-7 pb-16 text-sidebar-foreground sm:px-8 lg:hidden"
            >
                <div
                    class="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-sidebar-primary/25 blur-3xl"
                    aria-hidden="true"
                />
                <div class="relative">
                    <div class="flex"><AppLogo /></div>
                    <p
                        class="mt-5 max-w-sm text-lg leading-snug font-semibold text-sidebar-accent-foreground"
                    >
                        Tus QR y tarjetas NFC, siempre apuntando al lugar
                        correcto.
                    </p>
                </div>
            </div>

            <main
                class="relative z-10 -mt-10 flex flex-1 items-start justify-center px-4 pb-10 sm:px-8 lg:mt-0 lg:items-center lg:px-12 lg:py-12"
            >
                <div
                    class="w-full max-w-[26rem] rounded-2xl border bg-card p-6 text-card-foreground shadow-lg shadow-black/5 sm:p-8 lg:max-w-[28rem] lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none"
                >
                    <header class="mb-7 space-y-2">
                        <h1
                            class="text-2xl font-semibold tracking-tight lg:text-[1.75rem]"
                        >
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
                </div>
            </main>

            <footer
                class="px-6 pb-6 text-center text-xs text-muted-foreground lg:px-12 lg:text-left"
            >
                © DASTI · Plataforma de redirecciones QR y NFC
            </footer>
        </div>
    </div>
    <IndicadorConexion class="fixed inset-x-0 top-0" />
    <Toaster rich-colors position="top-center" />
</template>
