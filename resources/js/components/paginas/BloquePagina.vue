<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { ICONOS_REDES } from './iconosRedes';
import { REDES } from './catalogo';
import BotonPagina from './BotonPagina.vue';
import CarruselPagina from './CarruselPagina.vue';
import FormularioContacto from './FormularioContacto.vue';
import InsertadoPagina from './InsertadoPagina.vue';
import TextoEnriquecido from './TextoEnriquecido.vue';
import { altoMusica, textoPlano, urlInsertado, urlMapa } from './contenido';
import { esVisible, urlMedio } from './tema';
import type { Bloque, Tema } from './tipos';

/**
 * Un elemento de la página. El mismo componente se usa en la vista previa
 * del editor y en la página pública; solo cambia `modo`.
 */
const props = defineProps<{
    bloque: Bloque;
    tema: Tema;
    modo: 'publico' | 'vista_previa';
    contacto?: { accion: string; marca: string };
    qr?: string;
    compacto?: boolean;
}>();

const emit = defineEmits<{ navegar: [paginaId: string] }>();

const BOTONES = ['enlace', 'whatsapp', 'telefono', 'correo', 'subpagina'];
const alineacion = computed(() => {
    const a = props.bloque.alineacion;

    return a === 'centro' ||
        (a !== 'izquierda' && props.tema.tipografia.alineacion === 'centro')
        ? 'text-center'
        : 'text-left';
});
const hijos = computed(() =>
    (props.bloque.bloques ?? []).filter(
        (b) => props.modo === 'publico' || esVisible(b),
    ),
);
const enlace = (href?: string | null) =>
    props.modo === 'publico' ? (href ?? undefined) : undefined;
const prevenir = (e: Event) => props.modo !== 'publico' && e.preventDefault();
const proporcion = (p?: string) =>
    ({
        cuadrada: 'aspect-square',
        horizontal: 'aspect-[16/10]',
        vertical: 'aspect-[4/5]',
    })[p ?? ''] ?? '';
const tamanosTitulo: Record<string, string> = {
    grande: 'text-[calc(1.6em*var(--pe-escala))]',
    mediano: 'text-[calc(1.25em*var(--pe-escala))]',
    pequeno: 'text-[calc(1em*var(--pe-escala))]',
};
</script>

<template>
    <BotonPagina
        v-if="BOTONES.includes(bloque.tipo)"
        :bloque="bloque"
        :tema="tema"
        :modo="modo"
        :compacto="compacto"
        @navegar="emit('navegar', $event)"
    />

    <h2
        v-else-if="bloque.tipo === 'titulo'"
        class="pe-titulo mt-2 text-balance"
        :class="[
            alineacion,
            tamanosTitulo[String(bloque.tamano)] ?? tamanosTitulo.mediano,
        ]"
    >
        {{ bloque.texto }}
    </h2>

    <p
        v-else-if="bloque.tipo === 'texto'"
        class="leading-relaxed text-pretty"
        :class="[
            alineacion,
            bloque.tamano === 'pequeno'
                ? 'text-[0.88em]'
                : bloque.tamano === 'grande'
                  ? 'text-[1.12em]'
                  : '',
        ]"
    >
        <TextoEnriquecido :texto="bloque.texto ?? ''" />
    </p>

    <div
        v-else-if="bloque.tipo === 'separador'"
        class="flex items-center justify-center"
        :style="{ minHeight: `${bloque.alto ?? 24}px` }"
        role="separator"
    >
        <hr
            v-if="bloque.estilo === 'linea'"
            class="w-full border-0 border-t border-(--pe-borde)"
        />
        <span
            v-else-if="bloque.estilo === 'puntos'"
            class="tracking-[0.6em] text-(--pe-suave)"
            aria-hidden="true"
            >•••</span
        >
    </div>

    <nav v-else-if="bloque.tipo === 'redes'" aria-label="Redes sociales">
        <ul
            v-if="bloque.estilo !== 'botones'"
            class="flex flex-wrap gap-2.5"
            :class="
                tema.tipografia.alineacion === 'centro'
                    ? 'justify-center'
                    : 'justify-start'
            "
        >
            <li v-for="e in bloque.elementos ?? []" :key="e.id">
                <a
                    :href="enlace(e.href)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="pe-red"
                    :class="bloque.tamano === 'grande' ? 'size-14' : 'size-11'"
                    :aria-label="REDES[e.red]?.etiqueta ?? 'Red social'"
                    :title="REDES[e.red]?.etiqueta"
                    @click="prevenir"
                >
                    <component
                        :is="ICONOS_REDES[e.red] ?? ICONOS_REDES.sitio"
                        :class="
                            bloque.tamano === 'grande' ? 'size-6' : 'size-5'
                        "
                    />
                </a>
            </li>
        </ul>
        <div v-else class="grid grid-cols-1 gap-(--pe-separacion)">
            <BotonPagina
                v-for="e in bloque.elementos ?? []"
                :key="e.id"
                :bloque="{
                    id: e.id,
                    tipo: 'enlace',
                    titulo: REDES[e.red]?.etiqueta ?? 'Red social',
                    icono: `red:${e.red}`,
                    href: e.href,
                }"
                :tema="tema"
                :modo="modo"
            />
        </div>
    </nav>

    <section
        v-else-if="bloque.tipo === 'mapa'"
        class="pe-tarjeta grid grid-cols-1 gap-3 p-4"
    >
        <div>
            <h2 v-if="bloque.titulo" class="pe-titulo text-[1.05em]">
                {{ bloque.titulo }}
            </h2>
            <p class="text-[0.92em] opacity-85">
                {{ bloque.direccion || 'Escribe la dirección' }}
            </p>
        </div>
        <InsertadoPagina
            v-if="bloque.mostrar_mapa && bloque.direccion"
            :src="urlMapa(bloque.direccion)"
            :titulo="`Mapa: ${bloque.direccion}`"
            tipo="mapa"
            proveedor="google"
        />
        <BotonPagina
            :bloque="{
                id: bloque.id,
                tipo: 'enlace',
                titulo: bloque.boton || 'Cómo llegar',
                icono: 'mapa',
                href: bloque.href,
            }"
            :tema="tema"
            :modo="modo"
        />
    </section>

    <figure v-else-if="bloque.tipo === 'video'" class="grid grid-cols-1 gap-2">
        <video
            v-if="bloque.fuente === 'archivo' && urlMedio(bloque.archivo)"
            :src="urlMedio(bloque.archivo) ?? undefined"
            :poster="urlMedio(bloque.poster) ?? undefined"
            class="aspect-video w-full rounded-(--pe-radio) border border-(--pe-borde) bg-black object-contain"
            controls
            playsinline
            preload="metadata"
        />
        <InsertadoPagina
            v-else
            :src="
                bloque.proveedor && bloque.ref
                    ? urlInsertado(bloque.proveedor, bloque.ref)
                    : null
            "
            :titulo="bloque.titulo || 'Video'"
            tipo="video"
            :proveedor="bloque.proveedor ?? ''"
        />
        <figcaption
            v-if="bloque.titulo"
            class="text-[0.88em] opacity-80"
            :class="alineacion"
        >
            {{ bloque.titulo }}
        </figcaption>
    </figure>

    <figure v-else-if="bloque.tipo === 'musica'" class="grid grid-cols-1 gap-2">
        <figcaption
            v-if="bloque.titulo"
            class="pe-titulo text-[1.05em]"
            :class="alineacion"
        >
            {{ bloque.titulo }}
        </figcaption>
        <InsertadoPagina
            :src="
                bloque.proveedor && bloque.ref
                    ? urlInsertado(bloque.proveedor, bloque.ref)
                    : null
            "
            :titulo="bloque.titulo || 'Música'"
            tipo="musica"
            :proveedor="bloque.proveedor ?? ''"
            :alto="altoMusica(bloque.proveedor ?? '', bloque.ref ?? '')"
        />
    </figure>

    <component
        :is="bloque.href ? 'a' : 'figure'"
        v-else-if="bloque.tipo === 'imagen'"
        :href="bloque.href ? enlace(bloque.href) : undefined"
        :target="bloque.href ? '_blank' : undefined"
        :rel="bloque.href ? 'noopener noreferrer' : undefined"
        class="block"
        @click="prevenir"
    >
        <img
            v-if="urlMedio(bloque.archivo)"
            :src="urlMedio(bloque.archivo) ?? undefined"
            :alt="bloque.alt ?? ''"
            :width="bloque.archivo?.ancho ?? undefined"
            :height="bloque.archivo?.alto ?? undefined"
            class="h-auto w-full object-cover"
            :class="[
                proporcion(bloque.proporcion),
                bloque.redondeo !== false ? 'rounded-(--pe-radio)' : '',
            ]"
            loading="lazy"
            decoding="async"
        />
        <div
            v-else
            class="rounded-(--pe-radio) border border-dashed border-(--pe-borde) p-8 text-center text-[0.85em] opacity-70"
        >
            Elige una imagen
        </div>
    </component>

    <CarruselPagina
        v-else-if="bloque.tipo === 'carrusel'"
        :bloque="bloque"
        :modo="modo"
    />

    <section
        v-else-if="bloque.tipo === 'grupo'"
        :aria-label="bloque.titulo || undefined"
    >
        <details
            v-if="bloque.plegable"
            class="pe-grupo"
            :open="bloque.abierto !== false"
        >
            <summary
                class="pe-titulo flex cursor-pointer list-none items-center justify-between gap-2 py-2 text-[1.05em]"
            >
                <span>{{ bloque.titulo || 'Más enlaces' }}</span>
                <ChevronDown
                    class="pe-chevron size-5 transition-transform"
                    aria-hidden="true"
                />
            </summary>
            <div
                class="mt-2 grid grid-cols-1 gap-(--pe-separacion)"
                :class="
                    bloque.disposicion === 'cuadricula'
                        ? bloque.columnas === 3
                            ? 'grid-cols-2 @lg:grid-cols-3'
                            : 'grid-cols-2'
                        : ''
                "
            >
                <BloquePagina
                    v-for="hijo in hijos"
                    :key="hijo.id"
                    :bloque="hijo"
                    :tema="tema"
                    :modo="modo"
                    :compacto="bloque.disposicion === 'cuadricula'"
                    @navegar="emit('navegar', $event)"
                />
            </div>
        </details>
        <template v-else>
            <h2
                v-if="bloque.titulo"
                class="pe-titulo mb-2 text-[1.05em]"
                :class="alineacion"
            >
                {{ bloque.titulo }}
            </h2>
            <div
                class="grid grid-cols-1 gap-(--pe-separacion)"
                :class="
                    bloque.disposicion === 'cuadricula'
                        ? bloque.columnas === 3
                            ? 'grid-cols-2 @lg:grid-cols-3'
                            : 'grid-cols-2'
                        : ''
                "
            >
                <BloquePagina
                    v-for="hijo in hijos"
                    :key="hijo.id"
                    :bloque="hijo"
                    :tema="tema"
                    :modo="modo"
                    :compacto="bloque.disposicion === 'cuadricula'"
                    @navegar="emit('navegar', $event)"
                />
            </div>
        </template>
    </section>

    <article
        v-else-if="bloque.tipo === 'tarjeta'"
        class="pe-tarjeta overflow-hidden"
    >
        <img
            v-if="urlMedio(bloque.imagen)"
            :src="urlMedio(bloque.imagen) ?? undefined"
            :alt="textoPlano(bloque.titulo ?? '')"
            class="aspect-[16/9] w-full object-cover"
            loading="lazy"
            decoding="async"
        />
        <div class="grid grid-cols-1 gap-2 p-5">
            <h2 class="pe-titulo text-[1.1em]">{{ bloque.titulo }}</h2>
            <p
                v-if="bloque.texto"
                class="leading-relaxed text-pretty opacity-90"
            >
                <TextoEnriquecido :texto="bloque.texto" />
            </p>
            <BotonPagina
                v-if="bloque.boton && (bloque.href || modo !== 'publico')"
                class="mt-2"
                :bloque="{
                    id: bloque.id,
                    tipo: 'enlace',
                    titulo: bloque.boton,
                    href: bloque.href,
                }"
                :tema="tema"
                :modo="modo"
            />
        </div>
    </article>

    <FormularioContacto
        v-else-if="bloque.tipo === 'contacto'"
        :bloque="bloque"
        :modo="modo"
        :contacto="contacto"
    />

    <section
        v-else-if="bloque.tipo === 'qr'"
        class="pe-tarjeta flex flex-col items-center gap-3 p-5 text-center"
    >
        <h2 v-if="bloque.titulo" class="pe-titulo text-[1.05em]">
            {{ bloque.titulo }}
        </h2>
        <img
            v-if="qr"
            :src="qr"
            alt="Código QR de esta página"
            class="rounded-lg bg-white p-2"
            :style="{
                width: `${Number(bloque.tamano) || 180}px`,
                height: `${Number(bloque.tamano) || 180}px`,
            }"
            loading="lazy"
        />
        <p v-if="bloque.descripcion" class="text-[0.88em] opacity-80">
            {{ bloque.descripcion }}
        </p>
    </section>
</template>
