<script setup lang="ts">
import { computed, ref } from 'vue';
import { numero } from '@/lib/formato';

/**
 * Mapa de calor día de la semana × hora local. La intensidad usa una escala
 * de raíz cuadrada para que los horarios con poca actividad sigan siendo
 * visibles junto a los picos. En pantallas angostas la cuadrícula se
 * desplaza horizontalmente (con los días fijos a la izquierda).
 */
const props = defineProps<{
    celdas: { dia: number; hora: number; total: number }[];
    unidad?: string;
}>();

const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const DIAS_LARGOS = [
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
    'domingo',
];
const HORAS = Array.from({ length: 24 }, (_, h) => h);

const matriz = computed(() => {
    const m = Array.from({ length: 7 }, () => Array<number>(24).fill(0));

    for (const c of props.celdas) {
        if (c.dia >= 1 && c.dia <= 7 && c.hora >= 0 && c.hora <= 23) {
            m[c.dia - 1][c.hora] = c.total;
        }
    }

    return m;
});
const maximo = computed(() => Math.max(0, ...matriz.value.flat()));
const activo = ref<{ dia: number; hora: number } | null>(null);

const intensidad = (v: number) =>
    maximo.value === 0 || v === 0
        ? 0
        : 0.12 + 0.88 * Math.sqrt(v / maximo.value);
const rango = (h: number) =>
    `${String(h).padStart(2, '0')}:00–${String(h).padStart(2, '0')}:59`;

const pico = computed(() => {
    let mejor: { dia: number; hora: number; total: number } | null = null;

    matriz.value.forEach((fila, d) =>
        fila.forEach((v, h) => {
            if (v > 0 && (!mejor || v > mejor.total)) {
                mejor = { dia: d, hora: h, total: v };
            }
        }),
    );

    return mejor as { dia: number; hora: number; total: number } | null;
});
const etiquetaActivo = computed(() => {
    if (!activo.value) {
        return null;
    }

    const { dia, hora } = activo.value;

    return `${DIAS_LARGOS[dia]} de ${rango(hora)}: ${numero(matriz.value[dia][hora])} ${props.unidad ?? 'interacciones'}`;
});
</script>

<template>
    <div class="space-y-2">
        <p
            class="min-h-5 text-xs text-muted-foreground first-letter:uppercase"
            aria-live="polite"
        >
            <template v-if="etiquetaActivo">{{ etiquetaActivo }}</template>
            <template v-else-if="pico">
                Horario con más actividad:
                <span class="font-medium text-foreground"
                    >{{ DIAS_LARGOS[pico.dia] }}, {{ rango(pico.hora) }}</span
                >
                ({{ numero(pico.total) }})
            </template>
        </p>
        <div class="-mx-1 overflow-x-auto px-1 pb-1" tabindex="-1">
            <table
                class="w-full min-w-[520px] table-fixed border-separate border-spacing-[3px] text-[10px]"
            >
                <caption class="sr-only">
                    {{
                        unidad ?? 'Interacciones'
                    }}
                    por día de la semana y hora
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="w-9">
                            <span class="sr-only">Día</span>
                        </th>
                        <th
                            v-for="h in HORAS"
                            :key="h"
                            scope="col"
                            class="p-0 text-center font-normal text-muted-foreground"
                        >
                            <span :class="h % 3 === 0 ? '' : 'sr-only'">{{
                                h
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(fila, d) in matriz" :key="d">
                        <th
                            scope="row"
                            class="sticky left-0 z-10 bg-card pr-1.5 text-left font-medium text-muted-foreground"
                        >
                            {{ DIAS[d] }}
                        </th>
                        <td
                            v-for="(v, h) in fila"
                            :key="h"
                            tabindex="0"
                            :aria-label="`${DIAS_LARGOS[d]}, ${rango(h)}: ${numero(v)}`"
                            class="h-6 rounded-[4px] p-0 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :style="{
                                background:
                                    v === 0
                                        ? 'var(--muted)'
                                        : `color-mix(in oklab, var(--primary) ${Math.round(intensidad(v) * 100)}%, transparent)`,
                            }"
                            @pointerenter="activo = { dia: d, hora: h }"
                            @pointerleave="activo = null"
                            @focus="activo = { dia: d, hora: h }"
                            @blur="activo = null"
                        />
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            class="flex items-center justify-end gap-2 text-[11px] text-muted-foreground"
        >
            <span>Menos</span>
            <span class="flex gap-0.5" aria-hidden="true">
                <span
                    v-for="f in [0, 0.25, 0.5, 0.75, 1]"
                    :key="f"
                    class="size-3 rounded-[3px]"
                    :style="{
                        background:
                            f === 0
                                ? 'var(--muted)'
                                : `color-mix(in oklab, var(--primary) ${Math.round((0.12 + 0.88 * Math.sqrt(f)) * 100)}%, transparent)`,
                    }"
                />
            </span>
            <span>Más</span>
        </div>
    </div>
</template>
