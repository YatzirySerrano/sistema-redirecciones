<script setup lang="ts">
import { computed } from 'vue';
import { iniciales } from '../tema';

/**
 * Foto del perfil o, si no hay y el tema lo pide, un monograma con las
 * iniciales del negocio (en el color de acento).
 */
const props = defineProps<{
    foto: string | null;
    nombre: string;
    monograma: boolean;
}>();

const letras = computed(() => iniciales(props.nombre));
</script>

<template>
    <img
        v-if="foto"
        :src="foto"
        :alt="nombre"
        class="shrink-0 border-4 border-(--pe-fondo) bg-(--pe-tarjeta-fondo) object-cover shadow-sm"
    />
    <span
        v-else-if="monograma"
        class="pe-monograma pe-titulo flex shrink-0 items-center justify-center border-4 border-(--pe-fondo) shadow-sm select-none"
        aria-hidden="true"
        >{{ letras }}</span
    >
</template>
