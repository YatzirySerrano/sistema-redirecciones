<script setup lang="ts">
import { computed } from 'vue';
import { segmentosTexto } from './contenido';

/**
 * Texto con **negritas**, _cursivas_ y saltos de línea. Solo interpolación
 * de Vue (siempre escapada): nunca v-html.
 */
const props = defineProps<{ texto: string }>();

const lineas = computed(() => segmentosTexto(props.texto));
</script>

<template>
    <template v-for="(linea, i) in lineas" :key="i">
        <br v-if="i > 0" />
        <template v-for="(s, j) in linea" :key="j">
            <strong v-if="s.negrita" class="font-semibold">{{
                s.texto
            }}</strong>
            <em v-else-if="s.cursiva">{{ s.texto }}</em>
            <template v-else>{{ s.texto }}</template>
        </template>
    </template>
</template>
