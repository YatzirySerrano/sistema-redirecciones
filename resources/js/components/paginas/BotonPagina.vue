<script setup lang="ts">
import { computed } from 'vue';
import { primerPlano } from '@/lib/color';
import { componenteIcono } from './catalogo';
import { hex, urlMedio, variablesBoton } from './tema';
import type { Bloque, Tema } from './tipos';

/**
 * Botón de acción (enlace, WhatsApp, teléfono, correo o subpágina). En la
 * página pública usa la ruta de seguimiento del servidor; en la vista
 * previa no navega.
 */
const props = defineProps<{
    bloque: Bloque;
    tema: Tema;
    modo: 'publico' | 'vista_previa';
    compacto?: boolean;
}>();

const emit = defineEmits<{ navegar: [paginaId: string] }>();

const ICONOS_TIPO: Record<string, string> = {
    whatsapp: 'red:whatsapp',
    telefono: 'telefono',
    correo: 'correo',
    subpagina: 'flecha',
};

const icono = computed(() =>
    componenteIcono(props.bloque.icono ?? ICONOS_TIPO[props.bloque.tipo]),
);
const miniatura = computed(() => urlMedio(props.bloque.miniatura));
const posicion = computed(() => props.tema.botones.icono);
const animacion = computed(() =>
    !props.bloque.animacion || props.bloque.animacion === 'tema'
        ? props.tema.botones.animacion
        : props.bloque.animacion,
);
// Pulso y brillo continuos solo en destacados o si el elemento lo pide;
// con la animación del tema el resto los muestra al pasar el cursor.
const continua = computed(
    () =>
        props.bloque.destacado ||
        (props.bloque.animacion !== 'tema' && !!props.bloque.animacion),
);
const colorInsignia = computed(() =>
    primerPlano(hex(props.tema.colores.acento, '#111111')),
);

const externo = computed(() => {
    const href = props.bloque.href ?? '';

    return props.bloque.tipo !== 'subpagina' && href.includes('/ir/');
});

const enlace = computed(() =>
    props.modo === 'publico' ? (props.bloque.href ?? undefined) : undefined,
);

const alPulsar = (evento: MouseEvent) => {
    if (props.modo === 'vista_previa') {
        evento.preventDefault();

        if (props.bloque.tipo === 'subpagina' && props.bloque.pagina_id) {
            emit('navegar', props.bloque.pagina_id);
        }

        return;
    }

    // tel: y mailto: se abren directo; el clic se registra aparte.
    if (props.bloque.rastreo && navigator.sendBeacon) {
        navigator.sendBeacon(props.bloque.rastreo);
    }
};
</script>

<template>
    <a
        :href="enlace"
        :target="externo ? '_blank' : undefined"
        :rel="externo ? 'noopener noreferrer' : undefined"
        class="pe-boton"
        :class="[
            compacto ? 'pe-boton-compacto' : '',
            bloque.tamano === 'grande' ? 'pe-boton-grande' : '',
        ]"
        :data-estilo="tema.botones.estilo"
        :data-animacion="animacion"
        :data-continua="continua || undefined"
        :data-destacado="bloque.destacado || undefined"
        :data-alineacion="tema.botones.alineacion"
        :style="variablesBoton(bloque)"
        @click="alPulsar"
    >
        <span
            v-if="miniatura"
            class="pe-boton-lado"
            :class="posicion === 'derecha' && !compacto ? 'order-3' : ''"
        >
            <img
                :src="miniatura"
                alt=""
                class="size-10 rounded-[calc(var(--pe-radio)*0.6)] object-cover"
                loading="lazy"
                decoding="async"
            />
        </span>
        <span
            v-else-if="icono && posicion !== 'oculto'"
            class="pe-boton-lado"
            :class="posicion === 'derecha' && !compacto ? 'order-3' : ''"
        >
            <component :is="icono" class="size-5" aria-hidden="true" />
        </span>
        <span v-else-if="!compacto" class="pe-boton-lado" aria-hidden="true" />

        <span class="pe-boton-texto">
            <span class="block leading-snug font-semibold">{{
                bloque.titulo || 'Sin título'
            }}</span>
            <span
                v-if="bloque.subtitulo && !compacto"
                class="mt-0.5 block text-[0.85em] leading-snug opacity-75"
                >{{ bloque.subtitulo }}</span
            >
        </span>

        <span
            v-if="!compacto"
            class="pe-boton-lado"
            :class="posicion === 'derecha' ? 'order-1' : ''"
            aria-hidden="true"
        />

        <span
            v-if="bloque.insignia"
            class="pe-insignia"
            :style="{ color: colorInsignia }"
            >{{ bloque.insignia }}</span
        >
    </a>
</template>
