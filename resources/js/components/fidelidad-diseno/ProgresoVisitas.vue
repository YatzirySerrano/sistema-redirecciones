<script setup lang="ts">
import { Gift } from '@lucide/vue';
import { computed } from 'vue';
import { ICONOS, PRESENTACIONES } from './catalogo';
import { conAlfa } from './color';
import type { DocumentoDiseno } from './tipos';

/**
 * Presentaciones del avance. El número de sellos es SIEMPRE la meta real de
 * la campaña (`meta`) y los completados, las visitas reales (`visitas`).
 */
const props = defineProps<{
    progreso: DocumentoDiseno['progreso'];
    texto: string;
    textoSuave: string;
    visitas: number;
    meta: number;
    recompensa: string;
    /** Diseño horizontal: menos espacio vertical. */
    compacta?: boolean;
}>();

/** Más de 40 sellos no caben con claridad: se usa la barra. */
const MAX_SELLOS = 40;

const meta = computed(() => Math.max(1, props.meta));
const hechas = computed(() => Math.max(0, Math.min(props.visitas, meta.value)));
const porcentaje = computed(() =>
    Math.round((hechas.value * 100) / meta.value),
);
const presentacion = computed(() =>
    meta.value > MAX_SELLOS &&
    !['barra', 'premium'].includes(props.progreso.presentacion)
        ? 'barra'
        : props.progreso.presentacion,
);
const icono = computed(
    () => (ICONOS[props.progreso.icono] ?? ICONOS.estrella).icono,
);
const sellos = computed(() =>
    Array.from({ length: meta.value }, (_, i) => ({
        n: i + 1,
        hecho: i < hechas.value,
        // Ligera rotación determinista: aspecto de sello real.
        giro: ((i * 37) % 17) - 8,
    })),
);
const columnas = computed(() => {
    const m = meta.value;

    if (props.compacta) {
        return m <= 6 ? 3 : m <= 12 ? (m % 4 === 0 ? 4 : 5) : 6;
    }

    if (m <= 5) {
        return m;
    }

    if (m <= 20 && m % 5 === 0) {
        return 5;
    }

    return m <= 12 ? 6 : m <= 24 ? 6 : 8;
});

const etiqueta = computed(
    () =>
        `${hechas.value} de ${meta.value} visitas (${PRESENTACIONES[presentacion.value].nombre})`,
);

const estiloPendiente = computed(() => {
    const p = props.progreso;

    if (p.pendiente_estilo === 'tenue') {
        return {
            backgroundColor: conAlfa(p.pendiente_fondo, 0.16),
            color: conAlfa(p.pendiente_icono, 0.45),
        };
    }

    return {
        backgroundColor: 'transparent',
        border: `2px ${p.pendiente_estilo === 'punteado' ? 'dashed' : 'solid'} ${conAlfa(p.pendiente_fondo, 0.55)}`,
        color: conAlfa(p.pendiente_icono, 0.4),
    };
});
const estiloHecho = computed(() => ({
    backgroundColor: props.progreso.completo_fondo,
    color: props.progreso.completo_icono,
    boxShadow: `0 6px 14px -8px ${conAlfa(props.progreso.completo_fondo, 0.9)}`,
}));

// ---- Camino al premio: puntos en zigzag dentro de un viewBox ----
const ruta = computed(() => {
    const total = meta.value + 1; // + la meta (regalo)
    const cols = props.compacta ? 4 : meta.value <= 8 ? 4 : 5;
    const filas = Math.ceil(total / cols);
    const alto = 14 + (filas - 1) * 24 + 14;
    const puntos = Array.from({ length: total }, (_, i) => {
        const fila = Math.floor(i / cols);
        const col = i % cols;
        const x = fila % 2 === 0 ? col : cols - 1 - col;

        return {
            x: 10 + (x * 80) / Math.max(1, cols - 1),
            y: 14 + fila * 24,
            i,
        };
    });
    let d = `M ${puntos[0].x} ${puntos[0].y}`;

    for (let i = 1; i < puntos.length; i++) {
        const a = puntos[i - 1];
        const b = puntos[i];

        if (a.y === b.y) {
            d += ` L ${b.x} ${b.y}`;
        } else {
            const lado = a.x > 50 ? 8 : -8;
            d += ` C ${a.x + lado} ${a.y} ${b.x + lado} ${b.y} ${b.x} ${b.y}`;
        }
    }

    return { puntos, alto, d };
});

// ---- Premium: anillo ----
const RADIO = 42;
const circunferencia = 2 * Math.PI * RADIO;
</script>

<template>
    <div
        role="img"
        :aria-label="etiqueta"
        class="relative"
        :style="{ color: texto }"
    >
        <!-- 1. Tarjeta de sellos -->
        <div
            v-if="presentacion === 'sellos'"
            class="mx-auto grid w-full gap-2.5"
            :style="{
                gridTemplateColumns: `repeat(${columnas}, minmax(0, 1fr))`,
                maxWidth: `${columnas * 3.6}rem`,
            }"
        >
            <span
                v-for="s in sellos"
                :key="s.n"
                class="flex aspect-square items-center justify-center rounded-full transition-all duration-500"
                :style="[
                    s.hecho ? estiloHecho : estiloPendiente,
                    s.hecho ? { transform: `rotate(${s.giro}deg)` } : {},
                ]"
            >
                <component
                    :is="icono"
                    class="size-[52%]"
                    :stroke-width="s.hecho ? 2.2 : 1.6"
                    aria-hidden="true"
                />
            </span>
        </div>

        <!-- 2. Cuadrícula de visitas -->
        <div
            v-else-if="presentacion === 'cuadricula'"
            class="grid w-full gap-2"
            :style="{
                gridTemplateColumns: `repeat(${columnas}, minmax(0, 1fr))`,
            }"
        >
            <span
                v-for="s in sellos"
                :key="s.n"
                class="relative flex aspect-square items-center justify-center rounded-xl text-sm font-semibold tabular-nums transition-all duration-500"
                :style="
                    s.hecho
                        ? estiloHecho
                        : {
                              ...estiloPendiente,
                              borderRadius: '0.75rem',
                          }
                "
            >
                <component
                    :is="s.n === meta ? Gift : icono"
                    v-if="s.hecho || s.n === meta"
                    class="size-[48%]"
                    aria-hidden="true"
                />
                <span v-else>{{ s.n }}</span>
            </span>
        </div>

        <!-- 3. Círculos que se llenan -->
        <div
            v-else-if="presentacion === 'circulos'"
            class="flex flex-col items-center gap-4"
        >
            <div class="flex flex-wrap justify-center gap-2">
                <span
                    v-for="s in sellos"
                    :key="s.n"
                    class="relative size-7 overflow-hidden rounded-full"
                    :style="{
                        backgroundColor: conAlfa(
                            progreso.pendiente_fondo,
                            0.18,
                        ),
                        boxShadow: `inset 0 0 0 1.5px ${conAlfa(progreso.pendiente_fondo, 0.45)}`,
                    }"
                >
                    <span
                        class="absolute inset-x-0 bottom-0 transition-all duration-700 ease-out"
                        :style="{
                            height: s.hecho ? '100%' : '0%',
                            backgroundColor: progreso.completo_fondo,
                        }"
                    />
                </span>
            </div>
        </div>

        <!-- 4. Barra de progreso -->
        <div v-else-if="presentacion === 'barra'" class="space-y-3">
            <div class="flex items-end justify-between gap-3">
                <p class="leading-none">
                    <span class="text-5xl font-semibold tabular-nums">{{
                        hechas
                    }}</span>
                    <span
                        class="text-lg tabular-nums"
                        :style="{ color: textoSuave }"
                    >
                        / {{ meta }}</span
                    >
                </p>
                <component
                    :is="icono"
                    class="size-8"
                    :style="{ color: progreso.barra_color }"
                    aria-hidden="true"
                />
            </div>
            <div
                class="h-3.5 overflow-hidden rounded-full"
                :style="{
                    backgroundColor: conAlfa(progreso.barra_fondo, 0.22),
                }"
            >
                <div
                    class="h-full rounded-full transition-[width] duration-700 ease-out"
                    :style="{
                        width: `${porcentaje}%`,
                        backgroundColor: progreso.barra_color,
                    }"
                />
            </div>
        </div>

        <!-- 5. Premium con recompensa destacada -->
        <div
            v-else-if="presentacion === 'premium'"
            class="flex items-center gap-5"
            :class="compacta ? 'flex-col gap-3' : ''"
        >
            <div
                class="relative shrink-0"
                :class="compacta ? 'size-24' : 'size-32'"
            >
                <svg viewBox="0 0 100 100" class="size-full -rotate-90">
                    <circle
                        cx="50"
                        cy="50"
                        :r="RADIO"
                        fill="none"
                        :stroke="conAlfa(progreso.pendiente_fondo, 0.2)"
                        stroke-width="7"
                    />
                    <circle
                        cx="50"
                        cy="50"
                        :r="RADIO"
                        fill="none"
                        :stroke="progreso.completo_fondo"
                        stroke-width="7"
                        stroke-linecap="round"
                        :stroke-dasharray="circunferencia"
                        :stroke-dashoffset="
                            circunferencia * (1 - porcentaje / 100)
                        "
                        class="transition-[stroke-dashoffset] duration-700 ease-out"
                    />
                </svg>
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center"
                >
                    <Gift
                        class="size-6"
                        :style="{ color: progreso.completo_fondo }"
                        aria-hidden="true"
                    />
                    <span class="mt-0.5 text-lg font-semibold tabular-nums"
                        >{{ hechas }}/{{ meta }}</span
                    >
                </div>
            </div>
            <div class="min-w-0" :class="compacta ? 'text-center' : ''">
                <p
                    class="text-[0.7rem] font-semibold tracking-[0.18em] uppercase"
                    :style="{ color: textoSuave }"
                >
                    Tu recompensa
                </p>
                <p
                    class="mt-1 text-xl leading-tight font-semibold text-balance"
                >
                    {{ recompensa }}
                </p>
                <div class="mt-3 flex flex-wrap gap-1" aria-hidden="true">
                    <span
                        v-for="s in sellos.slice(0, 24)"
                        :key="s.n"
                        class="size-1.5 rounded-full"
                        :style="{
                            backgroundColor: s.hecho
                                ? progreso.completo_fondo
                                : conAlfa(progreso.pendiente_fondo, 0.3),
                        }"
                    />
                </div>
            </div>
        </div>

        <!-- 6. Camino al premio -->
        <svg
            v-else-if="presentacion === 'ruta'"
            :viewBox="`0 0 100 ${ruta.alto}`"
            class="w-full overflow-visible"
        >
            <path
                :d="ruta.d"
                fill="none"
                :stroke="conAlfa(progreso.pendiente_fondo, 0.45)"
                stroke-width="1.4"
                stroke-dasharray="2.5 2.5"
                stroke-linecap="round"
            />
            <g v-for="p in ruta.puntos" :key="p.i">
                <circle
                    :cx="p.x"
                    :cy="p.y"
                    :r="p.i === meta ? 8 : 6.2"
                    :fill="
                        p.i < hechas || (p.i === meta && hechas >= meta)
                            ? progreso.completo_fondo
                            : p.i === meta
                              ? conAlfa(progreso.completo_fondo, 0.18)
                              : conAlfa(progreso.pendiente_fondo, 0.14)
                    "
                    :stroke="
                        p.i < hechas
                            ? 'none'
                            : conAlfa(
                                  p.i === meta
                                      ? progreso.completo_fondo
                                      : progreso.pendiente_fondo,
                                  0.6,
                              )
                    "
                    stroke-width="1"
                    :stroke-dasharray="
                        p.i === meta
                            ? '0'
                            : progreso.pendiente_estilo === 'punteado'
                              ? '1.5 1.2'
                              : '0'
                    "
                />
                <component
                    :is="p.i === meta ? Gift : icono"
                    :x="p.x - (p.i === meta ? 4.5 : 3.6)"
                    :y="p.y - (p.i === meta ? 4.5 : 3.6)"
                    :width="p.i === meta ? 9 : 7.2"
                    :height="p.i === meta ? 9 : 7.2"
                    :stroke-width="2"
                    :color="
                        p.i < hechas || (p.i === meta && hechas >= meta)
                            ? progreso.completo_icono
                            : p.i === meta
                              ? progreso.completo_fondo
                              : conAlfa(progreso.pendiente_icono, 0.5)
                    "
                    aria-hidden="true"
                />
            </g>
        </svg>

        <!-- 7. Boleto perforado -->
        <div
            v-else
            class="relative overflow-hidden rounded-xl px-4 py-4"
            :style="{
                backgroundColor: conAlfa(progreso.pendiente_fondo, 0.09),
                boxShadow: `inset 0 0 0 1px ${conAlfa(progreso.pendiente_fondo, 0.22)}`,
            }"
        >
            <span
                class="absolute top-1/2 -left-2.5 size-5 -translate-y-1/2 rounded-full"
                :style="{ backgroundColor: 'var(--fondo-tarjeta, #000)' }"
                aria-hidden="true"
            />
            <span
                class="absolute top-1/2 -right-2.5 size-5 -translate-y-1/2 rounded-full"
                :style="{ backgroundColor: 'var(--fondo-tarjeta, #000)' }"
                aria-hidden="true"
            />
            <div
                class="mb-3 flex items-center justify-between text-[0.65rem] font-semibold tracking-[0.22em] uppercase"
                :style="{ color: textoSuave }"
            >
                <span>Visitas</span>
                <span class="tabular-nums"
                    >Nº {{ String(hechas).padStart(2, '0') }}</span
                >
            </div>
            <div
                class="grid gap-2"
                :style="{
                    gridTemplateColumns: `repeat(${columnas}, minmax(0, 1fr))`,
                }"
            >
                <span
                    v-for="s in sellos"
                    :key="s.n"
                    class="flex aspect-square items-center justify-center rounded-full transition-all duration-500"
                    :style="
                        s.hecho
                            ? estiloHecho
                            : {
                                  border: `1.5px dashed ${conAlfa(progreso.pendiente_fondo, 0.5)}`,
                                  color: conAlfa(progreso.pendiente_icono, 0.6),
                              }
                    "
                >
                    <component
                        :is="icono"
                        v-if="s.hecho"
                        class="size-[50%]"
                        aria-hidden="true"
                    />
                    <span v-else class="text-[0.65rem] tabular-nums">{{
                        s.n
                    }}</span>
                </span>
            </div>
            <div
                class="mt-3 border-t border-dashed pt-2 text-[0.65rem] tracking-[0.18em] uppercase"
                :style="{
                    borderColor: conAlfa(progreso.pendiente_fondo, 0.35),
                    color: textoSuave,
                }"
            >
                Premio: {{ recompensa }}
            </div>
        </div>

        <!-- Barra auxiliar opcional -->
        <div
            v-if="
                progreso.mostrar_barra &&
                !['barra', 'premium'].includes(presentacion)
            "
            class="mt-4 h-2 overflow-hidden rounded-full"
            :style="{ backgroundColor: conAlfa(progreso.barra_fondo, 0.22) }"
            aria-hidden="true"
        >
            <div
                class="h-full rounded-full transition-[width] duration-700 ease-out"
                :style="{
                    width: `${porcentaje}%`,
                    backgroundColor: progreso.barra_color,
                }"
            />
        </div>
    </div>
</template>
