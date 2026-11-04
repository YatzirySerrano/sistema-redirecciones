<script setup lang="ts">
import { useElementSize, useIntersectionObserver } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import RenderizadorPagina from '../RenderizadorPagina.vue';
import { urlFuentes } from '../catalogo';
import type { Documento } from '../tipos';
import { cargarFuentesCatalogo } from './plantillas';

/**
 * Miniatura REAL de una plantilla: el mismo renderizador de la página
 * pública, a ancho de teléfono y reducido con `transform: scale`. Se monta
 * solo cuando entra en pantalla (y se conserva después).
 */
const props = defineProps<{ documento: Documento }>();

const ANCHO = 390;
const ALTO = 640;

const caja = useTemplateRef<HTMLDivElement>('caja');
const { width } = useElementSize(caja);
const visible = ref(false);
const escala = computed(() => (width.value || 1) / ANCHO);

const { stop } = useIntersectionObserver(
    caja,
    ([entrada]) => {
        if (entrada?.isIntersecting) {
            visible.value = true;
            stop();
        }
    },
    { rootMargin: '240px' },
);

watch(
    visible,
    (v) =>
        v && cargarFuentesCatalogo(urlFuentes(props.documento.tema.tipografia)),
    { immediate: true },
);
</script>

<template>
    <div
        ref="caja"
        class="relative aspect-[390/640] w-full overflow-hidden bg-muted"
        aria-hidden="true"
    >
        <div
            v-if="visible"
            class="pointer-events-none absolute top-0 left-0 origin-top-left select-none"
            :style="{
                width: `${ANCHO}px`,
                height: `${ALTO}px`,
                transform: `scale(${escala})`,
            }"
            inert
        >
            <RenderizadorPagina
                :documento="documento"
                modo="vista_previa"
                en-marco
                :cargar-fuentes="false"
            />
        </div>
        <div v-else class="absolute inset-0 animate-pulse bg-muted" />
    </div>
</template>
