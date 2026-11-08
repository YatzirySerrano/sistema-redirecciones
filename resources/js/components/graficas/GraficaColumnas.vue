<script setup lang="ts">
import { computed, ref } from 'vue';
import { numero } from '@/lib/formato';

/**
 * Columnas verticales para categorías ordenadas (días de la semana, horas).
 * La columna máxima se resalta y se nombra en texto; cada columna es
 * enfocable y muestra su valor exacto.
 */
export type Columna = {
    clave: string | number;
    /** Texto del eje (corto). */
    eje: string;
    /** Nombre completo para el tooltip y lectores de pantalla. */
    titulo: string;
    valor: number;
    /** Texto secundario del tooltip (p. ej. «4 lunes en el periodo»). */
    detalle?: string;
};

const props = withDefaults(
    defineProps<{
        columnas: Columna[];
        alto?: number;
        formato?: (v: number) => string;
        unidad?: string;
    }>(),
    { alto: 180, formato: (v: number) => numero(v), unidad: 'interacciones' },
);

const activo = ref<string | number | null>(null);
const maximo = computed(() =>
    Math.max(0, ...props.columnas.map((c) => c.valor)),
);
const pico = computed(() =>
    maximo.value > 0
        ? props.columnas.find((c) => c.valor === maximo.value)
        : null,
);
const seleccion = computed(
    () => props.columnas.find((c) => c.clave === activo.value) ?? null,
);
</script>

<template>
    <div class="space-y-2">
        <p class="min-h-5 text-xs text-muted-foreground" aria-live="polite">
            <template v-if="seleccion">
                <span class="font-medium text-foreground"
                    >{{ seleccion.titulo }}:</span
                >
                {{ formato(seleccion.valor) }} {{ unidad }}
                <span v-if="seleccion.detalle"> · {{ seleccion.detalle }}</span>
            </template>
            <template v-else-if="pico">
                Mayor actividad:
                <span class="font-medium text-foreground">{{
                    pico.titulo
                }}</span>
                ({{ formato(pico.valor) }})
            </template>
        </p>
        <div
            class="flex items-end gap-1 sm:gap-1.5"
            :style="{ height: `${alto}px` }"
            role="list"
            :aria-label="`Distribución de ${unidad}`"
        >
            <div
                v-for="c in columnas"
                :key="c.clave"
                role="listitem"
                tabindex="0"
                :aria-label="`${c.titulo}: ${formato(c.valor)} ${unidad}${c.detalle ? `, ${c.detalle}` : ''}`"
                class="group flex h-full min-w-0 flex-1 cursor-default flex-col items-center justify-end gap-1.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @pointerenter="activo = c.clave"
                @pointerleave="activo = null"
                @focus="activo = c.clave"
                @blur="activo = null"
            >
                <div class="flex w-full flex-1 items-end justify-center">
                    <div
                        :class="[
                            'w-full max-w-10 rounded-t-md transition-all duration-300',
                            c.valor === maximo && maximo > 0
                                ? 'bg-primary'
                                : 'bg-primary/35 group-hover:bg-primary/60',
                            activo === c.clave && 'bg-primary/80',
                        ]"
                        :style="{
                            height:
                                maximo === 0
                                    ? '2px'
                                    : `${Math.max(2, (c.valor / maximo) * 100)}%`,
                        }"
                    />
                </div>
                <span
                    class="w-full truncate text-center text-[10px] text-muted-foreground sm:text-[11px]"
                    aria-hidden="true"
                    >{{ c.eje }}</span
                >
            </div>
        </div>
    </div>
</template>
