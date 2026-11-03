<script setup lang="ts">
import { computed } from 'vue';
import type { TipoBloque } from '../tipos';
import { TIPOS } from './bloques';
import type { Categoria } from './bloques';

/** Catálogo de elementos agrupado por categoría. */
const emit = defineEmits<{ elegir: [tipo: TipoBloque] }>();

defineProps<{ compacto?: boolean }>();

const ORDEN: Categoria[] = [
    'Botones y contacto',
    'Multimedia',
    'Estructura',
    'Interacción',
];
const grupos = computed(() =>
    ORDEN.map((categoria) => ({
        categoria,
        tipos: (Object.keys(TIPOS) as TipoBloque[]).filter(
            (t) => TIPOS[t].categoria === categoria,
        ),
    })),
);
</script>

<template>
    <div class="grid grid-cols-1 gap-5">
        <section v-for="g in grupos" :key="g.categoria">
            <h3
                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ g.categoria }}
            </h3>
            <div
                class="grid grid-cols-1 gap-2"
                :class="compacto ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-3'"
            >
                <button
                    v-for="tipo in g.tipos"
                    :key="tipo"
                    type="button"
                    class="group flex min-h-[4.5rem] flex-col items-start gap-1.5 rounded-xl border bg-card p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                    @click="emit('elegir', tipo)"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-primary-foreground"
                    >
                        <component
                            :is="TIPOS[tipo].icono"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="text-sm leading-tight font-medium">{{
                        TIPOS[tipo].etiqueta
                    }}</span>
                    <span
                        v-if="!compacto"
                        class="text-xs leading-snug text-muted-foreground"
                        >{{ TIPOS[tipo].descripcion }}</span
                    >
                </button>
            </div>
        </section>
    </div>
</template>
