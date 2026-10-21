<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';
import type { Opcion } from '@/types';

/**
 * Select nativo con estilo del sistema: accesible por teclado y usable en
 * móvil (abre el selector del sistema operativo).
 */
const modelo = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        opciones: Opcion[];
        vacio?: string | null;
        etiqueta?: string;
        id?: string;
        name?: string;
        class?: string;
        invalido?: boolean;
        disabled?: boolean;
    }>(),
    { vacio: null },
);
</script>

<template>
    <div :class="cn('relative', $props.class)">
        <select
            :id="id"
            v-model="modelo"
            :name="name"
            :aria-label="etiqueta"
            :aria-invalid="invalido || undefined"
            :disabled="disabled"
            class="h-9 w-full appearance-none truncate rounded-lg border border-input bg-background pr-8 pl-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive"
        >
            <option v-if="vacio !== null" value="">{{ vacio }}</option>
            <option
                v-for="opcion in opciones"
                :key="opcion.value"
                :value="opcion.value"
            >
                {{ opcion.label }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
    </div>
</template>
