<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { urlMedio } from './tema';
import type { Bloque } from './tipos';

/** Carrusel deslizable (scroll-snap), con botones y teclado. */
const props = defineProps<{
    bloque: Bloque;
    modo: 'publico' | 'vista_previa';
}>();

const pista = useTemplateRef<HTMLDivElement>('pista');
const mover = (direccion: 1 | -1) =>
    pista.value?.scrollBy({
        left: direccion * pista.value.clientWidth * 0.8,
        behavior: 'smooth',
    });

const proporcion = (p?: string) =>
    p === 'vertical'
        ? 'aspect-[4/5]'
        : p === 'horizontal'
          ? 'aspect-[16/10]'
          : 'aspect-square';
const enlace = (href?: string | null) =>
    props.modo === 'publico' ? (href ?? undefined) : undefined;
</script>

<template>
    <section :aria-label="bloque.titulo || 'Galería de imágenes'">
        <div
            v-if="bloque.titulo"
            class="mb-2 flex items-center justify-between gap-2"
        >
            <h2 class="pe-titulo text-[1.05em]">{{ bloque.titulo }}</h2>
        </div>
        <div class="relative">
            <div
                ref="pista"
                class="pe-pista flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1"
                tabindex="0"
            >
                <component
                    :is="e.href ? 'a' : 'div'"
                    v-for="e in bloque.elementos ?? []"
                    :key="e.id"
                    :href="e.href ? enlace(e.href) : undefined"
                    :target="e.href ? '_blank' : undefined"
                    :rel="e.href ? 'noopener noreferrer' : undefined"
                    class="w-[78%] shrink-0 snap-start @lg:w-[46%]"
                    @click="modo !== 'publico' && $event.preventDefault()"
                >
                    <img
                        v-if="urlMedio(e.archivo)"
                        :src="urlMedio(e.archivo) ?? undefined"
                        :alt="e.alt"
                        :class="proporcion(bloque.proporcion)"
                        class="w-full rounded-(--pe-radio) border border-(--pe-borde) object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </component>
                <p
                    v-if="!(bloque.elementos ?? []).length"
                    class="w-full rounded-(--pe-radio) border border-dashed border-(--pe-borde) p-8 text-center text-[0.85em] opacity-70"
                >
                    Agrega imágenes al carrusel.
                </p>
            </div>
            <template v-if="(bloque.elementos ?? []).length > 1">
                <button
                    type="button"
                    class="pe-flecha left-1"
                    aria-label="Imagen anterior"
                    @click="mover(-1)"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    type="button"
                    class="pe-flecha right-1"
                    aria-label="Imagen siguiente"
                    @click="mover(1)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </template>
        </div>
    </section>
</template>
