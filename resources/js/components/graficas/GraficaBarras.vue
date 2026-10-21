<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { numero } from '@/lib/formato';
import type { Barra } from '@/types';

/**
 * Barras horizontales de una serie (un solo tono). El valor va en el
 * extremo de cada barra y el porcentaje en el título accesible; con
 * etiquetas de texto, la identidad nunca depende del color.
 */
const props = withDefaults(
    defineProps<{
        barras: Barra[];
        unidad?: string;
        vacio?: string;
    }>(),
    { unidad: 'accesos', vacio: 'Sin datos en este periodo.' },
);

const maximo = computed(() => Math.max(1, ...props.barras.map((b) => b.total)));
const suma = computed(() => props.barras.reduce((s, b) => s + b.total, 0));
const porcentaje = (v: number) =>
    suma.value === 0 ? 0 : Math.round((v / suma.value) * 100);
</script>

<template>
    <p
        v-if="barras.length === 0 || suma === 0"
        class="py-6 text-center text-sm text-muted-foreground"
    >
        {{ vacio }}
    </p>
    <ul v-else class="space-y-3" role="list">
        <li
            v-for="barra in barras"
            :key="barra.clave"
            class="group"
            :title="`${barra.etiqueta}: ${numero(barra.total)} ${unidad} (${porcentaje(barra.total)} %)`"
        >
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate">
                    <component
                        :is="barra.href ? Link : 'span'"
                        :href="barra.href"
                        :class="
                            barra.href
                                ? 'font-medium hover:text-primary hover:underline'
                                : 'font-medium'
                        "
                    >
                        {{ barra.etiqueta }}
                    </component>
                    <span
                        v-if="barra.detalle"
                        class="ml-1.5 text-xs text-muted-foreground"
                        >{{ barra.detalle }}</span
                    >
                </span>
                <span
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    <span class="font-semibold text-foreground">{{
                        numero(barra.total)
                    }}</span>
                    · {{ porcentaje(barra.total) }} %
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-muted" aria-hidden="true">
                <div
                    class="h-2 rounded-full bg-primary transition-[width] duration-500 group-hover:bg-primary/80"
                    :style="{
                        width: `${Math.max(2, (barra.total / maximo) * 100)}%`,
                    }"
                />
            </div>
        </li>
    </ul>
</template>
