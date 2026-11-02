<script setup lang="ts">
import { Info } from '@lucide/vue';
import { ref } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

/**
 * Ayuda contextual: icono de información que muestra una explicación breve.
 *
 * Funciona con cursor (al pasar por encima), con teclado (Intro/Espacio) y
 * en pantallas táctiles (al tocar), porque se basa en un Popover y no solo
 * en hover. El texto también es el nombre accesible del botón.
 */
const props = withDefaults(
    defineProps<{
        texto: string;
        etiqueta?: string;
        lado?: 'top' | 'bottom' | 'left' | 'right';
    }>(),
    { etiqueta: 'Más información', lado: 'top' },
);

const abierto = ref(false);
let temporizador: ReturnType<typeof setTimeout> | undefined;

const alEntrar = (e: PointerEvent) => {
    if (e.pointerType !== 'mouse') {
        return;
    }

    clearTimeout(temporizador);
    abierto.value = true;
};

const alSalir = (e: PointerEvent) => {
    if (e.pointerType !== 'mouse') {
        return;
    }

    temporizador = setTimeout(() => (abierto.value = false), 120);
};
</script>

<template>
    <Popover v-model:open="abierto">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="inline-flex size-5 shrink-0 items-center justify-center rounded-full align-middle text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                :aria-label="`${props.etiqueta}: ${props.texto}`"
                @pointerenter="alEntrar"
                @pointerleave="alSalir"
            >
                <Info class="size-3.5" aria-hidden="true" />
            </button>
        </PopoverTrigger>
        <PopoverContent
            :side="lado"
            class="w-64 p-3 text-xs leading-relaxed"
            role="tooltip"
            @pointerenter="alEntrar"
            @pointerleave="alSalir"
            @open-auto-focus.prevent
        >
            {{ texto }}
        </PopoverContent>
    </Popover>
</template>
