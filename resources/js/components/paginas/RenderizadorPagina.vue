<script setup lang="ts">
import { ArrowLeft, Mail, MapPin, Phone } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BloquePagina from './BloquePagina.vue';
import TextoEnriquecido from './TextoEnriquecido.vue';
import { asegurarFuentes, urlFuentes } from './catalogo';
import { esVisible, estiloFondo, hex, urlMedio, variablesTema } from './tema';
import type { Documento } from './tipos';

/**
 * Renderizador ÚNICO de la Página de enlaces: lo usan la vista previa del
 * editor y la página pública, así que lo publicado coincide con lo que se
 * ve al editar. Todo el estilo se acota a la raíz (.pe-raiz) mediante
 * variables CSS; usa consultas de contenedor (@container) en lugar de
 * consultas de pantalla para que la vista previa por dispositivo sea fiel.
 */
const props = withDefaults(
    defineProps<{
        documento: Documento;
        /** Slug de la subpágina actual ('inicio' para la principal). */
        pagina?: string;
        modo?: 'publico' | 'vista_previa';
        base?: string;
        contacto?: { accion: string; marca: string };
        qr?: string;
        /** Vista previa: el contenido se desplaza dentro del marco. */
        enMarco?: boolean;
    }>(),
    { pagina: 'inicio', modo: 'publico', base: '', enMarco: false },
);

const emit = defineEmits<{ navegar: [slug: string] }>();

const tema = computed(() => props.documento.tema);
const perfil = computed(() => props.documento.perfil);
const actual = computed(
    () =>
        props.documento.paginas.find((p) => p.slug === props.pagina) ??
        props.documento.paginas[0],
);
const esInicio = computed(() => actual.value?.slug === 'inicio');
const bloques = computed(() =>
    (actual.value?.bloques ?? []).filter(
        (b) => props.modo === 'publico' || esVisible(b),
    ),
);
const variables = computed(() => variablesTema(tema.value));
const fondo = computed(() => estiloFondo(tema.value));
const foto = computed(
    () => urlMedio(perfil.value.foto) ?? urlMedio(perfil.value.logo),
);
const logo = computed(() =>
    perfil.value.foto ? urlMedio(perfil.value.logo) : null,
);
const portada = computed(() => urlMedio(perfil.value.portada));
const encabezado = computed(() => tema.value.disposicion.encabezado);
const forma = computed(
    () =>
        ({
            circulo: 'rounded-full',
            redondeada: 'rounded-[28%]',
            cuadrada: 'rounded-md',
        })[tema.value.disposicion.forma_foto] ?? 'rounded-full',
);
const centrado = computed(() => tema.value.tipografia.alineacion === 'centro');

// Fondo de video: sin movimiento si la persona lo pidió o si ahorra datos.
const reproducirVideo = ref(false);
let consultas: MediaQueryList[] = [];

const evaluarVideo = () => {
    const f = tema.value.fondo;

    if (
        f.tipo !== 'video' ||
        !urlMedio(f.video) ||
        typeof window === 'undefined'
    ) {
        reproducirVideo.value = false;

        return;
    }

    const conexion = (
        navigator as Navigator & { connection?: { saveData?: boolean } }
    ).connection;
    const reducir = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    const movil = window.matchMedia('(max-width: 640px)').matches;
    reproducirVideo.value =
        !reducir && !conexion?.saveData && !(f.ahorro_datos && movil);
};

onMounted(() => {
    evaluarVideo();
    consultas = ['(prefers-reduced-motion: reduce)', '(max-width: 640px)'].map(
        (q) => window.matchMedia(q),
    );
    consultas.forEach((c) => c.addEventListener('change', evaluarVideo));
});
onBeforeUnmount(() =>
    consultas.forEach((c) => c.removeEventListener('change', evaluarVideo)),
);
watch(() => tema.value.fondo, evaluarVideo, { deep: true });

// Tipografías de fonts.bunny.net (sin rastreo).
watch(
    () => urlFuentes(tema.value.tipografia),
    (url) => asegurarFuentes(url),
    { immediate: true },
);

const navegar = (paginaId: string) => {
    const destino = props.documento.paginas.find((p) => p.id === paginaId);

    if (destino) {
        emit('navegar', destino.slug);
    }
};

const volver = (evento: MouseEvent) => {
    if (props.modo !== 'publico') {
        evento.preventDefault();
        emit('navegar', 'inicio');
    }
};

const telefono = computed(() => perfil.value.contacto?.telefono ?? '');
const email = computed(() => perfil.value.contacto?.email ?? '');
const direccion = computed(() => perfil.value.contacto?.direccion ?? '');
</script>

<template>
    <div
        class="pe-raiz @container relative isolate"
        :class="enMarco ? 'h-full overflow-hidden' : 'min-h-dvh'"
        :style="variables"
        :data-modo="modo"
    >
        <!-- Fondo -->
        <div
            class="pe-capa-fondo -z-10 overflow-hidden"
            :class="enMarco ? 'absolute inset-0' : 'fixed inset-0'"
            :style="fondo"
            aria-hidden="true"
        >
            <template
                v-if="
                    tema.fondo.tipo === 'imagen' && urlMedio(tema.fondo.imagen)
                "
            >
                <img
                    :src="urlMedio(tema.fondo.imagen) ?? undefined"
                    alt=""
                    class="size-full object-cover"
                    :style="
                        tema.fondo.desenfoque > 0
                            ? {
                                  filter: `blur(${tema.fondo.desenfoque}px)`,
                                  transform: 'scale(1.08)',
                              }
                            : undefined
                    "
                />
            </template>
            <template
                v-else-if="
                    tema.fondo.tipo === 'video' && urlMedio(tema.fondo.video)
                "
            >
                <video
                    v-if="reproducirVideo"
                    :src="urlMedio(tema.fondo.video) ?? undefined"
                    :poster="urlMedio(tema.fondo.poster) ?? undefined"
                    class="size-full object-cover"
                    autoplay
                    muted
                    loop
                    playsinline
                    disablepictureinpicture
                    preload="metadata"
                />
                <img
                    v-else-if="urlMedio(tema.fondo.poster)"
                    :src="urlMedio(tema.fondo.poster) ?? undefined"
                    alt=""
                    class="size-full object-cover"
                />
            </template>
            <div
                v-if="
                    (tema.fondo.tipo === 'imagen' ||
                        tema.fondo.tipo === 'video') &&
                    tema.fondo.opacidad > 0
                "
                class="absolute inset-0"
                :style="{
                    backgroundColor: hex(tema.fondo.superposicion, '#000000'),
                    opacity: tema.fondo.opacidad / 100,
                }"
            />
        </div>

        <div
            :class="
                enMarco
                    ? 'pe-desplazable h-full overflow-y-auto overscroll-contain'
                    : ''
            "
        >
            <main
                class="mx-auto w-full max-w-(--pe-ancho) px-5 pt-8 pb-16 @md:px-6 @md:pt-12"
                :class="centrado ? 'text-center' : 'text-left'"
            >
                <!-- Encabezado -->
                <header v-if="esInicio" class="mb-7">
                    <div
                        v-if="portada"
                        class="overflow-hidden"
                        :class="
                            encabezado === 'portada'
                                ? '-mx-5 -mt-8 aspect-[16/10] @md:-mx-6 @md:-mt-12 @md:aspect-[16/8] @md:rounded-b-[calc(var(--pe-radio)+10px)]'
                                : 'aspect-[16/7] rounded-[calc(var(--pe-radio)+6px)] border border-(--pe-borde)'
                        "
                    >
                        <img
                            :src="portada"
                            alt=""
                            class="size-full object-cover"
                        />
                    </div>

                    <div
                        :class="[
                            encabezado === 'izquierda'
                                ? 'flex items-center gap-4 text-left'
                                : 'flex flex-col',
                            encabezado !== 'izquierda' && centrado
                                ? 'items-center'
                                : '',
                            portada && encabezado !== 'izquierda'
                                ? '-mt-12'
                                : portada
                                  ? 'mt-5'
                                  : '',
                        ]"
                    >
                        <img
                            v-if="foto"
                            :src="foto"
                            :alt="perfil.nombre"
                            class="shrink-0 border-4 border-(--pe-fondo) bg-(--pe-tarjeta-fondo) object-cover shadow-sm"
                            :class="[
                                forma,
                                encabezado === 'izquierda'
                                    ? 'size-20'
                                    : 'size-24 @md:size-28',
                            ]"
                        />
                        <div
                            :class="
                                encabezado === 'izquierda'
                                    ? 'min-w-0'
                                    : foto
                                      ? 'mt-3'
                                      : ''
                            "
                        >
                            <img
                                v-if="logo"
                                :src="logo"
                                alt=""
                                class="mb-2 h-8 w-auto object-contain"
                                :class="
                                    centrado && encabezado !== 'izquierda'
                                        ? 'mx-auto'
                                        : ''
                                "
                            />
                            <h1
                                v-if="
                                    perfil.mostrar_nombre !== false &&
                                    perfil.nombre
                                "
                                class="pe-titulo text-[calc(1.45em*var(--pe-escala))] leading-tight text-balance"
                            >
                                {{ perfil.nombre }}
                            </h1>
                            <p
                                v-if="perfil.descripcion"
                                class="mt-1.5 max-w-[34rem] leading-relaxed text-pretty text-(--pe-suave)"
                                :class="
                                    centrado && encabezado !== 'izquierda'
                                        ? 'mx-auto'
                                        : ''
                                "
                            >
                                <TextoEnriquecido :texto="perfil.descripcion" />
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="telefono || email || direccion"
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-[0.85em] text-(--pe-suave)"
                        :class="centrado ? 'justify-center' : ''"
                    >
                        <li v-if="telefono">
                            <a
                                :href="
                                    modo === 'publico'
                                        ? `tel:+${telefono}`
                                        : undefined
                                "
                                class="inline-flex items-center gap-1.5 hover:underline"
                            >
                                <Phone class="size-3.5" aria-hidden="true" />+{{
                                    telefono
                                }}
                            </a>
                        </li>
                        <li v-if="email">
                            <a
                                :href="
                                    modo === 'publico'
                                        ? `mailto:${email}`
                                        : undefined
                                "
                                class="inline-flex items-center gap-1.5 hover:underline"
                            >
                                <Mail class="size-3.5" aria-hidden="true" />{{
                                    email
                                }}
                            </a>
                        </li>
                        <li
                            v-if="direccion"
                            class="inline-flex items-center gap-1.5"
                        >
                            <MapPin class="size-3.5" aria-hidden="true" />{{
                                direccion
                            }}
                        </li>
                    </ul>
                </header>

                <header
                    v-else
                    class="mb-6 grid grid-cols-1 gap-3"
                    :class="centrado ? 'justify-items-center' : ''"
                >
                    <a
                        :href="modo === 'publico' ? base || '/' : undefined"
                        class="inline-flex items-center gap-1.5 text-[0.88em] text-(--pe-suave) hover:underline"
                        @click="volver"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />{{
                            perfil.nombre || 'Inicio'
                        }}
                    </a>
                    <h1
                        class="pe-titulo text-[calc(1.35em*var(--pe-escala))] leading-tight text-balance"
                    >
                        {{ actual?.titulo }}
                    </h1>
                    <p v-if="actual?.descripcion" class="text-(--pe-suave)">
                        {{ actual.descripcion }}
                    </p>
                </header>

                <!-- Elementos -->
                <div
                    class="flex flex-col gap-(--pe-separacion)"
                    :class="centrado ? '' : 'text-left'"
                >
                    <BloquePagina
                        v-for="bloque in bloques"
                        :key="bloque.id"
                        :bloque="bloque"
                        :tema="tema"
                        :modo="modo"
                        :contacto="contacto"
                        :qr="qr"
                        @navegar="navegar"
                    />
                    <p
                        v-if="bloques.length === 0"
                        class="rounded-(--pe-radio) border border-dashed border-(--pe-borde) p-8 text-center text-[0.9em] text-(--pe-suave)"
                    >
                        {{
                            modo === 'publico'
                                ? 'Pronto habrá contenido aquí.'
                                : 'Agrega elementos para verlos aquí.'
                        }}
                    </p>
                </div>
            </main>
        </div>
    </div>
</template>

<style>
/*
 * Estilos del renderizador, todos bajo .pe-raiz (no afectan al panel ni a
 * otras páginas). Los colores vienen de variables definidas en la raíz.
 */
.pe-raiz {
    color: var(--pe-texto);
    font-family: var(--pe-fuente);
    font-size: var(--pe-tamano);
    font-weight: var(--pe-peso);
    letter-spacing: var(--pe-espaciado);
    -webkit-font-smoothing: antialiased;
    --b-fondo: var(--pe-boton-fondo);
    --b-texto: var(--pe-boton-texto);
    --b-borde: var(--pe-boton-borde);
    --b-hover-fondo: var(--pe-boton-hover-fondo);
    --b-hover-texto: var(--pe-boton-hover-texto);
}

.pe-raiz *:focus-visible {
    outline: 2px solid var(--pe-acento);
    outline-offset: 3px;
}

.pe-raiz .pe-titulo {
    color: var(--pe-titulos);
    font-family: var(--pe-fuente-titulos);
    font-weight: var(--pe-peso-titulos);
    letter-spacing: calc(var(--pe-espaciado) - 0.01em);
}

.pe-raiz .pe-desplazable {
    scrollbar-width: thin;
}

/* Botones */
.pe-raiz .pe-boton {
    position: relative;
    display: grid;
    grid-template-columns: 2.5rem minmax(0, 1fr) 2.5rem;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    min-height: var(--pe-alto-boton);
    padding: 0.55rem 0.75rem;
    border: var(--pe-borde-ancho) solid var(--b-borde);
    border-radius: var(--pe-radio);
    background: var(--b-fondo);
    color: var(--b-texto);
    box-shadow: var(--pe-sombra);
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition:
        transform 0.18s ease,
        background-color 0.18s ease,
        color 0.18s ease,
        box-shadow 0.18s ease;
    -webkit-tap-highlight-color: transparent;
}

.pe-raiz .pe-boton[data-alineacion='izquierda'] {
    grid-template-columns: auto minmax(0, 1fr) auto;
    text-align: left;
}

.pe-raiz .pe-boton[data-alineacion='izquierda'] .pe-boton-lado:empty {
    width: 0;
}

.pe-raiz .pe-boton-lado {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
}

.pe-raiz .pe-boton-texto {
    min-width: 0;
    overflow-wrap: anywhere;
}

.pe-raiz .pe-boton-grande {
    min-height: calc(var(--pe-alto-boton) + 0.75rem);
    font-size: 1.06em;
}

.pe-raiz .pe-boton-compacto {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.35rem;
    min-height: calc(var(--pe-alto-boton) + 1.5rem);
    padding: 0.85rem 0.6rem;
    font-size: 0.92em;
}

.pe-raiz .pe-boton:hover,
.pe-raiz .pe-boton:focus-visible {
    background: var(--b-hover-fondo);
    color: var(--b-hover-texto);
}

.pe-raiz .pe-boton[data-estilo='contorno'] {
    background: transparent;
    box-shadow: none;
}

.pe-raiz .pe-boton[data-estilo='suave'] {
    background: color-mix(in srgb, var(--b-fondo) 24%, transparent);
    border-color: color-mix(in srgb, var(--b-borde) 55%, transparent);
}

.pe-raiz .pe-boton[data-estilo='vidrio'] {
    background: color-mix(in srgb, var(--b-fondo) 16%, transparent);
    border-color: color-mix(in srgb, var(--b-borde) 38%, transparent);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
    backdrop-filter: blur(14px) saturate(1.3);
}

.pe-raiz .pe-boton[data-estilo='contorno']:hover,
.pe-raiz .pe-boton[data-estilo='suave']:hover,
.pe-raiz .pe-boton[data-estilo='vidrio']:hover {
    background: color-mix(in srgb, var(--b-hover-fondo) 30%, transparent);
    color: var(--b-hover-texto);
}

.pe-raiz .pe-boton[data-destacado] {
    border-color: var(--pe-acento);
    box-shadow:
        0 0 0 1px var(--pe-acento),
        var(--pe-sombra);
}

.pe-raiz .pe-boton:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.pe-raiz .pe-insignia {
    position: absolute;
    top: 0;
    right: 0.85rem;
    transform: translateY(-50%);
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    background: var(--pe-acento);
    font-size: 0.7em;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.5;
    text-transform: uppercase;
}

.pe-raiz .pe-boton[data-animacion='elevar']:hover {
    transform: translateY(-2px);
}

.pe-raiz .pe-boton[data-animacion='pulso'][data-continua] {
    animation: pe-pulso 2.6s ease-in-out infinite;
}

.pe-raiz .pe-boton[data-animacion='pulso']:hover {
    transform: scale(1.015);
}

.pe-raiz .pe-boton[data-animacion='brillo'] {
    overflow: hidden;
}

.pe-raiz .pe-boton[data-animacion='brillo']::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        105deg,
        transparent 35%,
        rgb(255 255 255 / 0.35) 50%,
        transparent 65%
    );
    transform: translateX(-120%);
    pointer-events: none;
}

.pe-raiz .pe-boton[data-animacion='brillo'][data-continua]::after {
    animation: pe-brillo 4.2s ease-in-out infinite;
}

.pe-raiz .pe-boton[data-animacion='brillo']:hover::after {
    animation: pe-brillo 0.9s ease-out 1;
}

.pe-raiz .pe-boton[data-animacion='brillo'] .pe-insignia {
    z-index: 1;
}

@keyframes pe-pulso {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.018);
    }
}

@keyframes pe-brillo {
    0% {
        transform: translateX(-120%);
    }
    60%,
    100% {
        transform: translateX(120%);
    }
}

/* Redes, tarjetas y formularios */
.pe-raiz .pe-red {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: var(--pe-borde-ancho) solid var(--b-borde);
    background: var(--b-fondo);
    color: var(--b-texto);
    box-shadow: var(--pe-sombra);
    transition: transform 0.18s ease;
}

.pe-raiz .pe-red:hover {
    transform: translateY(-2px);
    background: var(--b-hover-fondo);
    color: var(--b-hover-texto);
}

.pe-raiz .pe-tarjeta {
    border: 1px solid var(--pe-borde);
    border-radius: calc(var(--pe-radio) + 4px);
    background: var(--pe-tarjeta-fondo);
    color: var(--pe-tarjeta-texto);
    text-align: left;
}

.pe-raiz .pe-tarjeta .pe-titulo {
    color: inherit;
}

.pe-raiz .pe-insertado {
    background: color-mix(in srgb, var(--pe-tarjeta-fondo) 85%, transparent);
    color: var(--pe-tarjeta-texto);
}

.pe-raiz .pe-campo {
    width: 100%;
    min-height: 2.75rem;
    padding: 0 0.85rem;
    border: 1px solid var(--pe-borde);
    border-radius: calc(var(--pe-radio) * 0.7);
    background: color-mix(
        in srgb,
        var(--pe-tarjeta-fondo) 92%,
        var(--pe-texto)
    );
    color: var(--pe-tarjeta-texto);
    font: inherit;
}

.pe-raiz .pe-campo[aria-invalid] {
    border-color: #dc2626;
}

.pe-raiz .pe-error {
    color: #dc2626;
    font-size: 0.82em;
}

.pe-raiz .pe-trampa {
    position: absolute;
    left: -10000px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}

.pe-raiz .pe-grupo[open] .pe-chevron {
    transform: rotate(180deg);
}

.pe-raiz .pe-grupo summary::-webkit-details-marker {
    display: none;
}

.pe-raiz .pe-pista {
    scrollbar-width: none;
}

.pe-raiz .pe-pista::-webkit-scrollbar {
    display: none;
}

.pe-raiz .pe-flecha {
    position: absolute;
    top: 50%;
    display: none;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    transform: translateY(-50%);
    border-radius: 999px;
    background: var(--pe-tarjeta-fondo);
    color: var(--pe-tarjeta-texto);
    box-shadow: 0 2px 10px rgb(0 0 0 / 0.18);
}

@container (min-width: 32rem) {
    .pe-raiz .pe-flecha {
        display: inline-flex;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pe-raiz .pe-boton,
    .pe-raiz .pe-boton::after,
    .pe-raiz .pe-red {
        animation: none !important;
        transition: none !important;
        transform: none !important;
    }
}
</style>
