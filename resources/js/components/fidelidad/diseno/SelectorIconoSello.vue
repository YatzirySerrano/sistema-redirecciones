<script setup lang="ts">
import type { ClaveIcono } from '@/components/fidelidad-diseno/tipos';
import SelectorIconoCatalogo from '@/components/iconos/SelectorIconoCatalogo.vue';

/**
 * ÚNICO lugar donde el editor elige el ícono de los sellos. Usa la
 * biblioteca compartida de íconos (valores `i:<nombre>` o claves heredadas;
 * el servidor valida que existan). Un sello siempre lleva ícono: no se
 * ofrece «ninguno».
 */
const modelo = defineModel<ClaveIcono>({ required: true });

defineProps<{ etiqueta: string; color?: string }>();

const elegir = (valor: string | null) => {
    if (valor) {
        modelo.value = valor as ClaveIcono;
    }
};
</script>

<template>
    <SelectorIconoCatalogo
        :model-value="modelo"
        :etiqueta="etiqueta"
        :permitir-ninguno="false"
        :incluir-redes="false"
        :color-base="color"
        @update:model-value="elegir"
    />
</template>
