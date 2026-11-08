<script setup lang="ts">
import { ArrowDownRight, ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { numero } from '@/lib/formato';
import { comparar } from '@/lib/graficas';
import { cn } from '@/lib/utils';

/**
 * Cambio frente al periodo anterior equivalente.
 *
 * - Sin datos previos no se inventa un porcentaje: se muestra la diferencia
 *   o «sin datos previos».
 * - `mejor` indica qué dirección es favorable. Con 'neutral' (o cuando un
 *   descenso no es necesariamente malo) no se colorea como bueno o malo.
 */
const props = withDefaults(
    defineProps<{
        actual: number;
        anterior: number;
        mejor?: 'arriba' | 'abajo' | 'neutral';
        contexto?: string;
        compacto?: boolean;
    }>(),
    { mejor: 'arriba', contexto: 'vs. periodo anterior', compacto: false },
);

const c = computed(() => comparar(props.actual, props.anterior));

const tono = computed(() => {
    if (
        c.value.sentido === 'igual' ||
        props.mejor === 'neutral' ||
        c.value.porcentaje === null
    ) {
        return 'text-muted-foreground bg-muted';
    }

    const favorable =
        (c.value.sentido === 'sube') === (props.mejor === 'arriba');

    return favorable
        ? 'text-success bg-success-soft'
        : 'text-warning bg-warning-soft';
});

const texto = computed(() => {
    if (props.anterior === 0) {
        return props.actual === 0
            ? 'Sin actividad en ambos periodos'
            : 'Sin datos del periodo anterior';
    }

    if (c.value.sentido === 'igual') {
        return 'Sin cambios';
    }

    const signo = c.value.sentido === 'sube' ? '+' : '−';

    return `${signo}${Math.abs(c.value.porcentaje ?? 0).toLocaleString('es-MX')} %`;
});

const icono = computed(() =>
    c.value.sentido === 'sube'
        ? ArrowUpRight
        : c.value.sentido === 'baja'
          ? ArrowDownRight
          : ArrowRight,
);
</script>

<template>
    <span
        class="inline-flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs"
    >
        <span
            :class="
                cn(
                    'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-medium tabular-nums',
                    tono,
                )
            "
        >
            <component
                :is="icono"
                v-if="anterior > 0 || actual > 0"
                class="size-3.5"
                aria-hidden="true"
            />
            {{ texto }}
        </span>
        <span v-if="!compacto && anterior > 0" class="text-muted-foreground">
            {{ contexto }} ({{ numero(anterior) }})
        </span>
    </span>
</template>
