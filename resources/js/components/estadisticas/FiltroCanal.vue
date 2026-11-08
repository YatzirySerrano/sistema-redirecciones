<script setup lang="ts">
import { computed } from 'vue';
import type { Canal } from '@/lib/graficas';
import { CANALES, ORDEN_CANALES } from '@/lib/graficas';
import { cn } from '@/lib/utils';

/**
 * Filtro por canal (todos, NFC, QR o QR + NFC compartido). Solo ofrece los
 * canales que existen en los productos consultados.
 */
const modelo = defineModel<string>({ required: true });
const props = defineProps<{ disponibles?: string[] }>();

const opciones = computed(() => [
    { clave: '', etiqueta: 'Todos', color: null as string | null },
    ...ORDEN_CANALES.filter(
        (c: Canal) =>
            !props.disponibles ||
            props.disponibles.includes(c) ||
            modelo.value === c,
    ).map((c: Canal) => ({
        clave: c,
        etiqueta: CANALES[c].etiqueta,
        color: CANALES[c].color,
    })),
]);
</script>

<template>
    <div
        v-if="opciones.length > 2"
        class="inline-flex shrink-0 rounded-lg border bg-background p-0.5"
        role="group"
        aria-label="Filtrar por canal"
    >
        <button
            v-for="o in opciones"
            :key="o.clave"
            type="button"
            :aria-pressed="modelo === o.clave"
            :class="
                cn(
                    'inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium whitespace-nowrap text-muted-foreground transition hover:text-foreground',
                    modelo === o.clave && 'bg-muted text-foreground shadow-xs',
                )
            "
            @click="modelo = o.clave"
        >
            <span
                v-if="o.color"
                class="size-2 rounded-full"
                :style="{ background: o.color }"
                aria-hidden="true"
            />
            {{ o.etiqueta }}
        </button>
    </div>
</template>
