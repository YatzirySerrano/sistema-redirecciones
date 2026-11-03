<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Identidad de DASTI en el panel (sidebar, inicio de sesión).
 *
 * - `completa`: logotipo completo (o, mientras no exista el oficial, el
 *   recurso neutral temporal + el nombre en texto).
 * - `compacta`: solo el isotipo cuadrado, centrado (sidebar colapsado).
 * - `auto`: completa, y compacta cuando el sidebar está colapsado.
 *
 * El logotipo oficial se detecta en `public/marca/` (ver
 * App\Services\Apariencia\MarcaDasti). Las imágenes usan `object-contain`
 * dentro de una caja de tamaño fijo: nunca se estiran ni se recortan.
 * `superficie`: «oscura» (sidebar, panel de marca) o «tema» (sigue el modo
 * claro/oscuro).
 */
const props = withDefaults(
    defineProps<{
        variante?: 'completa' | 'compacta' | 'auto';
        superficie?: 'oscura' | 'tema';
        subtitulo?: string | null;
    }>(),
    {
        variante: 'auto',
        superficie: 'oscura',
        subtitulo: 'Redirecciones QR · NFC',
    },
);

const page = usePage();
const marca = computed(() => page.props.marca);

// En superficie oscura se prefiere la versión para fondos oscuros.
const logo = computed(() =>
    props.superficie === 'oscura'
        ? (marca.value?.logo_oscuro ?? marca.value?.logo ?? null)
        : (marca.value?.logo ?? null),
);
const logoOscuro = computed(() =>
    props.superficie === 'tema' ? (marca.value?.logo_oscuro ?? null) : null,
);
const isotipo = computed(() =>
    props.superficie === 'oscura'
        ? (marca.value?.isotipo_oscuro ?? marca.value?.isotipo ?? null)
        : (marca.value?.isotipo ?? null),
);

const muestraCompacta = computed(() => props.variante !== 'completa');
const muestraCompleta = computed(() => props.variante !== 'compacta');
const auto = computed(() => props.variante === 'auto');
const textoClaro = computed(() => props.superficie === 'oscura');
</script>

<template>
    <span
        class="flex min-w-0 items-center"
        :class="
            auto
                ? 'gap-2.5 group-data-[collapsible=icon]:w-full group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0'
                : 'gap-2.5'
        "
    >
        <!-- Isotipo (compacto). Con logotipo completo oficial, solo se ve
             al colapsar; con el recurso temporal, acompaña al nombre. -->
        <span
            v-if="muestraCompacta"
            class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg"
            :class="[
                auto && logo ? 'hidden group-data-[collapsible=icon]:flex' : '',
                !isotipo && !logo
                    ? textoClaro
                        ? 'bg-white/10 ring-1 ring-white/15'
                        : 'bg-primary/10 ring-1 ring-primary/15'
                    : '',
            ]"
        >
            <img
                v-if="isotipo || logo"
                :src="(isotipo ?? logo) as string"
                alt="DASTI"
                class="size-full object-contain"
                width="32"
                height="32"
                decoding="async"
            />
            <!-- Recurso neutral TEMPORAL (no es el logotipo de DASTI). -->
            <svg
                v-else
                viewBox="0 0 32 32"
                class="size-5"
                :class="textoClaro ? 'text-sidebar-primary' : 'text-primary'"
                role="img"
                aria-label="DASTI"
                data-marca-temporal
            >
                <g
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    opacity="0.95"
                >
                    <rect x="3" y="3" width="10" height="10" rx="2.6" />
                    <rect x="19" y="3" width="10" height="10" rx="2.6" />
                    <rect x="3" y="19" width="10" height="10" rx="2.6" />
                </g>
                <g fill="currentColor">
                    <rect x="19" y="19" width="4" height="4" rx="1" />
                    <rect x="25" y="25" width="4" height="4" rx="1" />
                    <rect
                        x="25"
                        y="19"
                        width="4"
                        height="4"
                        rx="1"
                        opacity="0.5"
                    />
                    <rect
                        x="19"
                        y="25"
                        width="4"
                        height="4"
                        rx="1"
                        opacity="0.5"
                    />
                </g>
            </svg>
        </span>

        <!-- Versión completa -->
        <span
            v-if="muestraCompleta"
            class="flex min-w-0 flex-1 flex-col justify-center leading-none"
            :class="auto ? 'group-data-[collapsible=icon]:hidden' : ''"
        >
            <template v-if="logo">
                <img
                    :src="logo"
                    alt="DASTI"
                    class="h-7 w-auto max-w-40 object-contain object-left"
                    :class="logoOscuro ? 'dark:hidden' : ''"
                    decoding="async"
                />
                <img
                    v-if="logoOscuro"
                    :src="logoOscuro"
                    alt="DASTI"
                    class="hidden h-7 w-auto max-w-40 object-contain object-left dark:block"
                    decoding="async"
                />
            </template>
            <span
                v-else
                class="truncate text-[15px] font-extrabold tracking-[0.22em]"
                :class="
                    textoClaro
                        ? 'text-sidebar-accent-foreground'
                        : 'text-foreground'
                "
                >DASTI</span
            >
            <span
                v-if="subtitulo"
                class="mt-1 truncate text-[11px] font-medium tracking-wide"
                :class="
                    textoClaro
                        ? 'text-sidebar-foreground'
                        : 'text-muted-foreground'
                "
                >{{ subtitulo }}</span
            >
        </span>
    </span>
</template>
