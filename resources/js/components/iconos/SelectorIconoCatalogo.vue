<script setup lang="ts">
import { Ban, LoaderCircle, Search, X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import {
    computed,
    getCurrentInstance,
    nextTick,
    onBeforeUnmount,
    ref,
    shallowRef,
    useId,
    watch,
} from 'vue';
import CampoColor from '@/components/paginas/editor/CampoColor.vue';
import Deslizador from '@/components/paginas/editor/Deslizador.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { PREFIJO, buscar, cargarCatalogo, catalogo, todos } from './catalogo';
import { EQUIVALENTES, nombreHeredado } from './heredados';
import IconoCatalogo from './IconoCatalogo.vue';

/**
 * Selector de la biblioteca de íconos (Página de enlaces y Fidelidad).
 *
 * v-model: valor guardado (`i:<nombre>`, `red:<red>` o clave heredada) o
 * null. Opcionales: v-model:color (#rrggbb o null = el del diseño) y
 * v-model:tamano (px o null = predeterminado). El catálogo se descarga
 * (chunk aparte) solo al abrir el selector.
 */
const modelo = defineModel<string | null>({ default: null });
const color = defineModel<string | null>('color', { default: null });
const tamano = defineModel<number | null>('tamano', { default: null });

const props = withDefaults(
    defineProps<{
        etiqueta?: string;
        deshabilitado?: boolean;
        permitirNinguno?: boolean;
        incluirRedes?: boolean;
        /** Color con que se muestra la vista previa si no hay uno propio. */
        colorBase?: string;
        tamanoMin?: number;
        tamanoMax?: number;
        tamanoBase?: number;
    }>(),
    {
        etiqueta: 'Ícono',
        permitirNinguno: true,
        incluirRedes: true,
        colorBase: undefined,
        tamanoMin: 16,
        tamanoMax: 36,
        tamanoBase: 20,
    },
);

// El color y el tamaño solo se ofrecen si el contenedor los enlaza.
const vnodeProps = getCurrentInstance()?.vnode.props ?? {};
const conColor = 'onUpdate:color' in vnodeProps;
const conTamano = 'onUpdate:tamano' in vnodeProps;

const id = useId();
const abierto = ref(false);
const consulta = ref('');
/** En el teléfono, color y tamaño se muestran solo si se piden. */
const personalizar = ref(false);
const categoria = ref<string>('todos');
const error = ref(false);
const cargando = computed(
    () => abierto.value && !catalogo.value && !error.value,
);
const escritorio = useMediaQuery('(min-width: 640px)');
const tactil = useMediaQuery('(pointer: coarse)');

const PASO = 120;
const limite = ref(PASO);
const enfocado = ref<string | null>(null);
const rejilla = shallowRef<HTMLElement | null>(null);
const centinela = shallowRef<HTMLElement | null>(null);
const buscador = shallowRef<HTMLInputElement | null>(null);

function nombreDe(valor: string | null): string {
    if (!valor) {
        return 'Sin ícono';
    }

    if (valor.startsWith(PREFIJO)) {
        return (
            catalogo.value?.iconos[valor.slice(PREFIJO.length)]?.nombre ??
            'Ícono'
        );
    }

    if (valor.startsWith('red:') && catalogo.value) {
        return (
            catalogo.value.redes[valor.slice(4)]?.nombre ??
            nombreHeredado(valor) ??
            'Red social'
        );
    }

    return nombreHeredado(valor) ?? 'Ícono';
}

/** Valor del catálogo que corresponde al actual (claves heredadas). */
const elegidoEnLista = computed(() => {
    const v = modelo.value;

    if (!v) {
        return null;
    }

    return EQUIVALENTES[v] ? `${PREFIJO}${EQUIVALENTES[v]}` : v;
});

const categorias = computed(() =>
    (catalogo.value?.categorias ?? []).filter(
        (c) => props.incluirRedes || c.clave !== 'redes',
    ),
);

const lista = computed<string[]>(() => {
    const datos = catalogo.value;

    if (!datos) {
        return [];
    }

    const enCategoria =
        categoria.value === 'todos'
            ? null
            : new Set(
                  (
                      datos.categorias.find((c) => c.clave === categoria.value)
                          ?.iconos ?? []
                  ).map((c) => (c.startsWith('red:') ? c : `${PREFIJO}${c}`)),
              );

    const texto = consulta.value.trim();
    let valores: string[];

    if (texto) {
        valores = buscar(datos, texto, enCategoria ?? undefined);
    } else {
        valores = enCategoria
            ? [...enCategoria]
            : todos(datos, props.incluirRedes);
    }

    return props.incluirRedes
        ? valores
        : valores.filter((v) => !v.startsWith('red:'));
});

const visibles = computed(() => lista.value.slice(0, limite.value));

const vistaPrevia = computed(
    () => enfocado.value ?? elegidoEnLista.value ?? modelo.value,
);
const colorVista = computed(
    () => color.value ?? props.colorBase ?? 'currentColor',
);

watch([consulta, categoria], () => {
    limite.value = PASO;
    rejilla.value?.scrollTo({ top: 0 });
});

let observador: IntersectionObserver | null = null;

watch(centinela, (el) => {
    observador?.disconnect();

    if (!el || typeof IntersectionObserver === 'undefined') {
        return;
    }

    observador = new IntersectionObserver(
        (entradas) => {
            if (
                entradas.some((e) => e.isIntersecting) &&
                limite.value < lista.value.length
            ) {
                limite.value += PASO;
            }
        },
        { root: rejilla.value, rootMargin: '200px' },
    );
    observador.observe(el);
});

onBeforeUnmount(() => observador?.disconnect());

async function abrir() {
    if (props.deshabilitado) {
        return;
    }

    abierto.value = true;
    error.value = false;
    enfocado.value = null;

    try {
        await cargarCatalogo();
    } catch {
        error.value = true;

        return;
    }

    // Si el elegido no está en la primera página, se muestra hasta él.
    const indice = elegidoEnLista.value
        ? lista.value.indexOf(elegidoEnLista.value)
        : -1;

    if (indice >= limite.value) {
        limite.value = Math.ceil((indice + 1) / PASO) * PASO;
    }

    await nextTick();

    if (!tactil.value) {
        buscador.value?.focus();
    }

    rejilla.value
        ?.querySelector<HTMLElement>('[aria-pressed="true"]')
        ?.scrollIntoView({ block: 'center' });
}

function elegir(valor: string | null, cerrar = false) {
    modelo.value = valor;

    if (cerrar) {
        abierto.value = false;
    }
}

function columnas(): number {
    const el = rejilla.value?.querySelector<HTMLElement>('[data-cuadricula]');

    if (!el) {
        return 1;
    }

    return Math.max(
        1,
        getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean)
            .length,
    );
}

async function mover(evento: KeyboardEvent, indice: number) {
    const total = lista.value.length;
    const saltos: Record<string, number> = {
        ArrowRight: 1,
        ArrowLeft: -1,
        ArrowDown: columnas(),
        ArrowUp: -columnas(),
        Home: -indice,
        End: total - 1 - indice,
    };

    if (evento.key === 'Enter' || evento.key === ' ') {
        evento.preventDefault();
        elegir(lista.value[indice], evento.key === 'Enter');

        return;
    }

    const salto = saltos[evento.key];

    if (salto === undefined) {
        return;
    }

    evento.preventDefault();
    const destino = Math.max(0, Math.min(total - 1, indice + salto));

    if (destino >= limite.value) {
        limite.value = Math.ceil((destino + 1) / PASO) * PASO;
        await nextTick();
    }

    rejilla.value
        ?.querySelector<HTMLElement>(`[data-indice="${destino}"]`)
        ?.focus();
}

/** Solo el elegido (o el primero) entra en el orden de tabulación. */
const tabulable = computed(() => {
    const i = elegidoEnLista.value
        ? visibles.value.indexOf(elegidoEnLista.value)
        : -1;

    return i >= 0 ? i : 0;
});

function limpiarBusqueda() {
    consulta.value = '';
    buscador.value?.focus();
}
</script>

<template>
    <div class="grid min-w-0 grid-cols-1 gap-1.5">
        <span
            v-if="etiqueta"
            :id="`${id}-etiqueta`"
            class="text-xs font-medium text-muted-foreground"
            >{{ etiqueta }}</span
        >
        <Button
            type="button"
            variant="outline"
            class="h-11 w-full min-w-0 justify-start gap-2"
            :disabled="deshabilitado"
            :aria-labelledby="
                etiqueta ? `${id}-etiqueta ${id}-actual` : undefined
            "
            aria-haspopup="dialog"
            @click="abrir"
        >
            <IconoCatalogo
                v-if="modelo"
                :valor="modelo"
                class="size-5 shrink-0"
                :color="color ?? undefined"
            />
            <Ban v-else class="size-4 shrink-0 text-muted-foreground" />
            <span :id="`${id}-actual`" class="truncate">{{
                nombreDe(modelo)
            }}</span>
            <span class="ml-auto shrink-0 text-xs text-muted-foreground"
                >Cambiar</span
            >
        </Button>

        <Dialog v-model:open="abierto">
            <DialogContent
                :show-close-button="false"
                class="flex flex-col gap-0 overflow-hidden p-0 max-sm:inset-0 max-sm:h-[100dvh] max-sm:max-w-none max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-none max-sm:border-0 sm:h-[min(44rem,90vh)] sm:max-w-[min(56rem,calc(100%-2rem))]"
                @open-auto-focus.prevent
            >
                <!-- Encabezado y buscador (arriba: el teclado del teléfono no los tapa) -->
                <div class="shrink-0 space-y-3 border-b px-4 pt-4 pb-3">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <DialogTitle class="text-base font-semibold"
                                >Elegir ícono</DialogTitle
                            >
                            <DialogDescription
                                class="text-xs text-muted-foreground max-sm:sr-only"
                            >
                                Busca por palabra (por ejemplo «hielo» o «agua»)
                                o explora por categoría.
                            </DialogDescription>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-10 shrink-0"
                            aria-label="Cerrar"
                            @click="abierto = false"
                        >
                            <X class="size-5" />
                        </Button>
                    </div>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            ref="buscador"
                            v-model="consulta"
                            type="text"
                            inputmode="search"
                            enterkeyhint="search"
                            autocomplete="off"
                            class="h-11 w-full rounded-lg border border-input bg-background pr-10 pl-9 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 sm:text-sm"
                            placeholder="Buscar ícono…"
                            aria-label="Buscar ícono"
                            :aria-controls="`${id}-rejilla`"
                        />
                        <button
                            v-if="consulta"
                            type="button"
                            class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-accent"
                            aria-label="Borrar búsqueda"
                            @click="limpiarBusqueda"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <div
                        class="-mx-4 flex [scrollbar-width:thin] gap-1.5 overflow-x-auto px-4 pb-1"
                        role="group"
                        aria-label="Categorías"
                    >
                        <button
                            v-for="c in [
                                { clave: 'todos', nombre: 'Todos' },
                                ...categorias,
                            ]"
                            :key="c.clave"
                            type="button"
                            class="h-9 shrink-0 rounded-full border px-3 text-sm whitespace-nowrap transition-colors"
                            :class="
                                categoria === c.clave
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background hover:bg-accent'
                            "
                            :aria-pressed="categoria === c.clave"
                            @click="categoria = c.clave"
                        >
                            {{ c.nombre }}
                        </button>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1">
                    <!-- Cuadrícula (se monta por partes) -->
                    <div
                        :id="`${id}-rejilla`"
                        ref="rejilla"
                        class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3"
                    >
                        <div
                            v-if="cargando"
                            class="flex h-40 items-center justify-center gap-2 text-sm text-muted-foreground"
                            role="status"
                        >
                            <LoaderCircle class="size-4 animate-spin" />
                            Cargando íconos…
                        </div>
                        <div
                            v-else-if="error"
                            class="flex h-40 flex-col items-center justify-center gap-3 text-center text-sm"
                            role="alert"
                        >
                            No pudimos cargar los íconos. Revisa tu conexión.
                            <Button
                                type="button"
                                variant="outline"
                                @click="abrir"
                                >Reintentar</Button
                            >
                        </div>
                        <p
                            v-else-if="lista.length === 0"
                            class="px-2 py-10 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            No encontramos íconos para «{{ consulta }}». Prueba
                            con otra palabra o elige «Todos».
                        </p>
                        <template v-else>
                            <p class="sr-only" role="status">
                                {{ lista.length }} íconos
                            </p>
                            <div
                                data-cuadricula
                                @mouseleave="enfocado = null"
                                class="grid grid-cols-[repeat(auto-fill,minmax(2.75rem,1fr))] gap-1"
                            >
                                <button
                                    v-for="(valor, i) in visibles"
                                    :key="valor"
                                    type="button"
                                    :data-indice="i"
                                    :tabindex="i === tabulable ? 0 : -1"
                                    class="flex aspect-square min-h-11 items-center justify-center rounded-lg border-2 transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    :class="
                                        elegidoEnLista === valor
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-transparent hover:bg-accent'
                                    "
                                    :title="nombreDe(valor)"
                                    :aria-label="nombreDe(valor)"
                                    :aria-pressed="elegidoEnLista === valor"
                                    @click="elegir(valor)"
                                    @dblclick="elegir(valor, true)"
                                    @focus="enfocado = valor"
                                    @mouseenter="enfocado = valor"
                                    @keydown="mover($event, i)"
                                >
                                    <IconoCatalogo
                                        :valor="valor"
                                        class="size-6"
                                    />
                                </button>
                            </div>
                            <div
                                v-if="visibles.length < lista.length"
                                ref="centinela"
                                class="flex justify-center py-4"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="limite += PASO"
                                    >Mostrar más</Button
                                >
                            </div>
                        </template>
                    </div>

                    <!-- Vista previa y personalización (escritorio) -->
                    <aside
                        v-if="escritorio"
                        class="flex w-64 shrink-0 flex-col gap-4 overflow-y-auto border-l bg-muted/30 p-4"
                        aria-label="Vista previa"
                    >
                        <div
                            class="flex aspect-square w-full items-center justify-center rounded-xl border bg-background"
                        >
                            <IconoCatalogo
                                v-if="vistaPrevia"
                                :valor="vistaPrevia"
                                :color="colorVista"
                                :style="{
                                    width: `${(tamano ?? tamanoBase) * 3}px`,
                                    height: `${(tamano ?? tamanoBase) * 3}px`,
                                }"
                            />
                            <Ban v-else class="size-12 text-muted-foreground" />
                        </div>
                        <p
                            class="text-center text-sm font-medium"
                            aria-live="polite"
                        >
                            {{ nombreDe(vistaPrevia) }}
                        </p>
                        <template v-if="conColor || conTamano">
                            <div v-if="conColor" class="space-y-1.5">
                                <CampoColor
                                    :id="`${id}-color`"
                                    :model-value="
                                        color ?? colorBase ?? '#111111'
                                    "
                                    etiqueta="Color del ícono"
                                    @update:model-value="color = $event"
                                />
                                <button
                                    v-if="color"
                                    type="button"
                                    class="text-xs text-primary underline-offset-2 hover:underline"
                                    @click="color = null"
                                >
                                    Usar el color del diseño
                                </button>
                            </div>
                            <Deslizador
                                v-if="conTamano"
                                :id="`${id}-tamano`"
                                :model-value="tamano ?? tamanoBase"
                                etiqueta="Tamaño"
                                :min="tamanoMin"
                                :max="tamanoMax"
                                unidad=" px"
                                @update:model-value="tamano = $event"
                            />
                        </template>
                    </aside>
                </div>

                <!-- Pie: vista previa compacta (móvil) y acciones -->
                <div
                    class="shrink-0 space-y-3 border-t bg-background px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                >
                    <div v-if="!escritorio" class="flex items-center gap-3">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-lg border bg-muted/30"
                        >
                            <IconoCatalogo
                                v-if="vistaPrevia"
                                :valor="vistaPrevia"
                                :color="colorVista"
                                class="size-7"
                            />
                            <Ban v-else class="size-5 text-muted-foreground" />
                        </span>
                        <p
                            class="min-w-0 flex-1 truncate text-sm font-medium"
                            aria-live="polite"
                        >
                            {{ nombreDe(vistaPrevia) }}
                        </p>
                        <Button
                            v-if="conColor || conTamano"
                            type="button"
                            variant="outline"
                            class="h-10 shrink-0"
                            :aria-expanded="personalizar"
                            :aria-controls="`${id}-personalizar`"
                            @click="personalizar = !personalizar"
                        >
                            {{ personalizar ? 'Ocultar' : 'Color y tamaño' }}
                        </Button>
                    </div>
                    <div
                        v-if="
                            !escritorio &&
                            personalizar &&
                            (conColor || conTamano)
                        "
                        :id="`${id}-personalizar`"
                        class="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2"
                    >
                        <CampoColor
                            v-if="conColor"
                            :id="`${id}-color-m`"
                            :model-value="color ?? colorBase ?? '#111111'"
                            etiqueta="Color"
                            @update:model-value="color = $event"
                        />
                        <Deslizador
                            v-if="conTamano"
                            :id="`${id}-tamano-m`"
                            :model-value="tamano ?? tamanoBase"
                            etiqueta="Tamaño"
                            :min="tamanoMin"
                            :max="tamanoMax"
                            unidad=" px"
                            @update:model-value="tamano = $event"
                        />
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            v-if="permitirNinguno"
                            type="button"
                            variant="ghost"
                            class="mr-auto h-11"
                            @click="elegir(null, true)"
                        >
                            <Ban class="size-4" />
                            Sin ícono
                        </Button>
                        <Button
                            type="button"
                            class="h-11 min-w-28"
                            @click="abierto = false"
                            >Listo</Button
                        >
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
