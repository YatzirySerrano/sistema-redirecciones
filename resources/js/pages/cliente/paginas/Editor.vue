<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    Cloud,
    CloudUpload,
    ExternalLink,
    Eye,
    EyeOff,
    History,
    LayoutList,
    Palette,
    Redo2,
    Rocket,
    Settings2,
    Ellipsis,
    Files,
    Save,
    Undo2,
} from '@lucide/vue';
import { useEventListener, useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import CatalogoBloques from '@/components/paginas/editor/CatalogoBloques.vue';
import EditorPerfil from '@/components/paginas/editor/EditorPerfil.vue';
import HistorialVersiones from '@/components/paginas/editor/HistorialVersiones.vue';
import ListaBloques from '@/components/paginas/editor/ListaBloques.vue';
import PanelAjustes from '@/components/paginas/editor/PanelAjustes.vue';
import PanelDiseno from '@/components/paginas/editor/PanelDiseno.vue';
import PanelPaginas from '@/components/paginas/editor/PanelPaginas.vue';
import VistaPrevia from '@/components/paginas/editor/VistaPrevia.vue';
import { nuevoBloque } from '@/components/paginas/editor/bloques';
import { proveerEditor } from '@/components/paginas/editor/contexto';
import { useEditorPagina } from '@/components/paginas/editor/useEditorPagina';
import type { ResumenPagina } from '@/components/paginas/editor/useEditorPagina';
import type { Documento, Tema, TipoBloque } from '@/components/paginas/tipos';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { relativo } from '@/lib/formato';
import { editor as rutaEditor, inicio, qr } from '@/routes/cliente/paginas';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Página de enlaces', href: inicio() },
            { title: 'Editor', href: rutaEditor() },
        ],
    },
});

const props = defineProps<{
    servicio: {
        activo: boolean;
        solo_lectura: boolean;
        estado: string | null;
        opera: boolean;
    };
    puede: {
        editar: boolean;
        publicar: boolean;
        dominios: boolean;
        ver_dominios: boolean;
        mensajes: boolean;
        estadisticas: boolean;
    };
    pagina: ResumenPagina;
    documento: Documento;
    tema_por_defecto: Tema;
    plantillas: {
        clave: string;
        nombre: string;
        descripcion: string;
        documento: Documento;
    }[];
    limites: { subpaginas: number };
    reservadas: string[];
}>();

const estado = useEditorPagina({
    documento: props.documento,
    pagina: props.pagina,
});
const { documento, pagina, ocupado, sucio, errores, conflicto } = estado;
const paginaActual = ref('inicio');
const recienAgregado = ref<string | null>(null);
const catalogoAbierto = ref(false);

proveerEditor({
    ...estado,
    editable: props.puede.editar,
    paginaActual,
    recienAgregado,
    abrirCatalogo: () => {
        catalogoAbierto.value = true;
    },
});

const esEscritorio = useMediaQuery('(min-width: 1024px)');
const esAmplio = useMediaQuery('(min-width: 1280px)');
type Pestana = 'contenido' | 'diseno' | 'paginas' | 'ajustes';
const pestana = ref<Pestana>('contenido');
const seccion = ref<Pestana | 'vista'>('contenido');
const previaAbierta = ref(false);
const historialAbierto = ref(false);
const confirmarDespublicar = ref(false);
const panel = computed(() =>
    esEscritorio.value ? pestana.value : seccion.value,
);

const actual = computed(
    () =>
        documento.value.paginas.find((p) => p.slug === paginaActual.value) ??
        documento.value.paginas[0],
);

watch(
    () => documento.value.paginas.map((p) => p.slug).join('|'),
    () => {
        if (
            !documento.value.paginas.some((p) => p.slug === paginaActual.value)
        ) {
            paginaActual.value = 'inicio';
        }
    },
);

const agregar = (tipo: TipoBloque) => {
    const nuevo = nuevoBloque(tipo);
    actual.value.bloques.push(nuevo);
    recienAgregado.value = nuevo.id;
    catalogoAbierto.value = false;
    seccion.value = 'contenido';
};

const PESTANAS: { clave: Pestana; etiqueta: string; icono: typeof Palette }[] =
    [
        { clave: 'contenido', etiqueta: 'Contenido', icono: LayoutList },
        { clave: 'diseno', etiqueta: 'Diseño', icono: Palette },
        { clave: 'paginas', etiqueta: 'Páginas', icono: Files },
        { clave: 'ajustes', etiqueta: 'Ajustes', icono: Settings2 },
    ];

// ---- Estado de guardado ----
const textoEstado = computed(() => {
    if (ocupado.value === 'guardando') {
        return 'Guardando…';
    }

    if (ocupado.value === 'publicando') {
        return 'Publicando…';
    }

    if (sucio.value) {
        return 'Cambios sin guardar';
    }

    if (pagina.value.cambios_sin_publicar) {
        return pagina.value.borrador_actualizado_en
            ? `Borrador guardado ${relativo(pagina.value.borrador_actualizado_en)}`
            : 'Sin publicar';
    }

    return pagina.value.publicado_en
        ? `Publicada ${relativo(pagina.value.publicado_en)}`
        : 'Publicada';
});

const totalErrores = computed(() => Object.keys(errores.value).length);

/** Lleva al primer dato con error (también en otra subpágina). */
const irAlError = () => {
    const clave = Object.keys(errores.value).find((c) =>
        c.startsWith('bloques.'),
    );

    if (!clave) {
        pestana.value = Object.keys(errores.value).some((c) =>
            c.startsWith('tema.'),
        )
            ? 'diseno'
            : 'contenido';
        seccion.value = pestana.value;

        return;
    }

    const id = clave.split('.')[1];
    const contiene = documento.value.paginas.find((p) =>
        p.bloques.some(
            (b) => b.id === id || (b.bloques ?? []).some((h) => h.id === id),
        ),
    );

    if (contiene) {
        paginaActual.value = contiene.slug;
    }

    seccion.value = 'contenido';
};

watch(totalErrores, (total, anterior) => {
    if (total > 0 && anterior === 0) {
        irAlError();
    }
});

// ---- Atajos de teclado ----
useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    const mod = e.metaKey || e.ctrlKey;

    if (!mod) {
        return;
    }

    const tecla = e.key.toLowerCase();

    if (tecla === 's') {
        e.preventDefault();

        if (props.puede.editar && sucio.value) {
            void estado.guardar();
        }

        return;
    }

    // En campos de texto se respeta el deshacer propio del navegador.
    const destino = e.target as HTMLElement | null;

    if (
        destino &&
        (destino.tagName === 'INPUT' ||
            destino.tagName === 'TEXTAREA' ||
            destino.isContentEditable)
    ) {
        return;
    }

    if (tecla === 'z' && !e.shiftKey) {
        e.preventDefault();
        estado.deshacer();
    } else if ((tecla === 'z' && e.shiftKey) || tecla === 'y') {
        e.preventDefault();
        estado.rehacer();
    }
});

// ---- Cambios sin guardar ----
useEventListener(window, 'beforeunload', (e: BeforeUnloadEvent) => {
    if (sucio.value) {
        e.preventDefault();
        e.returnValue = '';
    }
});

const quitarGuardia = router.on('before', (evento) => {
    const visita = evento.detail.visit;

    if (sucio.value && visita.method === 'get' && !visita.only?.length) {
        return window.confirm(
            'Tienes cambios sin guardar. ¿Salir sin guardarlos?',
        );
    }

    return true;
});

onBeforeUnmount(() => quitarGuardia());

// ---- Conflicto de versión ----
const recargar = () => {
    router.reload({
        only: ['documento', 'pagina'],
        onSuccess: (respuesta) => {
            const p = respuesta.props as unknown as {
                documento: Documento;
                pagina: ResumenPagina;
            };
            estado.reiniciar(p.documento, p.pagina);
        },
    });
};

const publicar = async () => {
    await estado.publicar();
};

const despublicar = async () => {
    if (await estado.despublicar()) {
        confirmarDespublicar.value = false;
    }
};

const urlQr = computed(() => qr({ query: { formato: 'svg' } }).url);
</script>

<template>
    <Head title="Editor de tu página" />

    <div class="-mt-2 pb-24 lg:pb-0">
        <!-- Barra superior -->
        <div
            class="sticky top-0 z-30 -mx-4 mb-4 border-b bg-background/90 px-4 py-2.5 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        >
            <div class="flex flex-wrap items-center gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h1 class="truncate text-base font-semibold sm:text-lg">
                            Tu página de enlaces
                        </h1>
                        <span
                            class="hidden shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium sm:inline"
                            :class="
                                pagina.publicada
                                    ? 'bg-success-soft text-success'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ pagina.estado_etiqueta }}</span
                        >
                    </div>
                    <p
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        <Spinner v-if="ocupado" class="size-3" />
                        <CloudUpload
                            v-else-if="sucio"
                            class="size-3.5 text-warning"
                        />
                        <Cloud v-else class="size-3.5" />
                        {{ textoEstado }}
                    </p>
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-10"
                        aria-label="Deshacer (Ctrl+Z)"
                        title="Deshacer (Ctrl+Z)"
                        :disabled="!estado.puedeDeshacer.value || !puede.editar"
                        @click="estado.deshacer()"
                        ><Undo2
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-10"
                        aria-label="Rehacer (Ctrl+Shift+Z)"
                        title="Rehacer (Ctrl+Shift+Z)"
                        :disabled="!estado.puedeRehacer.value || !puede.editar"
                        @click="estado.rehacer()"
                        ><Redo2
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="hidden size-10 sm:inline-flex"
                        aria-label="Historial de cambios"
                        title="Historial de cambios"
                        @click="historialAbierto = true"
                        ><History
                    /></Button>
                    <Button
                        v-if="esEscritorio && !esAmplio"
                        type="button"
                        variant="outline"
                        class="h-10"
                        @click="previaAbierta = true"
                        ><Eye /> Vista previa</Button
                    >
                    <Button
                        v-if="pagina.publicada"
                        variant="ghost"
                        class="hidden h-10 md:inline-flex"
                        as-child
                    >
                        <a
                            :href="pagina.url_canonica"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><ExternalLink /> Ver</a
                        >
                    </Button>
                    <Button
                        v-if="puede.editar"
                        type="button"
                        variant="outline"
                        class="hidden h-10 sm:inline-flex"
                        :disabled="!sucio || ocupado !== null"
                        @click="estado.guardar()"
                    >
                        <Save /> Guardar borrador
                    </Button>
                    <Button
                        v-if="puede.publicar"
                        type="button"
                        class="h-10"
                        :disabled="
                            ocupado !== null ||
                            (!sucio &&
                                !pagina.cambios_sin_publicar &&
                                pagina.publicada)
                        "
                        @click="publicar"
                    >
                        <Spinner v-if="ocupado === 'publicando'" />
                        <Rocket v-else />
                        Publicar
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-10"
                                aria-label="Más opciones"
                                ><Ellipsis
                            /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem
                                v-if="puede.editar"
                                class="sm:hidden"
                                :disabled="!sucio"
                                @select="estado.guardar()"
                                ><Save /> Guardar borrador</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                class="sm:hidden"
                                @select="historialAbierto = true"
                                ><History /> Historial de
                                cambios</DropdownMenuItem
                            >
                            <DropdownMenuItem v-if="pagina.publicada" as-child>
                                <a
                                    :href="pagina.url_canonica"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    ><ExternalLink /> Ver página publicada</a
                                >
                            </DropdownMenuItem>
                            <template v-if="puede.publicar && pagina.publicada">
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="text-destructive focus:text-destructive"
                                    @select="confirmarDespublicar = true"
                                    ><EyeOff /> Despublicar</DropdownMenuItem
                                >
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div
                v-if="totalErrores > 0"
                class="mt-2 flex flex-wrap items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive"
                role="alert"
            >
                <CircleAlert class="size-4 shrink-0" />
                <span class="flex-1"
                    >{{
                        totalErrores === 1
                            ? 'Hay un dato por corregir.'
                            : `Hay ${totalErrores} datos por corregir.`
                    }}
                    Los marcamos en rojo.</span
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9"
                    @click="irAlError"
                    >Ver</Button
                >
            </div>
        </div>

        <div
            v-if="!servicio.opera || !puede.editar"
            class="mb-4 flex gap-3 rounded-xl border border-warning/40 bg-warning-soft/50 p-4 text-sm"
            role="status"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0 text-warning" />
            <p v-if="!servicio.opera">
                <strong>Modo de solo lectura.</strong> Tu servicio de Página de
                enlaces
                {{
                    servicio.estado === 'vencida'
                        ? 'venció'
                        : 'está en revisión'
                }}: puedes ver tu página pero no editarla ni publicarla.
                Contacta a DASTI para reactivarlo.
            </p>
            <p v-else>Tu rol te permite ver la página, pero no modificarla.</p>
        </div>

        <div
            class="grid grid-cols-1 gap-6 lg:grid-cols-[19rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)_minmax(19rem,23rem)] 2xl:grid-cols-[19rem_minmax(0,1fr)_minmax(20rem,25rem)]"
        >
            <!-- Herramientas -->
            <aside
                class="min-w-0 lg:sticky lg:top-24 lg:block lg:max-h-[calc(100dvh-7rem)] lg:overflow-y-auto lg:pr-1"
                :class="
                    seccion === 'contenido' || seccion === 'vista'
                        ? 'hidden'
                        : 'block'
                "
            >
                <div
                    role="tablist"
                    aria-label="Herramientas"
                    class="mb-4 hidden grid-cols-4 gap-1 rounded-lg bg-muted p-1 lg:grid"
                >
                    <button
                        v-for="p in PESTANAS"
                        :key="p.clave"
                        type="button"
                        role="tab"
                        :aria-selected="pestana === p.clave"
                        class="flex min-h-10 flex-col items-center justify-center gap-0.5 rounded-md text-[11px] font-medium transition"
                        :class="
                            pestana === p.clave
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="pestana = p.clave"
                    >
                        <component :is="p.icono" class="size-4" />
                        {{ p.etiqueta }}
                    </button>
                </div>

                <div
                    v-if="panel === 'contenido'"
                    class="grid grid-cols-1 gap-6"
                >
                    <section class="grid grid-cols-1 gap-3">
                        <h2 class="text-sm font-semibold">Perfil</h2>
                        <EditorPerfil />
                    </section>
                    <section v-if="puede.editar" class="grid grid-cols-1 gap-3">
                        <h2 class="text-sm font-semibold">
                            Agregar a «{{ actual.titulo }}»
                        </h2>
                        <CatalogoBloques compacto @elegir="agregar" />
                    </section>
                </div>
                <PanelDiseno
                    v-else-if="panel === 'diseno'"
                    :plantillas="plantillas"
                    :tema-por-defecto="tema_por_defecto"
                />
                <PanelPaginas
                    v-else-if="panel === 'paginas'"
                    :reservadas="reservadas"
                    :limite="limites.subpaginas"
                    :prefijo="pagina.url_publica"
                />
                <PanelAjustes
                    v-else-if="panel === 'ajustes'"
                    :puede-publicar="puede.publicar"
                    :puede-dominios="puede.ver_dominios"
                />
            </aside>

            <!-- Elementos de la página actual -->
            <section
                class="min-w-0"
                :class="seccion === 'contenido' ? 'block' : 'hidden lg:block'"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">Editando</p>
                        <h2 class="truncate text-base font-semibold">
                            {{ actual.titulo || 'Inicio' }}
                        </h2>
                    </div>
                    <div
                        v-if="documento.paginas.length > 1"
                        class="flex gap-1 overflow-x-auto"
                    >
                        <button
                            v-for="p in documento.paginas"
                            :key="p.id"
                            type="button"
                            class="min-h-10 shrink-0 rounded-full border px-3 text-xs font-medium transition"
                            :class="
                                p.slug === paginaActual
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent'
                            "
                            @click="paginaActual = p.slug"
                        >
                            {{ p.titulo || p.slug }}
                        </button>
                    </div>
                </div>

                <details
                    v-if="!esEscritorio && paginaActual === 'inicio'"
                    class="mb-3 rounded-xl border bg-card p-4"
                >
                    <summary
                        class="min-h-6 cursor-pointer text-sm font-semibold"
                    >
                        Perfil: nombre, foto y descripción
                    </summary>
                    <div class="mt-4"><EditorPerfil /></div>
                </details>

                <ListaBloques v-model="actual.bloques" />
            </section>

            <!-- Vista previa -->
            <aside
                class="min-w-0 xl:sticky xl:top-24 xl:block xl:h-[calc(100dvh-7rem)]"
                :class="
                    seccion === 'vista'
                        ? 'block h-[calc(100dvh-13rem)] lg:hidden xl:block'
                        : 'hidden'
                "
            >
                <VistaPrevia
                    v-if="esAmplio || seccion === 'vista'"
                    v-model:pagina="paginaActual"
                    :documento="documento"
                    :qr="urlQr"
                />
            </aside>
        </div>

        <!-- Navegación móvil -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
            aria-label="Secciones del editor"
        >
            <div class="grid grid-cols-5">
                <button
                    v-for="s in [
                        ...PESTANAS,
                        {
                            clave: 'vista' as const,
                            etiqueta: 'Vista previa',
                            icono: Eye,
                        },
                    ]"
                    :key="s.clave"
                    type="button"
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium"
                    :class="
                        seccion === s.clave
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                    :aria-current="seccion === s.clave ? 'page' : undefined"
                    @click="seccion = s.clave"
                >
                    <component :is="s.icono" class="size-5" />
                    {{ s.etiqueta }}
                </button>
            </div>
        </nav>
    </div>

    <!-- Catálogo para agregar elementos -->
    <Dialog v-model:open="catalogoAbierto">
        <DialogContent class="max-h-[88dvh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Agregar a «{{ actual.titulo }}»</DialogTitle>
                <DialogDescription
                    >Elige qué quieres mostrar. Después podrás editarlo y
                    moverlo.</DialogDescription
                >
            </DialogHeader>
            <CatalogoBloques @elegir="agregar" />
        </DialogContent>
    </Dialog>

    <!-- Vista previa en pantallas medianas -->
    <Sheet v-model:open="previaAbierta">
        <SheetContent side="right" class="w-full sm:max-w-lg">
            <SheetHeader><SheetTitle>Vista previa</SheetTitle></SheetHeader>
            <div class="h-[calc(100dvh-6rem)] px-4 pb-4">
                <VistaPrevia
                    v-if="previaAbierta"
                    v-model:pagina="paginaActual"
                    :documento="documento"
                    :qr="urlQr"
                />
            </div>
        </SheetContent>
    </Sheet>

    <HistorialVersiones v-model:open="historialAbierto" />

    <DialogoConfirmacion
        v-model:open="confirmarDespublicar"
        titulo="¿Despublicar tu página?"
        descripcion="Tus visitantes verán «página no disponible» hasta que vuelvas a publicar. Tu contenido y tu dirección se conservan."
        confirmar="Despublicar"
        destructivo
        :procesando="ocupado === 'despublicando'"
        @confirmar="despublicar"
    />

    <DialogoConfirmacion
        :open="conflicto !== null"
        titulo="La página cambió en otra ventana"
        :descripcion="`${conflicto?.por ?? 'Alguien'} guardó cambios ${conflicto?.en ? relativo(conflicto.en) : 'hace un momento'}. Puedes cargar esa versión (perderás tus cambios sin guardar) o guardar la tuya encima.`"
        confirmar="Guardar mis cambios de todos modos"
        @update:open="(v: boolean) => !v && (conflicto = null)"
        @confirmar="estado.guardar({ forzar: true })"
    >
        <Button
            type="button"
            variant="outline"
            class="h-11 w-full"
            @click="recargar"
            >Cargar la versión guardada</Button
        >
    </DialogoConfirmacion>
</template>
