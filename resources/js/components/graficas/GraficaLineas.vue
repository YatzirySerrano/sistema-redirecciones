<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useId, useTemplateRef } from 'vue';
import { compacto, numero } from '@/lib/formato';
import { maximoRedondo } from '@/lib/graficas';

/**
 * Líneas (una o varias series) sobre los mismos puntos del eje X.
 *
 * - La primera serie con `area` lleva un relleno degradado; las series
 *   `discontinua` (p. ej. el periodo anterior) se dibujan punteadas.
 * - Mira y tooltip con todas las series al pasar el cursor, al tocar o con
 *   las flechas del teclado; alternativa en tabla accesible.
 * - Los puntos `parcial` (semana o mes incompleto) se señalan en el
 *   tooltip para no compararlos como si estuvieran completos.
 */
export type SerieLinea = {
    clave: string;
    nombre: string;
    color: string;
    valores: number[];
    area?: boolean;
    discontinua?: boolean;
};

const props = withDefaults(
    defineProps<{
        puntos: {
            clave: string;
            eje: string;
            titulo: string;
            parcial?: boolean;
        }[];
        series: SerieLinea[];
        alto?: number;
        unidad?: string;
        etiquetaTabla?: string;
    }>(),
    { alto: 260, unidad: 'interacciones', etiquetaTabla: 'Periodo' },
);

const id = useId();
const contenedor = useTemplateRef<HTMLDivElement>('contenedor');
const { width } = useElementSize(contenedor);
const activo = ref<number | null>(null);
const verTabla = ref(false);

const margen = { arriba: 14, derecha: 14, abajo: 28, izquierda: 40 };
const ancho = computed(() => Math.max(width.value, 280));
const areaAncho = computed(
    () => ancho.value - margen.izquierda - margen.derecha,
);
const areaAlto = computed(() => props.alto - margen.arriba - margen.abajo);
const n = computed(() => props.puntos.length);

const maximo = computed(() =>
    maximoRedondo(Math.max(0, ...props.series.flatMap((s) => s.valores))),
);
const marcas = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((f) => Math.round(maximo.value * f)),
);

const x = (i: number) =>
    margen.izquierda +
    (n.value <= 1
        ? areaAncho.value / 2
        : (i / (n.value - 1)) * areaAncho.value);
const y = (v: number) =>
    margen.arriba + areaAlto.value - (v / maximo.value) * areaAlto.value;

const trazo = (valores: number[]) =>
    valores
        .map(
            (v, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(v).toFixed(1)}`,
        )
        .join('');

const trazos = computed(() =>
    props.series.map((s) => ({
        ...s,
        linea: trazo(s.valores),
        relleno:
            s.area && s.valores.length > 1
                ? `${trazo(s.valores)}L${x(s.valores.length - 1).toFixed(1)},${y(0)}L${x(0).toFixed(1)},${y(0)}Z`
                : '',
    })),
);

const etiquetasX = computed(() => {
    const cantidad = Math.min(
        n.value,
        ancho.value < 420 ? 3 : ancho.value < 640 ? 5 : 7,
    );

    if (cantidad <= 1) {
        return n.value === 1 ? [0] : [];
    }

    return [
        ...new Set(
            Array.from({ length: cantidad }, (_, k) =>
                Math.round((k * (n.value - 1)) / (cantidad - 1)),
            ),
        ),
    ];
});

const indiceDesde = (clientX: number, rect: DOMRect) => {
    const relativo =
        ((clientX - rect.left) / rect.width) * ancho.value - margen.izquierda;

    return Math.min(
        n.value - 1,
        Math.max(0, Math.round((relativo / areaAncho.value) * (n.value - 1))),
    );
};

const mover = (evento: PointerEvent) => {
    if (n.value === 0) {
        return;
    }

    activo.value = indiceDesde(
        evento.clientX,
        (evento.currentTarget as SVGElement).getBoundingClientRect(),
    );
};

const teclado = (evento: KeyboardEvent) => {
    if (n.value === 0) {
        return;
    }

    const pasos: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1 };

    if (evento.key in pasos) {
        evento.preventDefault();
        activo.value = Math.min(
            n.value - 1,
            Math.max(0, (activo.value ?? n.value - 1) + pasos[evento.key]),
        );
    } else if (evento.key === 'Home' || evento.key === 'End') {
        evento.preventDefault();
        activo.value = evento.key === 'Home' ? 0 : n.value - 1;
    } else if (evento.key === 'Escape') {
        activo.value = null;
    }
};

const punto = computed(() =>
    activo.value === null ? null : props.puntos[activo.value],
);
const tooltipIzquierda = computed(() =>
    activo.value === null
        ? 0
        : Math.min(Math.max((x(activo.value) / ancho.value) * 100, 18), 82),
);
const totalSerie = (s: SerieLinea) => s.valores.reduce((a, v) => a + v, 0);
const resumen = computed(() =>
    props.series.map((s) => `${s.nombre}: ${numero(totalSerie(s))}`).join('; '),
);
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <ul
                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs"
                aria-label="Series"
            >
                <li
                    v-for="s in series"
                    :key="s.clave"
                    class="flex items-center gap-1.5 text-muted-foreground"
                >
                    <svg
                        width="16"
                        height="8"
                        aria-hidden="true"
                        class="shrink-0"
                    >
                        <line
                            x1="1"
                            x2="15"
                            y1="4"
                            y2="4"
                            :stroke="s.color"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            :stroke-dasharray="
                                s.discontinua ? '3 3' : undefined
                            "
                        />
                    </svg>
                    {{ s.nombre }}
                    <span class="font-medium text-foreground tabular-nums">{{
                        numero(totalSerie(s))
                    }}</span>
                </li>
            </ul>
            <button
                type="button"
                class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                :aria-pressed="verTabla"
                @click="verTabla = !verTabla"
            >
                {{ verTabla ? 'Ver gráfica' : 'Ver como tabla' }}
            </button>
        </div>

        <div v-if="verTabla" class="max-h-80 overflow-auto rounded-lg border">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    {{
                        unidad
                    }}
                    por
                    {{
                        etiquetaTabla.toLowerCase()
                    }}
                </caption>
                <thead
                    class="sticky top-0 bg-muted text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2 font-medium">
                            {{ etiquetaTabla }}
                        </th>
                        <th
                            v-for="s in series"
                            :key="s.clave"
                            scope="col"
                            class="px-3 py-2 text-right font-medium"
                        >
                            {{ s.nombre }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(p, i) in puntos"
                        :key="p.clave"
                        class="border-t"
                    >
                        <td class="px-3 py-1.5 first-letter:uppercase">
                            {{ p.titulo }}
                            <span
                                v-if="p.parcial"
                                class="text-xs text-muted-foreground"
                                >(parcial)</span
                            >
                        </td>
                        <td
                            v-for="s in series"
                            :key="s.clave"
                            class="px-3 py-1.5 text-right tabular-nums"
                        >
                            {{ numero(s.valores[i] ?? 0) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            ref="contenedor"
            class="relative w-full touch-pan-y select-none"
            :style="{ height: `${alto}px` }"
        >
            <svg
                :viewBox="`0 0 ${ancho} ${alto}`"
                :width="ancho"
                :height="alto"
                class="block max-w-full overflow-visible rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                role="img"
                tabindex="0"
                :aria-label="`Gráfica de ${unidad}. ${resumen}. Usa las flechas para recorrer los puntos.`"
                @pointermove="mover"
                @pointerdown="mover"
                @pointerleave="activo = null"
                @keydown="teclado"
                @blur="activo = null"
            >
                <defs>
                    <linearGradient
                        v-for="s in trazos.filter((t) => t.relleno)"
                        :id="`${id}-${s.clave}`"
                        :key="s.clave"
                        x1="0"
                        x2="0"
                        y1="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            :stop-color="s.color"
                            stop-opacity="0.22"
                        />
                        <stop
                            offset="100%"
                            :stop-color="s.color"
                            stop-opacity="0"
                        />
                    </linearGradient>
                </defs>
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
                            n > 1 && i === 0
                                ? 'start'
                                : n > 1 && i === n - 1
                                  ? 'end'
                                  : 'middle'
                        "
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ puntos[i]?.eje }}
                    </text>
                </g>
                <template v-for="s in trazos" :key="s.clave">
                    <path
                        v-if="s.relleno"
                        :d="s.relleno"
                        :fill="`url(#${id}-${s.clave})`"
                    />
                    <path
                        :d="s.linea"
                        fill="none"
                        :stroke="s.color"
                        :stroke-width="s.discontinua ? 1.5 : 2.25"
                        :stroke-dasharray="s.discontinua ? '4 4' : undefined"
                        :stroke-opacity="s.discontinua ? 0.7 : 1"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <circle
                        v-if="n === 1"
                        :cx="x(0)"
                        :cy="y(s.valores[0] ?? 0)"
                        r="4"
                        :fill="s.color"
                    />
                </template>
                <g v-if="punto && activo !== null">
                    <line
                        :x1="x(activo)"
                        :x2="x(activo)"
                        :y1="margen.arriba"
                        :y2="y(0)"
                        stroke="var(--muted-foreground)"
                        stroke-opacity="0.35"
                        stroke-width="1"
                    />
                    <circle
                        v-for="s in series"
                        :key="s.clave"
                        :cx="x(activo)"
                        :cy="y(s.valores[activo] ?? 0)"
                        r="4.5"
                        :fill="s.discontinua ? 'var(--card)' : s.color"
                        :stroke="s.discontinua ? s.color : 'var(--card)'"
                        stroke-width="2"
                    />
                </g>
            </svg>
            <div
                v-if="punto && activo !== null"
                class="pointer-events-none absolute top-0 z-10 min-w-40 -translate-x-1/2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg"
                :style="{ left: `${tooltipIzquierda}%` }"
                role="status"
                aria-live="polite"
            >
                <p class="font-medium text-foreground first-letter:uppercase">
                    {{ punto.titulo }}
                </p>
                <p
                    v-if="punto.parcial"
                    class="text-[11px] text-muted-foreground"
                >
                    Periodo incompleto
                </p>
                <ul class="mt-1.5 space-y-1">
                    <li
                        v-for="s in series"
                        :key="s.clave"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :style="{
                                background: s.color,
                                opacity: s.discontinua ? 0.6 : 1,
                            }"
                            aria-hidden="true"
                        />
                        <span
                            class="min-w-0 flex-1 truncate text-muted-foreground"
                            >{{ s.nombre }}</span
                        >
                        <span
                            class="font-semibold text-foreground tabular-nums"
                            >{{ numero(s.valores[activo] ?? 0) }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
