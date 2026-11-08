<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * Minigráfica de tendencia (sin ejes) para tarjetas de indicadores. Es
 * decorativa: el valor y la comparación van en texto junto a ella.
 */
const props = withDefaults(
    defineProps<{ valores: number[]; color?: string; alto?: number }>(),
    { color: 'var(--primary)', alto: 36 },
);

const id = useId();
const ancho = 120;
const max = computed(() => Math.max(1, ...props.valores));
const puntos = computed(() =>
    props.valores.map((v, i) => [
        props.valores.length <= 1
            ? ancho / 2
            : (i / (props.valores.length - 1)) * ancho,
        props.alto - 3 - (v / max.value) * (props.alto - 6),
    ]),
);
const linea = computed(() =>
    puntos.value
        .map(
            ([x, y], i) =>
                `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`,
        )
        .join(''),
);
const area = computed(() =>
    puntos.value.length < 2
        ? ''
        : `${linea.value}L${ancho},${props.alto}L0,${props.alto}Z`,
);
</script>

<template>
    <svg
        :viewBox="`0 0 ${ancho} ${alto}`"
        preserveAspectRatio="none"
        class="block h-9 w-full overflow-visible"
        aria-hidden="true"
    >
        <defs>
            <linearGradient :id="id" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" :stop-color="color" stop-opacity="0.25" />
                <stop offset="100%" :stop-color="color" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path v-if="area" :d="area" :fill="`url(#${id})`" />
        <path
            :d="linea"
            fill="none"
            :stroke="color"
            stroke-width="1.75"
            stroke-linejoin="round"
            stroke-linecap="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
