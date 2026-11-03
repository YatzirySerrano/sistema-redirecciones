<script setup lang="ts">
import { CircleCheck, Gift, PartyPopper } from '@lucide/vue';
import { computed } from 'vue';
import {
    familia,
    fondoCss,
    plantillaMensaje,
    SOMBRAS,
    urlMedio,
} from './catalogo';
import { conAlfa } from './color';
import Decoracion from './Decoracion.vue';
import ProgresoVisitas from './ProgresoVisitas.vue';
import type { DocumentoDiseno, ProgramaPublico } from './tipos';

/**
 * Tarjeta digital de una campaña. Misma pieza en la vista previa del
 * editor y en la página pública: todo color o imagen viene del documento
 * saneado por el servidor y todo texto se muestra como texto (sin HTML).
 */
const props = defineProps<{
    diseno: DocumentoDiseno;
    programa: ProgramaPublico;
    negocio: string;
    consumidor: string | null;
    medios: Record<string, string>;
}>();

const t = computed(() => props.diseno.tarjeta);
const horizontal = computed(
    () => t.value.orientacion === 'horizontal' && props.programa.meta <= 12,
);
const logo = computed(() =>
    urlMedio(props.diseno.identidad.logo, props.medios),
);
const fondo = computed(() =>
    fondoCss(
        t.value.fondo_tipo,
        t.value.color1,
        t.value.color2,
        t.value.angulo,
        t.value.imagen,
        props.medios,
        t.value.oscurecer,
    ),
);
const titulo = computed(
    () =>
        props.diseno.programa.nombre || props.programa.campana || props.negocio,
);
const mensaje = computed(() => {
    const p = props.programa;
    const c = props.diseno.contenido;

    switch (p.estado) {
        case 'disponible':
            return c.mensaje_meta || '¡Recompensa disponible!';
        case 'canjeada':
            return c.mensaje_canje;
        case 'completada':
            return 'Completaste este programa. ¡Gracias por tu preferencia!';
        default:
            return plantillaMensaje(c.mensaje_progreso, {
                faltan: p.faltan,
                meta: p.meta,
                visitas: p.visitas,
                recompensa: p.recompensa,
            });
    }
});
const estadoChip = computed(
    () =>
        ({
            disponible: 'Recompensa lista',
            canjeada: 'Recompensa canjeada',
            completada: 'Completado',
            en_progreso: null,
        })[props.programa.estado],
);
</script>

<template>
    <article
        class="relative isolate overflow-hidden"
        :style="{
            background: fondo,
            color: t.texto,
            borderRadius: `${t.radio}px`,
            boxShadow: SOMBRAS[t.sombra]?.css ?? 'none',
            border:
                t.borde_ancho > 0
                    ? `${t.borde_ancho}px solid ${t.borde_color}`
                    : 'none',
            fontFamily: familia(diseno.tipografia.texto),
            '--fondo-tarjeta': t.color1,
        }"
        :aria-label="`Tarjeta ${titulo}: ${programa.visitas} de ${programa.meta} visitas`"
    >
        <Decoracion
            :tipo="t.decoracion"
            :color="t.texto"
            :opacidad="t.decoracion_opacidad"
        />
        <!-- Brillo sutil: acabado premium -->
        <div
            class="pointer-events-none absolute -top-1/2 -right-1/3 -z-0 size-[120%] rounded-full opacity-[0.07]"
            :style="{
                background: `radial-gradient(circle, ${t.texto} 0%, transparent 60%)`,
            }"
            aria-hidden="true"
        />

        <div
            class="relative p-5 sm:p-6"
            :class="
                horizontal
                    ? 'grid min-h-[13rem] grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] items-center gap-4'
                    : 'space-y-5'
            "
        >
            <header
                class="min-w-0"
                :class="
                    horizontal
                        ? 'flex h-full flex-col justify-between gap-3'
                        : 'flex items-start gap-3'
                "
            >
                <img
                    v-if="logo"
                    :src="logo"
                    alt=""
                    class="size-12 shrink-0 rounded-xl object-cover"
                    :style="{
                        boxShadow: `0 0 0 1px ${conAlfa(t.texto, 0.18)}`,
                    }"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                />
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-[0.68rem] font-semibold tracking-[0.2em] uppercase"
                        :style="{ color: t.texto_suave }"
                    >
                        {{ negocio }}
                    </p>
                    <h3
                        class="mt-1 text-[1.35rem] leading-tight font-semibold text-balance"
                        :style="{
                            fontFamily: familia(diseno.tipografia.titulos),
                        }"
                    >
                        {{ titulo }}
                    </h3>
                    <p
                        v-if="
                            diseno.contenido.mostrar_campana &&
                            programa.campana &&
                            programa.campana !== titulo
                        "
                        class="mt-0.5 truncate text-sm"
                        :style="{ color: t.texto_suave }"
                    >
                        {{ programa.campana }}
                    </p>
                </div>
                <div v-if="horizontal" class="space-y-1">
                    <p
                        v-if="diseno.progreso.mostrar_contador"
                        class="text-3xl font-semibold tabular-nums"
                    >
                        {{ programa.visitas
                        }}<span
                            class="text-base"
                            :style="{ color: t.texto_suave }"
                            >/{{ programa.meta }}</span
                        >
                    </p>
                    <p
                        v-if="consumidor"
                        class="truncate text-xs tracking-[0.14em] uppercase"
                        :style="{ color: t.texto_suave }"
                    >
                        {{ consumidor }}
                    </p>
                </div>
                <span
                    v-else-if="estadoChip"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :style="{
                        backgroundColor: diseno.progreso.completo_fondo,
                        color: diseno.progreso.completo_icono,
                    }"
                >
                    <CircleCheck class="size-3.5" aria-hidden="true" />
                    {{ estadoChip }}
                </span>
            </header>

            <ProgresoVisitas
                :progreso="diseno.progreso"
                :texto="t.texto"
                :texto-suave="t.texto_suave"
                :visitas="programa.visitas"
                :meta="programa.meta"
                :recompensa="programa.recompensa"
                :compacta="horizontal"
            />

            <footer
                v-if="!horizontal"
                class="flex items-end justify-between gap-3 text-sm"
            >
                <div class="min-w-0">
                    <p
                        v-if="diseno.progreso.mostrar_contador"
                        class="font-semibold tabular-nums"
                    >
                        {{ programa.visitas }} de {{ programa.meta }} visitas
                    </p>
                    <p
                        v-if="
                            diseno.contenido.mostrar_recompensa &&
                            diseno.progreso.presentacion !== 'premium'
                        "
                        class="mt-0.5 flex items-center gap-1.5"
                        :style="{ color: t.texto_suave }"
                    >
                        <Gift class="size-4 shrink-0" aria-hidden="true" />
                        <span class="min-w-0 truncate">{{
                            programa.recompensa
                        }}</span>
                    </p>
                </div>
                <p
                    v-if="consumidor"
                    class="max-w-[45%] shrink-0 truncate text-right text-xs tracking-[0.14em] uppercase"
                    :style="{ color: t.texto_suave }"
                >
                    {{ consumidor }}
                </p>
            </footer>
        </div>

        <!-- Mensaje según el estado real -->
        <div
            v-if="mensaje || programa.recompensas.length"
            class="relative border-t px-5 py-4 text-sm sm:px-6"
            :style="{
                borderColor: conAlfa(t.texto, 0.14),
                backgroundColor:
                    programa.estado === 'disponible'
                        ? conAlfa(diseno.progreso.completo_fondo, 0.22)
                        : conAlfa(t.texto, 0.05),
            }"
            :role="programa.estado === 'disponible' ? 'status' : undefined"
        >
            <p class="flex items-start gap-2 font-medium text-pretty">
                <PartyPopper
                    v-if="programa.estado === 'disponible'"
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ mensaje }}</span>
            </p>
            <ul v-if="programa.recompensas.length" class="mt-2 space-y-1">
                <li
                    v-for="(r, i) in programa.recompensas"
                    :key="i"
                    class="flex items-center gap-2"
                >
                    <Gift class="size-4 shrink-0" aria-hidden="true" />
                    <span class="font-semibold">{{ r.descripcion }}</span>
                    <span
                        v-if="r.vence_en"
                        class="text-xs"
                        :style="{ color: t.texto_suave }"
                        >· vence el {{ r.vence_en }}</span
                    >
                </li>
            </ul>
        </div>
    </article>
</template>
