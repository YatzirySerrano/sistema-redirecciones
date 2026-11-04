<script setup lang="ts">
import { computed } from 'vue';
import { textoLimite, useLimitesArchivos } from '@/lib/archivos';
import type { TipoLimite } from '@/lib/archivos';

/**
 * Texto junto a cada control de carga: formatos aceptados, tamaño máximo
 * real y (opcional) la recomendación de dimensiones.
 */
const props = withDefaults(
    defineProps<{
        tipo: TipoLimite;
        recomendacion?: string | null;
        sinRecomendacion?: boolean;
    }>(),
    { recomendacion: null, sinRecomendacion: false },
);

const { limite } = useLimitesArchivos();
const datos = computed(() => limite(props.tipo));
</script>

<template>
    <p class="text-xs leading-relaxed text-muted-foreground" data-info-archivo>
        {{ textoLimite(datos) }}
        <template
            v-if="!sinRecomendacion && (recomendacion ?? datos.recomendacion)"
        >
            {{ recomendacion ?? datos.recomendacion }}
        </template>
    </p>
</template>
