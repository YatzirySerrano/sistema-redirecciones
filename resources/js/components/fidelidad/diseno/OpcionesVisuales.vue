<script setup lang="ts" generic="T extends string">
/**
 * Grupo de opciones exclusivas con botones grandes (accesible como grupo de
 * radio: flechas para moverse, espacio para elegir).
 */
const modelo = defineModel<T>({ required: true });

withDefaults(
    defineProps<{
        etiqueta: string;
        opciones: { valor: T; nombre: string; descripcion?: string }[];
        columnas?: 2 | 3 | 4 | 5 | 6;
    }>(),
    { columnas: 2 },
);

const mover = (evento: KeyboardEvent, valores: T[]) => {
    const i = valores.indexOf(modelo.value);
    const delta =
        evento.key === 'ArrowRight' || evento.key === 'ArrowDown'
            ? 1
            : evento.key === 'ArrowLeft' || evento.key === 'ArrowUp'
              ? -1
              : 0;

    if (delta === 0) {
        return;
    }

    evento.preventDefault();
    const siguiente = valores[(i + delta + valores.length) % valores.length];
    modelo.value = siguiente;
    const grupo = (evento.currentTarget as HTMLElement).closest(
        '[role=radiogroup]',
    );
    grupo
        ?.querySelector<HTMLElement>(`[data-valor="${CSS.escape(siguiente)}"]`)
        ?.focus();
};
</script>

<template>
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">{{ etiqueta }}</legend>
        <div
            role="radiogroup"
            :aria-label="etiqueta"
            class="grid gap-2"
            :class="{
                'grid-cols-2': columnas === 2,
                'grid-cols-3': columnas === 3,
                'grid-cols-4': columnas === 4,
                'grid-cols-5': columnas === 5,
                'grid-cols-6': columnas === 6,
            }"
        >
            <button
                v-for="o in opciones"
                :key="o.valor"
                type="button"
                role="radio"
                :data-valor="o.valor"
                :aria-checked="modelo === o.valor"
                :tabindex="modelo === o.valor ? 0 : -1"
                :title="o.descripcion ?? o.nombre"
                class="group flex min-h-10 flex-col items-center justify-center gap-1 rounded-lg border bg-background px-2 py-2 text-center text-xs font-medium transition hover:border-primary/40 hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 aria-checked:border-primary aria-checked:bg-primary/8 aria-checked:text-primary"
                @click="modelo = o.valor"
                @keydown="
                    mover(
                        $event,
                        opciones.map((x) => x.valor),
                    )
                "
            >
                <slot name="vista" :opcion="o" />
                <span class="leading-tight">{{ o.nombre }}</span>
            </button>
        </div>
    </fieldset>
</template>
