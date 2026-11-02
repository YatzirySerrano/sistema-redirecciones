<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle, Search, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import {
    ListboxContent,
    ListboxFilter,
    ListboxItem,
    ListboxRoot,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ErrorPeticion, pedir } from '@/lib/peticion';
import { coincide } from '@/lib/texto';
import { cn } from '@/lib/utils';
import type { Opcion, PaginaOpciones } from '@/types';

/**
 * Selector con búsqueda, único para todos los formularios y filtros.
 *
 * - Modo local (`opciones`): filtra en el navegador; para catálogos
 *   pequeños y fijos.
 * - Modo remoto (`url`): consulta el servidor con retardo (debounce),
 *   cancela la petición anterior, descarta respuestas obsoletas y carga más
 *   resultados al llegar al final. Para negocios, usuarios y productos.
 *
 * La búsqueda ignora mayúsculas y acentos. Teclado: flechas, Inicio/Fin,
 * Intro para elegir y Esc para cerrar. El valor vacío ('') significa «sin
 * selección»; con `vacio` se ofrece la opción para quitarla.
 */
const modelo = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        opciones?: Opcion[];
        url?: string;
        parametros?: Record<string, string | number | boolean | undefined>;
        /** Etiqueta del valor ya elegido en modo remoto (p. ej. al editar). */
        seleccionInicial?: Opcion | null;
        placeholder?: string;
        placeholderBusqueda?: string;
        /** Texto de la opción «sin selección»; null si es obligatorio. */
        vacio?: string | null;
        sinResultados?: string;
        etiqueta?: string;
        id?: string;
        name?: string;
        invalido?: boolean;
        disabled?: boolean;
        excluir?: string[];
        class?: string;
        anchoContenido?: string;
    }>(),
    {
        opciones: undefined,
        url: undefined,
        parametros: () => ({}),
        seleccionInicial: null,
        placeholder: 'Selecciona una opción',
        placeholderBusqueda: 'Escribe para buscar…',
        vacio: null,
        sinResultados: 'No encontramos coincidencias.',
        etiqueta: undefined,
        id: undefined,
        name: undefined,
        excluir: () => [],
        class: undefined,
        anchoContenido: undefined,
    },
);

const emit = defineEmits<{ seleccionar: [opcion: Opcion | null] }>();

const abierto = ref(false);
const busqueda = ref('');
const remoto = computed(() => props.url !== undefined);

// Etiqueta conocida del valor actual (remoto: se recuerda al elegir).
const conocida = ref<Opcion | null>(props.seleccionInicial);
watch(
    () => props.seleccionInicial,
    (nueva) => {
        if (nueva) {
            conocida.value = nueva;
        }
    },
);

// ---- Modo remoto ----
const resultados = ref<Opcion[]>([]);
const pagina = ref(1);
const hayMas = ref(false);
const cargando = ref(false);
const error = ref<string | null>(null);
let controlador: AbortController | null = null;
let secuencia = 0;

const consultar = async (reiniciar: boolean) => {
    if (!props.url) {
        return;
    }

    controlador?.abort();
    controlador = new AbortController();
    const actual = ++secuencia;
    const siguiente = reiniciar ? 1 : pagina.value + 1;

    const consulta = new URLSearchParams();
    consulta.set('buscar', busqueda.value.trim());
    consulta.set('pagina', String(siguiente));

    for (const [clave, valor] of Object.entries(props.parametros)) {
        if (valor !== undefined && valor !== '' && valor !== false) {
            consulta.set(clave, String(valor === true ? 1 : valor));
        }
    }

    cargando.value = true;
    error.value = null;

    try {
        const respuesta = await pedir(`${props.url}?${consulta.toString()}`, {
            senal: controlador.signal,
        });
        const datos = (await respuesta.json()) as PaginaOpciones;

        // Una respuesta más antigua que la última solicitada se descarta.
        if (actual !== secuencia) {
            return;
        }

        resultados.value = reiniciar
            ? datos.data
            : [...resultados.value, ...datos.data];
        pagina.value = siguiente;
        hayMas.value = datos.hay_mas;
    } catch (e) {
        if (e instanceof DOMException && e.name === 'AbortError') {
            return;
        }

        if (actual === secuencia) {
            error.value =
                e instanceof ErrorPeticion
                    ? e.message
                    : 'No pudimos cargar las opciones. Inténtalo nuevamente.';
        }
    } finally {
        if (actual === secuencia) {
            cargando.value = false;
        }
    }
};

const consultarConRetardo = useDebounceFn(() => consultar(true), 300);

watch(busqueda, () => {
    if (remoto.value && abierto.value) {
        cargando.value = true;
        void consultarConRetardo();
    }
});

watch(
    () => JSON.stringify(props.parametros),
    () => {
        resultados.value = [];

        if (remoto.value && abierto.value) {
            void consultar(true);
        }
    },
);

watch(abierto, (valor) => {
    if (valor) {
        busqueda.value = '';

        if (remoto.value) {
            void consultar(true);
        }
    } else {
        controlador?.abort();
    }
});

const alDesplazar = (evento: Event) => {
    const el = evento.target as HTMLElement;

    if (
        remoto.value &&
        hayMas.value &&
        !cargando.value &&
        el.scrollTop + el.clientHeight >= el.scrollHeight - 48
    ) {
        void consultar(false);
    }
};

// ---- Opciones visibles ----
const visibles = computed<Opcion[]>(() => {
    const base = remoto.value
        ? resultados.value
        : (props.opciones ?? []).filter((o) =>
              coincide(`${o.label} ${o.descripcion ?? ''}`, busqueda.value),
          );

    return base.filter((o) => !props.excluir.includes(o.value));
});

const seleccionada = computed<Opcion | null>(() => {
    if (modelo.value === '') {
        return null;
    }

    const lista = remoto.value ? resultados.value : (props.opciones ?? []);

    return (
        lista.find((o) => o.value === modelo.value) ??
        (conocida.value?.value === modelo.value ? conocida.value : null)
    );
});

const elegir = (valor: unknown) => {
    const texto = typeof valor === 'string' ? valor : '';
    const opcion =
        visibles.value.find((o) => o.value === texto) ??
        (texto === '' ? null : seleccionada.value);

    if (opcion) {
        conocida.value = opcion;
    }

    modelo.value = texto;
    emit('seleccionar', opcion);
    abierto.value = false;
};

const limpiar = () => {
    modelo.value = '';
    conocida.value = null;
    emit('seleccionar', null);
};
</script>

<template>
    <div :class="cn('relative', props.class)">
        <input v-if="name" type="hidden" :name="name" :value="modelo" />
        <Popover v-model:open="abierto">
            <PopoverTrigger as-child>
                <button
                    :id="id"
                    type="button"
                    role="combobox"
                    :aria-expanded="abierto"
                    aria-haspopup="listbox"
                    :aria-label="etiqueta"
                    :aria-invalid="invalido || undefined"
                    :disabled="disabled"
                    :class="
                        cn(
                            'flex h-9 w-full items-center gap-2 rounded-lg border border-input bg-background pr-2 pl-3 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
                            vacio !== null && seleccionada && 'pr-8',
                        )
                    "
                >
                    <span
                        class="min-w-0 flex-1 truncate"
                        :class="!seleccionada && 'text-muted-foreground'"
                    >
                        {{
                            seleccionada?.label ??
                            (modelo === ''
                                ? (vacio ?? placeholder)
                                : placeholder)
                        }}
                    </span>
                    <ChevronsUpDown
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                :class="
                    cn(
                        'w-(--reka-popover-trigger-width) min-w-64 p-0',
                        anchoContenido,
                    )
                "
                @open-auto-focus.prevent
            >
                <ListboxRoot
                    :model-value="modelo"
                    highlight-on-hover
                    class="flex flex-col"
                    @update:model-value="elegir"
                >
                    <div class="relative border-b">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ListboxFilter
                            v-model="busqueda"
                            auto-focus
                            :placeholder="placeholderBusqueda"
                            :aria-label="`Buscar en ${etiqueta ?? 'las opciones'}`"
                            class="h-10 w-full bg-transparent pr-9 pl-9 text-base outline-none placeholder:text-muted-foreground sm:text-sm"
                        />
                        <LoaderCircle
                            v-if="cargando"
                            class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                            aria-label="Cargando"
                        />
                    </div>
                    <ListboxContent
                        class="max-h-64 overflow-y-auto p-1"
                        @scroll="alDesplazar"
                    >
                        <ListboxItem
                            v-if="vacio !== null && busqueda.trim() === ''"
                            value=""
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm text-muted-foreground outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            <Check
                                class="size-4 shrink-0"
                                :class="
                                    modelo === '' ? 'opacity-100' : 'opacity-0'
                                "
                                aria-hidden="true"
                            />
                            {{ vacio }}
                        </ListboxItem>
                        <ListboxItem
                            v-for="opcion in visibles"
                            :key="opcion.value"
                            :value="opcion.value"
                            class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-2 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0"
                                :class="
                                    modelo === opcion.value
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                "
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block truncate">{{
                                    opcion.label
                                }}</span>
                                <span
                                    v-if="opcion.descripcion"
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ opcion.descripcion }}</span
                                >
                            </span>
                        </ListboxItem>

                        <p
                            v-if="error"
                            class="px-2 py-3 text-sm text-destructive"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <p
                            v-else-if="!cargando && visibles.length === 0"
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            {{ sinResultados }}
                        </p>
                        <button
                            v-else-if="remoto && hayMas"
                            type="button"
                            class="w-full rounded-md px-2 py-2 text-center text-xs text-muted-foreground hover:bg-muted"
                            :disabled="cargando"
                            @click="consultar(false)"
                        >
                            {{
                                cargando
                                    ? 'Cargando…'
                                    : 'Mostrar más resultados'
                            }}
                        </button>
                        <p
                            v-else-if="cargando && visibles.length === 0"
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            Buscando…
                        </p>
                    </ListboxContent>
                </ListboxRoot>
            </PopoverContent>
        </Popover>
        <button
            v-if="vacio !== null && seleccionada && !disabled"
            type="button"
            class="absolute top-1/2 right-7 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
            :aria-label="`Quitar selección${etiqueta ? ` de ${etiqueta}` : ''}`"
            @click="limpiar"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
