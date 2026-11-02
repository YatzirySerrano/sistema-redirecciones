<script setup lang="ts">
import { LayoutGrid, Rows3 } from '@lucide/vue';
import type { Vista } from '@/composables/useVistaListado';
import { cn } from '@/lib/utils';

/**
 * Control «Tabla | Tarjetas» de los listados.
 */
const vista = defineModel<Vista>({ required: true });

const opciones: { valor: Vista; etiqueta: string; icono: typeof Rows3 }[] = [
    { valor: 'tabla', etiqueta: 'Tabla', icono: Rows3 },
    { valor: 'tarjetas', etiqueta: 'Tarjetas', icono: LayoutGrid },
];
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Vista del listado"
        class="inline-flex h-9 shrink-0 items-center rounded-lg border bg-muted/50 p-0.5"
    >
        <button
            v-for="opcion in opciones"
            :key="opcion.valor"
            type="button"
            role="radio"
            :aria-checked="vista === opcion.valor"
            :class="
                cn(
                    'inline-flex h-full items-center gap-1.5 rounded-md px-2.5 text-xs font-medium text-muted-foreground transition outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30',
                    vista === opcion.valor &&
                        'bg-card text-foreground shadow-xs',
                )
            "
            @click="vista = opcion.valor"
        >
            <component :is="opcion.icono" class="size-4" aria-hidden="true" />
            {{ opcion.etiqueta }}
        </button>
    </div>
</template>
