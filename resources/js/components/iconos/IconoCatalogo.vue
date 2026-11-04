<script setup lang="ts">
import { computed, h, watchEffect } from 'vue';
import type { FunctionalComponent } from 'vue';
import { PREFIJO, cargarCatalogo, catalogo } from './catalogo';
import type { NodoIcono } from './catalogo';
import { usarIconos } from './contexto';
import { componenteHeredado } from './heredados';

/**
 * Dibuja cualquier valor de ícono guardado: `i:<nombre>` (biblioteca),
 * claves heredadas de Página de enlaces/Fidelidad y `red:<red>`. Los
 * atributos (class, style, x, y, width, height…) pasan al <svg>.
 *
 * En páginas públicas los trazos vienen del servidor (proveerIconos); en
 * los editores se descarga el catálogo si hace falta.
 */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        valor: string | null | undefined;
        color?: string;
        strokeWidth?: number | string;
    }>(),
    { color: 'currentColor', strokeWidth: 2 },
);

const contexto = usarIconos();

const ETIQUETAS = new Set([
    'path',
    'circle',
    'rect',
    'line',
    'polyline',
    'polygon',
    'ellipse',
]);
const ATRIBUTOS = new Set([
    'd',
    'cx',
    'cy',
    'r',
    'rx',
    'ry',
    'x',
    'y',
    'x1',
    'y1',
    'x2',
    'y2',
    'width',
    'height',
    'points',
    'fill',
]);

const esBiblioteca = computed(
    () => !!props.valor && props.valor.startsWith(PREFIJO),
);

const heredado = computed(() =>
    props.valor && !esBiblioteca.value ? componenteHeredado(props.valor) : null,
);

const nodos = computed<NodoIcono[] | null>(() => {
    const valor = props.valor;

    if (!valor || !esBiblioteca.value) {
        return null;
    }

    return (
        contexto.mapa[valor] ??
        catalogo.value?.iconos[valor.slice(PREFIJO.length)]?.nodos ??
        null
    );
});

watchEffect(() => {
    if (
        esBiblioteca.value &&
        !nodos.value &&
        contexto.cargarFaltantes &&
        !catalogo.value
    ) {
        void cargarCatalogo().catch(() => undefined);
    }
});

/** Solo etiquetas y atributos geométricos: nada ejecutable. */
const Trazos: FunctionalComponent<{ nodos: NodoIcono[] }> = (p) =>
    p.nodos
        .filter(([etiqueta]) => ETIQUETAS.has(etiqueta))
        .map(([etiqueta, atributos]) =>
            h(
                etiqueta,
                Object.fromEntries(
                    Object.entries(atributos).filter(
                        ([nombre, valor]) =>
                            ATRIBUTOS.has(nombre) &&
                            (nombre !== 'fill' ||
                                valor === 'currentColor' ||
                                valor === 'none'),
                    ),
                ),
            ),
        );
</script>

<template>
    <component
        :is="heredado"
        v-if="heredado"
        v-bind="$attrs"
        :color="color"
        :stroke-width="strokeWidth"
        aria-hidden="true"
    />
    <svg
        v-else-if="esBiblioteca"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        :stroke="color"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-bind="$attrs"
    >
        <Trazos v-if="nodos" :nodos="nodos" />
    </svg>
</template>
