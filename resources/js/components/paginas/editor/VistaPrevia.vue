<script setup lang="ts">
import { Monitor, Smartphone, Tablet } from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import RenderizadorPagina from '../RenderizadorPagina.vue';
import type { Documento } from '../tipos';

/**
 * Vista previa fiel: el mismo renderizador de la página pública dentro de
 * un marco con el ancho real del dispositivo, escalado para caber.
 */
const props = defineProps<{
    documento: Documento;
    qr?: string;
    alto?: string;
    /** false en el catálogo de plantillas (carga sus propias tipografías). */
    cargarFuentes?: boolean;
}>();

const pagina = defineModel<string>('pagina', { default: 'inicio' });

const DISPOSITIVOS = {
    movil: { etiqueta: 'Móvil', icono: Smartphone, ancho: 390, alto: 844 },
    tableta: { etiqueta: 'Tableta', icono: Tablet, ancho: 820, alto: 1100 },
    escritorio: {
        etiqueta: 'Escritorio',
        icono: Monitor,
        ancho: 1280,
        alto: 820,
    },
} as const;

const dispositivo = ref<keyof typeof DISPOSITIVOS>('movil');
const lienzo = useTemplateRef<HTMLDivElement>('lienzo');
const { width, height } = useElementSize(lienzo);
const medidas = computed(() => DISPOSITIVOS[dispositivo.value]);
const escala = computed(() =>
    Math.min(
        1,
        (width.value || 1) / medidas.value.ancho,
        (height.value || 1) / medidas.value.alto,
    ),
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-medium">Vista previa</p>
            <div
                role="radiogroup"
                aria-label="Dispositivo"
                class="flex gap-1 rounded-lg bg-muted p-1"
            >
                <button
                    v-for="(d, clave) in DISPOSITIVOS"
                    :key="clave"
                    type="button"
                    role="radio"
                    :aria-checked="dispositivo === clave"
                    :aria-label="d.etiqueta"
                    :title="d.etiqueta"
                    class="flex size-10 items-center justify-center rounded-md transition"
                    :class="
                        dispositivo === clave
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="dispositivo = clave"
                >
                    <component :is="d.icono" class="size-4" />
                </button>
            </div>
        </div>
        <div
            ref="lienzo"
            class="relative min-h-0 flex-1"
            :style="props.alto ? { height: props.alto } : undefined"
        >
            <div
                class="absolute top-0 left-1/2 overflow-hidden border-[6px] border-foreground/85 bg-background shadow-xl"
                :class="
                    dispositivo === 'escritorio'
                        ? 'rounded-xl'
                        : 'rounded-[2rem]'
                "
                :style="{
                    width: `${medidas.ancho}px`,
                    height: `${medidas.alto}px`,
                    transform: `translateX(-50%) scale(${escala})`,
                    transformOrigin: 'top center',
                }"
            >
                <RenderizadorPagina
                    :documento="documento"
                    :pagina="pagina"
                    modo="vista_previa"
                    :qr="qr"
                    en-marco
                    :cargar-fuentes="props.cargarFuentes !== false"
                    @navegar="pagina = $event"
                />
            </div>
        </div>
        <p class="text-center text-xs text-muted-foreground">
            Así se verá al publicar. Los botones no navegan en la vista previa.
        </p>
    </div>
</template>
