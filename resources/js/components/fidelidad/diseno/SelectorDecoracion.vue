<script setup lang="ts">
import { Check, Undo2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { DECORACIONES } from '@/components/fidelidad-diseno/catalogo';
import Decoracion from '@/components/fidelidad-diseno/Decoracion.vue';
import type { ClaveDecoracion } from '@/components/fidelidad-diseno/tipos';

/**
 * Selector visual de decoraciones. Cada opción muestra el patrón REAL
 * (mismo componente `Decoracion` que la página) con los colores actuales
 * del diseño.
 *
 * - Ratón/teclado: al pasar o enfocar una opción se previsualiza en la
 *   página (`previsualizar`) y se revierte al salir sin elegir.
 * - Táctil: un toque elige; «Volver a …» deshace la elección.
 * - Accesible como grupo de radio (flechas para moverse, Espacio/Intro
 *   para elegir).
 */
const modelo = defineModel<ClaveDecoracion>({ required: true });

const props = defineProps<{
    etiqueta: string;
    /** Fondo CSS de la miniatura (el de la superficie decorada). */
    fondo: string;
    /** Color del patrón. */
    color: string;
    /** Intensidad real (0 a 60); en la miniatura se refuerza un poco. */
    opacidad: number;
}>();

const emit = defineEmits<{ previsualizar: [valor: ClaveDecoracion | null] }>();

const opciones = Object.entries(DECORACIONES).map(([valor, nombre]) => ({
    valor: valor as ClaveDecoracion,
    nombre,
}));
const anterior = ref<ClaveDecoracion | null>(null);
const opacidadMuestra = computed(() =>
    Math.min(60, Math.max(28, props.opacidad * 2)),
);

let ultimoPuntero = 'mouse';

const elegir = (valor: ClaveDecoracion) => {
    if (valor !== modelo.value) {
        anterior.value = modelo.value;
        modelo.value = valor;
    }

    emit('previsualizar', null);
};

const deshacer = () => {
    if (anterior.value) {
        modelo.value = anterior.value;
        anterior.value = null;
    }
};

const entrar = (valor: ClaveDecoracion, e: PointerEvent) => {
    ultimoPuntero = e.pointerType;

    if (e.pointerType === 'mouse') {
        emit('previsualizar', valor);
    }
};

const enfocar = (valor: ClaveDecoracion) => {
    // Con toque, el foco llega junto con la elección: no hace falta previa.
    if (ultimoPuntero !== 'touch') {
        emit('previsualizar', valor);
    }
};

const salir = () => emit('previsualizar', null);

const mover = (evento: KeyboardEvent, i: number) => {
    const delta =
        evento.key === 'ArrowRight' || evento.key === 'ArrowDown'
            ? 1
            : evento.key === 'ArrowLeft' || evento.key === 'ArrowUp'
              ? -1
              : 0;

    if (delta === 0) {
        return;
    }

    // Como en un grupo de radio nativo, las flechas también eligen.
    evento.preventDefault();
    ultimoPuntero = 'keyboard';
    const siguiente = opciones[(i + delta + opciones.length) % opciones.length];
    elegir(siguiente.valor);
    const grupo = (evento.currentTarget as HTMLElement).closest(
        '[role=radiogroup]',
    );
    grupo
        ?.querySelector<HTMLElement>(`[data-valor="${siguiente.valor}"]`)
        ?.focus();
};

watch(modelo, (v) => {
    if (v === anterior.value) {
        anterior.value = null;
    }
});
</script>

<template>
    <fieldset class="space-y-2">
        <div class="flex min-h-8 items-center justify-between gap-2">
            <legend class="text-sm font-medium">{{ etiqueta }}</legend>
            <button
                v-if="anterior"
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-md px-2 text-xs font-medium text-primary hover:bg-primary/8"
                @click="deshacer"
            >
                <Undo2 class="size-3.5" aria-hidden="true" />
                Volver a «{{ DECORACIONES[anterior] }}»
            </button>
        </div>
        <div
            role="radiogroup"
            :aria-label="etiqueta"
            class="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-2"
            @pointerleave="salir"
        >
            <button
                v-for="(o, i) in opciones"
                :key="o.valor"
                type="button"
                role="radio"
                :data-valor="o.valor"
                :aria-checked="modelo === o.valor"
                :tabindex="modelo === o.valor ? 0 : -1"
                :title="o.nombre"
                class="group relative flex min-h-10 flex-col overflow-hidden rounded-xl border-2 border-border bg-background text-left transition hover:border-primary/50 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 aria-checked:border-primary aria-checked:shadow-[0_0_0_3px] aria-checked:shadow-primary/20"
                @click="elegir(o.valor)"
                @pointerenter="entrar(o.valor, $event)"
                @pointerdown="ultimoPuntero = $event.pointerType"
                @focus="enfocar(o.valor)"
                @blur="salir"
                @keydown="mover($event, i)"
            >
                <span
                    class="relative isolate block h-14 w-full overflow-hidden"
                    :style="{ background: fondo }"
                    aria-hidden="true"
                >
                    <Decoracion
                        :tipo="o.valor"
                        :color="color"
                        :opacidad="opacidadMuestra"
                    />
                    <span
                        v-if="o.valor === 'ninguna'"
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <span
                            class="h-px w-3/4 rotate-[-20deg] opacity-50"
                            :style="{ backgroundColor: color }"
                        />
                    </span>
                </span>
                <span
                    class="flex min-h-8 items-center gap-1 px-2 py-1 text-xs leading-tight font-medium group-aria-checked:text-primary"
                >
                    <span class="min-w-0 flex-1">{{ o.nombre }}</span>
                </span>
                <span
                    v-if="modelo === o.valor"
                    class="absolute top-1.5 right-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
                    aria-hidden="true"
                >
                    <Check class="size-3.5" />
                </span>
            </button>
        </div>
    </fieldset>
</template>
