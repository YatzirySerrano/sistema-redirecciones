<script setup lang="ts">
import { Check, Eye, Search, SearchX, Sparkles, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { urlFuentes } from '../catalogo';
import MiniaturaPlantilla from './MiniaturaPlantilla.vue';
import { cargarFuentesCatalogo, normalizar } from './plantillas';
import type {
    CategoriasPlantillas,
    ModoPlantilla,
    PlantillaPagina,
} from './plantillas';
import VistaPrevia from './VistaPrevia.vue';

/**
 * Catálogo de plantillas: filtros por giro y estilo, buscador, miniaturas
 * reales (mismo renderizador que la página pública), vista previa ampliada
 * y confirmación de qué se reemplaza.
 */
const abierto = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    plantillas: PlantillaPagina[];
    categorias: CategoriasPlantillas;
    editable: boolean;
    /** Elementos actuales en «Inicio» (para sugerir el modo). */
    elementosInicio: number;
}>();

const emit = defineEmits<{
    aplicar: [plantilla: PlantillaPagina, modo: ModoPlantilla];
}>();

// ---- Filtros ----
const busqueda = ref('');
const giro = ref('');
const estilo = ref('');

const textoDe = (p: PlantillaPagina) =>
    normalizar(
        [
            p.nombre,
            p.descripcion,
            ...p.rasgos,
            props.categorias.estilos[p.estilo] ?? '',
            ...p.giros.map((g) => props.categorias.giros[g] ?? ''),
        ].join(' '),
    );

const filtradas = computed(() => {
    const terminos = normalizar(busqueda.value).split(/\s+/).filter(Boolean);

    return props.plantillas.filter((p) => {
        if (giro.value && !p.giros.includes(giro.value)) {
            return false;
        }

        if (estilo.value && p.estilo !== estilo.value) {
            return false;
        }

        const texto = textoDe(p);

        return terminos.every((t) => texto.includes(t));
    });
});

const hayFiltros = computed(
    () => !!busqueda.value.trim() || !!giro.value || !!estilo.value,
);
const limpiar = () => {
    busqueda.value = '';
    giro.value = '';
    estilo.value = '';
};

// Solo se ofrecen los giros y estilos que tienen plantillas.
const girosDisponibles = computed(() =>
    Object.entries(props.categorias.giros).filter(([clave]) =>
        props.plantillas.some((p) => p.giros.includes(clave)),
    ),
);
const estilosDisponibles = computed(() =>
    Object.entries(props.categorias.estilos).filter(([clave]) =>
        props.plantillas.some((p) => p.estilo === clave),
    ),
);

// ---- Vista previa ampliada ----
const enVista = ref<PlantillaPagina | null>(null);
const paginaVista = ref('inicio');
watch(enVista, (p) => {
    paginaVista.value = 'inicio';

    if (p) {
        cargarFuentesCatalogo(urlFuentes(p.documento.tema.tipografia));
    }
});

// ---- Confirmación ----
const elegida = ref<PlantillaPagina | null>(null);
const modo = ref<ModoPlantilla>('diseno');

const elegir = (p: PlantillaPagina) => {
    elegida.value = p;
    // Página casi vacía: se sugiere usar también el contenido de ejemplo.
    modo.value = props.elementosInicio <= 2 ? 'reemplazar' : 'diseno';
};

const MODOS: { valor: ModoPlantilla; titulo: string; detalle: string }[] = [
    {
        valor: 'diseno',
        titulo: 'Solo el diseño',
        detalle:
            'Cambian colores, fondo, letras, botones y encabezado. Tu contenido queda igual.',
    },
    {
        valor: 'agregar',
        titulo: 'Diseño y agregar ejemplos',
        detalle:
            'Además, los elementos de ejemplo se suman al final de «Inicio». Nada se borra.',
    },
    {
        valor: 'reemplazar',
        titulo: 'Diseño y reemplazar «Inicio»',
        detalle:
            'Los elementos de «Inicio» se sustituyen por los de ejemplo. Tus otras páginas no cambian.',
    },
];

const confirmar = () => {
    if (!elegida.value) {
        return;
    }

    emit('aplicar', elegida.value, modo.value);
    elegida.value = null;
    enVista.value = null;
    abierto.value = false;
};

const giroCorto = (p: PlantillaPagina) =>
    p.giros
        .slice(0, 2)
        .map((g) => props.categorias.giros[g])
        .filter(Boolean)
        .join(' · ');
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent
            class="flex h-dvh max-h-dvh w-full max-w-none flex-col gap-0 overflow-hidden rounded-none p-0 sm:h-[92dvh] sm:max-w-[min(76rem,calc(100%-2rem))] sm:rounded-xl"
            :show-close-button="false"
        >
            <!-- En pantallas bajas (teléfono horizontal) filtros y rejilla se
                 desplazan juntos; en las demás los filtros quedan fijos. -->
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <!-- Encabezado y filtros -->
                <div
                    class="border-b bg-background px-4 pt-4 pb-3 sm:px-6 [@media(min-height:561px)]:sticky [@media(min-height:561px)]:top-0 [@media(min-height:561px)]:z-10"
                >
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <DialogTitle class="text-lg font-semibold"
                                >Plantillas de diseño</DialogTitle
                            >
                            <DialogDescription
                                class="text-sm [@media(max-height:560px)]:sr-only"
                                >Elige un punto de partida. Antes de aplicar te
                                diremos qué cambia, y podrás
                                deshacerlo.</DialogDescription
                            >
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-10 shrink-0"
                            aria-label="Cerrar plantillas"
                            @click="abierto = false"
                            ><X
                        /></Button>
                    </div>

                    <div class="relative mt-3">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="busqueda"
                            type="search"
                            class="h-11 pl-9"
                            placeholder="Buscar: café, barbería, elegante…"
                            aria-label="Buscar plantillas"
                        />
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2">
                        <div
                            class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:[@media(min-height:720px)]:flex-wrap"
                            role="group"
                            aria-label="Filtrar por tipo de negocio"
                        >
                            <button
                                v-for="[clave, etiqueta] in [
                                    ['', 'Todos los giros'],
                                    ...girosDisponibles,
                                ]"
                                :key="`g-${clave}`"
                                type="button"
                                class="min-h-10 shrink-0 rounded-full border px-3.5 text-sm font-medium transition"
                                :class="
                                    giro === clave
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background hover:bg-accent'
                                "
                                :aria-pressed="giro === clave"
                                @click="giro = clave"
                            >
                                {{ etiqueta }}
                            </button>
                        </div>
                        <div
                            class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:[@media(min-height:720px)]:flex-wrap"
                            role="group"
                            aria-label="Filtrar por estilo"
                        >
                            <button
                                v-for="[clave, etiqueta] in [
                                    ['', 'Todos los estilos'],
                                    ...estilosDisponibles,
                                ]"
                                :key="`e-${clave}`"
                                type="button"
                                class="min-h-10 shrink-0 rounded-full border border-dashed px-3.5 text-sm font-medium transition"
                                :class="
                                    estilo === clave
                                        ? 'border-solid border-foreground bg-foreground text-background'
                                        : 'bg-background hover:bg-accent'
                                "
                                :aria-pressed="estilo === clave"
                                @click="estilo = clave"
                            >
                                {{ etiqueta }}
                            </button>
                        </div>
                    </div>
                    <p
                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {{
                            filtradas.length === 1
                                ? '1 plantilla'
                                : `${filtradas.length} plantillas`
                        }}
                        <button
                            v-if="hayFiltros"
                            type="button"
                            class="min-h-8 font-medium text-primary underline-offset-2 hover:underline"
                            @click="limpiar"
                        >
                            Quitar filtros
                        </button>
                    </p>
                </div>

                <!-- Rejilla -->
                <div class="px-4 py-4 sm:px-6">
                    <EstadoVacio
                        v-if="filtradas.length === 0"
                        titulo="Ninguna plantilla coincide"
                        descripcion="Prueba con otra palabra o quita algún filtro. Cualquier plantilla se puede adaptar a tu giro."
                        :icono="SearchX"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11"
                            @click="limpiar"
                            >Ver todas las plantillas</Button
                        >
                    </EstadoVacio>
                    <ul
                        v-else
                        class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                    >
                        <li
                            v-for="p in filtradas"
                            :key="p.clave"
                            class="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card shadow-xs"
                        >
                            <button
                                type="button"
                                class="group relative block w-full text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                :aria-label="`Ver en grande la plantilla «${p.nombre}»`"
                                @click="enVista = p"
                            >
                                <MiniaturaPlantilla :documento="p.documento" />
                                <span
                                    class="absolute right-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-background/90 px-2.5 py-1 text-xs font-medium shadow-sm"
                                    ><Eye class="size-3.5" /> Ver en
                                    grande</span
                                >
                            </button>
                            <div
                                class="flex flex-1 flex-col gap-2 border-t p-3"
                            >
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{ p.nombre }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ giroCorto(p) }}
                                    </p>
                                </div>
                                <p class="line-clamp-2 text-xs leading-snug">
                                    {{ p.descripcion }}
                                </p>
                                <span
                                    class="w-fit rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium"
                                    >{{ categorias.estilos[p.estilo] }}</span
                                >
                                <Button
                                    v-if="editable"
                                    type="button"
                                    class="mt-auto h-10 w-full"
                                    @click="elegir(p)"
                                    ><Check /> Usar esta plantilla</Button
                                >
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Vista previa ampliada -->
    <Dialog
        :open="enVista !== null"
        @update:open="(v: boolean) => !v && (enVista = null)"
    >
        <DialogContent
            class="flex h-dvh max-h-dvh w-full max-w-none flex-col gap-0 overflow-hidden rounded-none p-0 sm:h-[94dvh] sm:max-w-[min(68rem,calc(100%-2rem))] sm:rounded-xl"
            :show-close-button="false"
        >
            <template v-if="enVista">
                <div
                    class="flex shrink-0 items-start gap-3 border-b px-4 py-3 sm:px-6"
                >
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="text-base font-semibold"
                            >{{ enVista.nombre }}
                            <span
                                class="ml-1 rounded-full bg-muted px-2 py-0.5 align-middle text-[11px] font-medium"
                                >{{ categorias.estilos[enVista.estilo] }}</span
                            ></DialogTitle
                        >
                        <DialogDescription class="text-xs"
                            >Ideal para:
                            {{
                                enVista.giros
                                    .map((g) => categorias.giros[g])
                                    .join(', ')
                            }}</DialogDescription
                        >
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-10 shrink-0"
                        aria-label="Cerrar vista previa"
                        @click="enVista = null"
                        ><X
                    /></Button>
                </div>
                <div
                    class="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-y-auto p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_17rem] lg:overflow-hidden"
                >
                    <div class="h-[68dvh] min-h-[30rem] lg:h-full lg:min-h-0">
                        <VistaPrevia
                            v-model:pagina="paginaVista"
                            :documento="enVista.documento"
                            :cargar-fuentes="false"
                        />
                    </div>
                    <aside class="grid content-start gap-3 text-sm">
                        <p>{{ enVista.descripcion }}</p>
                        <div>
                            <p class="text-xs font-semibold">
                                Lo que la distingue
                            </p>
                            <ul class="mt-1.5 grid gap-1.5 text-xs">
                                <li
                                    v-for="r in enVista.rasgos"
                                    :key="r"
                                    class="flex gap-1.5"
                                >
                                    <Sparkles
                                        class="mt-0.5 size-3.5 shrink-0 text-primary"
                                    />{{ r }}
                                </li>
                            </ul>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Los textos son de ejemplo y los enlaces quedan
                            vacíos: el editor te marcará qué falta antes de
                            publicar. Todo se puede cambiar después.
                        </p>
                    </aside>
                </div>
                <div
                    class="flex shrink-0 flex-col-reverse gap-2 border-t px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:px-6"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="enVista = null"
                        >Volver al catálogo</Button
                    >
                    <Button
                        v-if="editable"
                        type="button"
                        class="h-11"
                        @click="elegir(enVista)"
                        ><Check /> Usar esta plantilla</Button
                    >
                </div>
            </template>
        </DialogContent>
    </Dialog>

    <!-- Confirmación: qué se reemplaza -->
    <Dialog
        :open="elegida !== null"
        @update:open="(v: boolean) => !v && (elegida = null)"
    >
        <DialogContent class="max-h-[92dvh] overflow-y-auto sm:max-w-lg">
            <DialogTitle class="pr-8 text-base font-semibold"
                >¿Usar «{{ elegida?.nombre }}»?</DialogTitle
            >
            <DialogDescription class="text-sm"
                >Elige qué quieres cambiar. Tu nombre, tu foto, tus otras
                páginas y tus ajustes se conservan. Después podrás pulsar
                «Deshacer».</DialogDescription
            >
            <div
                class="grid gap-2"
                role="radiogroup"
                aria-label="Qué aplicar de la plantilla"
            >
                <label
                    v-for="m in MODOS"
                    :key="m.valor"
                    class="flex min-h-11 cursor-pointer gap-3 rounded-lg border p-3 text-sm transition has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                >
                    <input
                        v-model="modo"
                        type="radio"
                        name="modo-plantilla"
                        :value="m.valor"
                        class="mt-0.5 size-4 shrink-0 accent-primary"
                    />
                    <span>
                        <span class="block font-medium">{{ m.titulo }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            m.detalle
                        }}</span>
                    </span>
                </label>
            </div>
            <p
                v-if="modo === 'reemplazar' && elementosInicio > 0"
                class="flex items-center gap-1.5 rounded-lg bg-warning-soft/60 px-3 py-2 text-xs"
            >
                Se quitarán {{ elementosInicio }}
                {{ elementosInicio === 1 ? 'elemento' : 'elementos' }} de
                «Inicio». Puedes recuperarlos con «Deshacer» o desde el
                historial.
                <Ayuda
                    texto="El historial guarda copias de tu página. Lo encuentras en el botón del reloj, arriba."
                    etiqueta="Sobre el historial"
                />
            </p>
            <div
                class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="elegida = null"
                    >Cancelar</Button
                >
                <Button type="button" class="h-11" @click="confirmar"
                    ><Check /> Aplicar plantilla</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
