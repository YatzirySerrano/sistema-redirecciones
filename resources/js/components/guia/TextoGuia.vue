<script setup lang="ts">
import { computed } from 'vue';

/**
 * Texto de la guía con formato mínimo y seguro (sin v-html):
 * **negritas**, `código` y _énfasis_.
 */
const props = defineProps<{ texto: string }>();

type Segmento = {
    texto: string;
    tipo: 'normal' | 'fuerte' | 'codigo' | 'enfasis';
};

const segmentos = computed<Segmento[]>(() =>
    props.texto
        .split(/(\*\*[^*]+\*\*|`[^`]+`|_[^_]+_)/g)
        .filter((p) => p !== '')
        .map((p) => {
            if (p.startsWith('**') && p.endsWith('**')) {
                return { texto: p.slice(2, -2), tipo: 'fuerte' };
            }

            if (p.startsWith('`') && p.endsWith('`')) {
                return { texto: p.slice(1, -1), tipo: 'codigo' };
            }

            if (p.length > 2 && p.startsWith('_') && p.endsWith('_')) {
                return { texto: p.slice(1, -1), tipo: 'enfasis' };
            }

            return { texto: p, tipo: 'normal' };
        }),
);
</script>

<template>
    <template v-for="(s, i) in segmentos" :key="i">
        <strong
            v-if="s.tipo === 'fuerte'"
            class="font-semibold text-foreground"
            >{{ s.texto }}</strong
        >
        <code
            v-else-if="s.tipo === 'codigo'"
            class="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em] text-foreground"
            >{{ s.texto }}</code
        >
        <em v-else-if="s.tipo === 'enfasis'">{{ s.texto }}</em>
        <template v-else>{{ s.texto }}</template>
    </template>
</template>
