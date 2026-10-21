<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import { compacto, diaMes, fechaCorta, numero } from '@/lib/formato';
import type { PuntoSerie } from '@/types';

/**
 * Serie diaria (una sola serie, sin leyenda: el título la nombra).
 * Línea de 2px, relleno al 10 %, cuadrícula de 1px, mira + tooltip al pasar
 * el cursor o al navegar con las flechas, y vista de tabla accesible.
 */
const props = withDefaults(
    defineProps<{
        serie: PuntoSerie[];
        etiqueta?: string;
        alto?: number;
    }>(),
    { etiqueta: 'Accesos', alto: 240 },
);

const contenedor = useTemplateRef<HTMLDivElement>('contenedor');
const { width } = useElementSize(contenedor);
const activo = ref<number | null>(null);
const verTabla = ref(false);

const margen = { arriba: 12, derecha: 12, abajo: 26, izquierda: 40 };
const ancho = computed(() => Math.max(width.value, 280));
const areaAncho = computed(
    () => ancho.value - margen.izquierda - margen.derecha,
);
const areaAlto = computed(() => props.alto - margen.arriba - margen.abajo);

const maximo = computed(() => {
    const max = Math.max(0, ...props.serie.map((p) => p.total));

    if (max <= 4) {
        return 4;
    }

    const paso = Math.pow(10, Math.floor(Math.log10(max)));
    const normal = max / paso;
    const redondo = normal <= 2 ? 2 : normal <= 5 ? 5 : 10;

    return redondo * paso;
});

const marcas = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((f) => Math.round(maximo.value * f)),
);

const x = (i: number) =>
    margen.izquierda +
    (props.serie.length <= 1
        ? areaAncho.value / 2
        : (i / (props.serie.length - 1)) * areaAncho.value);
const y = (v: number) =>
    margen.arriba + areaAlto.value - (v / maximo.value) * areaAlto.value;

const linea = computed(() =>
    props.serie
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.total).toFixed(1)}`,
        )
        .join(''),
);
const area = computed(() =>
    props.serie.length === 0
        ? ''
        : `${linea.value}L${x(props.serie.length - 1).toFixed(1)},${y(0)}L${x(0).toFixed(1)},${y(0)}Z`,
);

const etiquetasX = computed(() => {
    const n = props.serie.length;
    const cantidad = Math.min(n, ancho.value < 480 ? 4 : 7);

    if (cantidad <= 1) {
        return n === 1 ? [0] : [];
    }

    return Array.from({ length: cantidad }, (_, k) =>
        Math.round((k * (n - 1)) / (cantidad - 1)),
    );
});

const total = computed(() => props.serie.reduce((s, p) => s + p.total, 0));

const mover = (evento: PointerEvent) => {
    const rect = (evento.currentTarget as SVGElement).getBoundingClientRect();
    const relativo =
        ((evento.clientX - rect.left) / rect.width) * ancho.value -
        margen.izquierda;
    const n = props.serie.length;

    if (n === 0) {
        return;
    }

    activo.value = Math.min(
        n - 1,
        Math.max(0, Math.round((relativo / areaAncho.value) * (n - 1))),
    );
};

const teclado = (evento: KeyboardEvent) => {
    const n = props.serie.length;

    if (n === 0) {
        return;
    }

    if (evento.key === 'ArrowRight' || evento.key === 'ArrowLeft') {
        evento.preventDefault();
        const actual = activo.value ?? n - 1;
        activo.value = Math.min(
            n - 1,
            Math.max(0, actual + (evento.key === 'ArrowRight' ? 1 : -1)),
        );
    }
};

const punto = computed(() =>
    activo.value === null ? null : props.serie[activo.value],
);
const tooltipIzquierda = computed(() =>
    activo.value === null
        ? 0
        : Math.min(Math.max((x(activo.value) / ancho.value) * 100, 12), 88),
);
</script>

<template>
    <div class="space-y-2">
        <div class="flex justify-end">
            <button
                type="button"
                class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                :aria-pressed="verTabla"
                @click="verTabla = !verTabla"
            >
                {{ verTabla ? 'Ver gráfica' : 'Ver como tabla' }}
            </button>
        </div>

        <div v-if="verTabla" class="max-h-72 overflow-auto rounded-lg border">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    {{
                        etiqueta
                    }}
                    por día
                </caption>
                <thead
                    class="sticky top-0 bg-muted text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2 font-medium">Fecha</th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-right font-medium"
                        >
                            {{ etiqueta }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in [...serie].reverse()"
                        :key="p.fecha"
                        class="border-t"
                    >
                        <td class="px-3 py-1.5">{{ fechaCorta(p.fecha) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">
                            {{ numero(p.total) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            ref="contenedor"
            class="relative w-full select-none"
            :style="{ height: `${alto}px` }"
        >
            <svg
                :viewBox="`0 0 ${ancho} ${alto}`"
                :width="ancho"
                :height="alto"
                class="block max-w-full overflow-visible rounded-md text-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                role="img"
                tabindex="0"
                :aria-label="`${etiqueta} por día: ${numero(total)} en total. Usa las flechas para recorrer los días.`"
                @pointermove="mover"
                @pointerleave="activo = null"
                @keydown="teclado"
                @blur="activo = null"
            >
                <g>
                    <template v-for="m in marcas" :key="m">
                        <line
                            :x1="margen.izquierda"
                            :x2="ancho - margen.derecha"
                            :y1="y(m)"
                            :y2="y(m)"
                            stroke="var(--grid)"
                            stroke-width="1"
                        />
                        <text
                            :x="margen.izquierda - 8"
                            :y="y(m)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            {{ compacto(m) }}
                        </text>
                    </template>
                    <text
                        v-for="i in etiquetasX"
                        :key="`x${i}`"
                        :x="x(i)"
                        :y="alto - 6"
                        :text-anchor="
                            i === 0
                                ? 'start'
                                : i === serie.length - 1
                                  ? 'end'
                                  : 'middle'
                        "
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ diaMes(serie[i].fecha) }}
                    </text>
                </g>
                <path :d="area" fill="currentColor" fill-opacity="0.1" />
                <path
                    :d="linea"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
                <g v-if="punto && activo !== null">
                    <line
                        :x1="x(activo)"
                        :x2="x(activo)"
                        :y1="margen.arriba"
                        :y2="y(0)"
                        stroke="var(--muted-foreground)"
                        stroke-opacity="0.4"
                        stroke-width="1"
                    />
                    <circle
                        :cx="x(activo)"
                        :cy="y(punto.total)"
                        r="5"
                        fill="currentColor"
                        stroke="var(--card)"
                        stroke-width="2"
                    />
                </g>
            </svg>
            <div
                v-if="punto"
                class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                :style="{ left: `${tooltipIzquierda}%` }"
                role="status"
            >
                <p class="text-muted-foreground">
                    {{ fechaCorta(punto.fecha) }}
                </p>
                <p class="mt-0.5 font-semibold text-foreground tabular-nums">
                    {{ numero(punto.total) }} {{ etiqueta.toLowerCase() }}
                </p>
            </div>
        </div>
    </div>
</template>
