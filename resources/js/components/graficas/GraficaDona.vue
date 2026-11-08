<script setup lang="ts">
import { computed, ref } from 'vue';
import { compacto, numero } from '@/lib/formato';
import { porcentajeDe } from '@/lib/graficas';

/**
 * Dona de proporciones con el total al centro y una leyenda que siempre
 * muestra cantidad y porcentaje (la identidad nunca depende solo del
 * color). Al pasar el cursor o enfocar un elemento de la leyenda se
 * resalta su segmento.
 */
export type Segmento = {
    clave: string;
    etiqueta: string;
    total: number;
    color: string;
    descripcion?: string;
};

const props = withDefaults(
    defineProps<{
        segmentos: Segmento[];
        centro?: string;
        tamano?: number;
        compacta?: boolean;
    }>(),
    { centro: 'Total', tamano: 168, compacta: false },
);

const activo = ref<string | null>(null);
const visibles = computed(() => props.segmentos.filter((s) => s.total > 0));
const total = computed(() => visibles.value.reduce((a, s) => a + s.total, 0));

const radio = 42;
const grosor = 12;
const circunferencia = 2 * Math.PI * radio;
// Separación visual entre segmentos (solo si hay más de uno).
const separacion = computed(() => (visibles.value.length > 1 ? 1.6 : 0));

const arcos = computed(() => {
    let acumulado = 0;

    return visibles.value.map((s) => {
        const fraccion = total.value === 0 ? 0 : s.total / total.value;
        const largo = Math.max(0, fraccion * circunferencia - separacion.value);
        const arco = {
            ...s,
            dash: `${largo} ${circunferencia - largo}`,
            offset: -acumulado * circunferencia,
        };
        acumulado += fraccion;

        return arco;
    });
});

const destacado = computed(() =>
    activo.value ? visibles.value.find((s) => s.clave === activo.value) : null,
);
</script>

<template>
    <div class="@container">
        <div
            :class="
                compacta
                    ? 'flex items-center gap-4'
                    : 'flex flex-col items-center gap-5 @md:flex-row @md:items-center @md:gap-6'
            "
        >
            <div
                class="relative shrink-0"
                :style="{ width: `${tamano}px`, height: `${tamano}px` }"
            >
                <svg
                    viewBox="0 0 100 100"
                    :width="tamano"
                    :height="tamano"
                    class="-rotate-90"
                    role="img"
                    :aria-label="`${centro}: ${numero(total)}. ${visibles.map((s) => `${s.etiqueta} ${numero(s.total)} (${porcentajeDe(s.total, total)})`).join(', ')}`"
                >
                    <circle
                        cx="50"
                        cy="50"
                        :r="radio"
                        fill="none"
                        stroke="var(--muted)"
                        :stroke-width="grosor"
                    />
                    <circle
                        v-for="a in arcos"
                        :key="a.clave"
                        cx="50"
                        cy="50"
                        :r="radio"
                        fill="none"
                        :stroke="a.color"
                        :stroke-width="activo === a.clave ? grosor + 3 : grosor"
                        :stroke-dasharray="a.dash"
                        :stroke-dashoffset="a.offset"
                        :opacity="activo && activo !== a.clave ? 0.35 : 1"
                        class="transition-all duration-200"
                        @pointerenter="activo = a.clave"
                        @pointerleave="activo = null"
                    />
                </svg>
                <div
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
                    aria-hidden="true"
                >
                    <span
                        :class="[
                            'font-semibold tracking-tight tabular-nums',
                            tamano < 130 ? 'text-lg' : 'text-2xl',
                        ]"
                    >
                        {{
                            destacado
                                ? porcentajeDe(destacado.total, total)
                                : compacto(total)
                        }}
                    </span>
                    <span
                        class="max-w-[70%] truncate text-[11px] text-muted-foreground"
                    >
                        {{ destacado ? destacado.etiqueta : centro }}
                    </span>
                </div>
            </div>

            <ul class="w-full min-w-0 flex-1 space-y-1.5" role="list">
                <li
                    v-for="s in segmentos"
                    :key="s.clave"
                    tabindex="0"
                    :class="[
                        'flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        activo === s.clave ? 'bg-muted' : 'hover:bg-muted/60',
                        s.total === 0 && 'opacity-60',
                    ]"
                    @pointerenter="activo = s.total > 0 ? s.clave : null"
                    @pointerleave="activo = null"
                    @focus="activo = s.total > 0 ? s.clave : null"
                    @blur="activo = null"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ background: s.color }"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            :class="[
                                'block font-medium',
                                compacta ? 'leading-tight' : 'truncate',
                            ]"
                            >{{ s.etiqueta }}</span
                        >
                        <span
                            v-if="s.descripcion && !compacta"
                            class="block text-xs text-pretty text-muted-foreground"
                            >{{ s.descripcion }}</span
                        >
                    </span>
                    <span class="shrink-0 text-right tabular-nums">
                        <span class="block font-semibold">{{
                            numero(s.total)
                        }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            porcentajeDe(s.total, total)
                        }}</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
