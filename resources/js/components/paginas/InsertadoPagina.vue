<script setup lang="ts">
import { CirclePlay, MapPin, Music } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Reproductor o mapa de un proveedor externo. No se carga nada del
 * proveedor hasta que la persona lo pide (privacidad y velocidad).
 */
const props = defineProps<{
    src: string | null;
    titulo: string;
    tipo: 'video' | 'musica' | 'mapa';
    proveedor: string;
    alto?: number;
}>();

const cargado = ref(false);
const NOMBRES: Record<string, string> = {
    youtube: 'YouTube',
    vimeo: 'Vimeo',
    spotify: 'Spotify',
    soundcloud: 'SoundCloud',
    apple: 'Apple Music',
    google: 'Google Maps',
};
const icono = computed(() =>
    props.tipo === 'mapa'
        ? MapPin
        : props.tipo === 'musica'
          ? Music
          : CirclePlay,
);
const accion = computed(() =>
    props.tipo === 'mapa'
        ? 'Ver mapa'
        : props.tipo === 'musica'
          ? 'Escuchar'
          : 'Reproducir',
);
</script>

<template>
    <div
        class="pe-insertado overflow-hidden rounded-(--pe-radio) border border-(--pe-borde)"
        :class="tipo === 'musica' ? '' : 'aspect-video'"
        :style="tipo === 'musica' ? { height: `${alto ?? 166}px` } : undefined"
    >
        <iframe
            v-if="cargado && src"
            :src="src"
            :title="titulo"
            class="size-full border-0"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            allow="
                autoplay;
                encrypted-media;
                picture-in-picture;
                fullscreen;
                clipboard-write;
            "
            allowfullscreen
        />
        <button
            v-else
            type="button"
            class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center"
            :disabled="!src"
            @click="cargado = true"
        >
            <span
                class="flex size-12 items-center justify-center rounded-full bg-(--pe-boton-fondo) text-(--pe-boton-texto) shadow-sm"
            >
                <component :is="icono" class="size-6" aria-hidden="true" />
            </span>
            <span class="font-semibold">{{
                src
                    ? `${accion}${titulo ? ` · ${titulo}` : ''}`
                    : 'Enlace pendiente'
            }}</span>
            <span v-if="src" class="text-[0.78em] opacity-70"
                >Se cargará desde {{ NOMBRES[proveedor] ?? proveedor }}</span
            >
        </button>
    </div>
</template>
