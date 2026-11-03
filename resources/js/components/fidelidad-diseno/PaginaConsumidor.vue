<script setup lang="ts">
import { Info, LockKeyhole } from '@lucide/vue';
import { computed } from 'vue';
import { familia, fondoCss, urlMedio } from './catalogo';
import { conAlfa } from './color';
import Decoracion from './Decoracion.vue';
import TarjetaPrograma from './TarjetaPrograma.vue';
import type { DatosPublicos, DocumentoDiseno } from './tipos';

/**
 * Página del consumidor (solo lectura). La usa la página pública y la vista
 * previa del editor. Recibe únicamente datos no sensibles.
 */
const props = defineProps<{
    datos: DatosPublicos;
    /** En el editor: sin enlaces activos ni altura de pantalla completa. */
    vistaPrevia?: boolean;
}>();

const g = computed(() => props.datos.disenos.general);
const portada = computed(() =>
    urlMedio(g.value.identidad.portada, props.datos.medios),
);
const logo = computed(() =>
    urlMedio(g.value.identidad.logo, props.datos.medios),
);
const fondo = computed(() =>
    fondoCss(
        g.value.pagina.fondo_tipo,
        g.value.pagina.color1,
        g.value.pagina.color2,
        g.value.pagina.angulo,
        g.value.pagina.imagen,
        props.datos.medios,
        20,
    ),
);
const disenoDe = (variante: number | null): DocumentoDiseno =>
    (variante !== null ? props.datos.disenos.variantes[variante] : null) ??
    g.value;
const saludo = computed(() =>
    g.value.contenido.saludo === 'alias' ? props.datos.consumidor : null,
);
const terminos = computed(() => g.value.contenido.terminos.trim());
</script>

<template>
    <div
        class="relative isolate w-full overflow-hidden"
        :class="vistaPrevia ? 'min-h-full' : 'min-h-dvh'"
        :style="{
            background: fondo,
            color: g.colores.texto,
            fontFamily: familia(g.tipografia.texto),
        }"
    >
        <Decoracion
            :tipo="g.pagina.decoracion"
            :color="g.colores.primario"
            :opacidad="10"
        />

        <div
            v-if="portada"
            class="relative h-44 w-full overflow-hidden sm:h-56"
            aria-hidden="true"
        >
            <img
                :src="portada"
                alt=""
                class="size-full object-cover"
                decoding="async"
                referrerpolicy="no-referrer"
            />
            <div
                class="absolute inset-0"
                :style="{
                    background: `linear-gradient(to bottom, transparent 40%, ${g.pagina.color1})`,
                }"
            />
        </div>

        <main
            class="relative mx-auto w-full max-w-md px-5 pb-10"
            :class="portada ? '-mt-12' : 'pt-10'"
        >
            <header class="text-center">
                <img
                    v-if="logo"
                    :src="logo"
                    alt=""
                    class="mx-auto size-20 rounded-2xl object-cover"
                    :style="{
                        boxShadow: `0 10px 30px -12px ${conAlfa(g.colores.texto, 0.45)}, 0 0 0 4px ${g.colores.superficie}`,
                    }"
                    decoding="async"
                    referrerpolicy="no-referrer"
                />
                <p
                    v-if="g.identidad.mostrar_negocio"
                    class="mt-4 text-xs font-semibold tracking-[0.22em] uppercase"
                    :style="{ color: g.colores.texto_suave }"
                >
                    {{ datos.negocio }}
                </p>
                <h1
                    class="mt-1.5 text-3xl leading-tight font-semibold text-balance"
                    :style="{ fontFamily: familia(g.tipografia.titulos) }"
                >
                    {{ g.programa.nombre || datos.negocio }}
                </h1>
                <p
                    v-if="g.programa.lema"
                    class="mx-auto mt-2 max-w-xs text-sm text-pretty"
                    :style="{ color: g.colores.texto_suave }"
                >
                    {{ g.programa.lema }}
                </p>
                <p v-if="saludo" class="mt-5 text-base">
                    Hola, <span class="font-semibold">{{ saludo }}</span>
                </p>
            </header>

            <p
                v-if="datos.aviso"
                class="mt-6 flex items-start gap-2 rounded-2xl px-4 py-3 text-sm"
                role="status"
                :style="{
                    backgroundColor: g.colores.superficie,
                    boxShadow: `inset 0 0 0 1px ${g.colores.borde}`,
                }"
            >
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ datos.aviso }}
            </p>

            <section class="mt-7 space-y-5" aria-label="Tu progreso">
                <TarjetaPrograma
                    v-for="(p, i) in datos.programas"
                    :key="i"
                    :diseno="disenoDe(p.variante)"
                    :programa="p"
                    :negocio="datos.negocio"
                    :consumidor="saludo"
                    :medios="datos.medios"
                />
                <p
                    v-if="datos.programas.length === 0"
                    class="rounded-2xl px-5 py-6 text-center text-sm"
                    :style="{
                        backgroundColor: g.colores.superficie,
                        color: g.colores.texto_suave,
                        boxShadow: `inset 0 0 0 1px ${g.colores.borde}`,
                    }"
                >
                    Aún no tienes visitas registradas. Se sumarán cuando el
                    negocio confirme tu compra o servicio.
                </p>
            </section>

            <section
                v-for="(s, i) in g.contenido.secciones"
                :key="`s${i}`"
                class="mt-5 rounded-2xl px-5 py-5"
                :style="{
                    backgroundColor: g.colores.superficie,
                    boxShadow: `inset 0 0 0 1px ${g.colores.borde}`,
                }"
            >
                <h2
                    v-if="s.titulo"
                    class="text-lg font-semibold"
                    :style="{ fontFamily: familia(g.tipografia.titulos) }"
                >
                    {{ s.titulo }}
                </h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li
                        v-for="(e, j) in s.elementos"
                        :key="j"
                        class="flex gap-2.5"
                    >
                        <span
                            class="mt-1.5 size-1.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: g.colores.primario }"
                            aria-hidden="true"
                        />
                        <span class="text-pretty">{{ e }}</span>
                    </li>
                </ul>
            </section>

            <details
                v-if="terminos"
                class="group mt-5 rounded-2xl px-5 py-4 text-sm"
                :style="{
                    backgroundColor: conAlfa(g.colores.superficie, 0.7),
                    boxShadow: `inset 0 0 0 1px ${g.colores.borde}`,
                }"
            >
                <summary class="cursor-pointer font-medium select-none">
                    Términos y condiciones
                </summary>
                <p
                    class="mt-3 leading-relaxed whitespace-pre-line"
                    :style="{ color: g.colores.texto_suave }"
                >
                    {{ terminos }}
                </p>
            </details>

            <footer
                class="mt-10 space-y-2 text-center text-xs"
                :style="{ color: g.colores.texto_suave }"
            >
                <p class="inline-flex items-center gap-1.5">
                    <LockKeyhole class="size-3.5" aria-hidden="true" />
                    Página de solo consulta: el negocio registra tus visitas al
                    atenderte.
                </p>
                <p v-if="datos.recuperar_url">
                    ¿Perdiste tu enlace?
                    <a
                        v-if="!vistaPrevia"
                        :href="datos.recuperar_url"
                        class="font-medium underline underline-offset-2"
                        rel="nofollow"
                        >Recupéralo con tu correo</a
                    >
                    <span
                        v-else
                        class="font-medium underline underline-offset-2"
                        >Recupéralo con tu correo</span
                    >
                </p>
            </footer>
        </main>
    </div>
</template>
