<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CreditCard,
    Ellipsis,
    Eye,
    History,
    LayoutTemplate,
    Lock,
    MessageSquareText,
    PanelTop,
    Palette,
    Redo2,
    RotateCcw,
    Save,
    Send,
    Stamp,
    Store,
    TriangleAlert,
    Undo2,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import { cargarFuentes } from '@/components/fidelidad-diseno/catalogo';
import type {
    DocumentoDiseno,
    EstadoPrograma,
} from '@/components/fidelidad-diseno/tipos';
import { enviar } from '@/components/fidelidad/diseno/api';
import { avisosLegibilidad } from '@/components/fidelidad/diseno/legibilidad';
import PanelHerramientas from '@/components/fidelidad/diseno/PanelHerramientas.vue';
import type {
    ArchivoImagen,
    CampanaDiseno,
    EstadoDiseno,
    PlantillaDiseno,
    RespuestaDiseno,
    VersionDiseno,
} from '@/components/fidelidad/diseno/tipos';
import VistaPrevia from '@/components/fidelidad/diseno/VistaPrevia.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    EstadoServicio,
    PuedeFidelidad,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { fechaHora, relativo } from '@/lib/formato';
import { ErrorPeticion, pedir } from '@/lib/peticion';
import { inicio } from '@/routes/cliente/fidelidad';
import {
    edit,
    publicar as rutaPublicar,
    restaurar as rutaRestaurar,
    update as rutaGuardar,
} from '@/routes/cliente/fidelidad/diseno';
import { destroy as rutaQuitarVariante } from '@/routes/cliente/fidelidad/diseno/variantes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Diseño de tarjeta', href: edit() },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    negocio: string;
    edicion: { puede: boolean; motivo: string | null };
    variante: { campana_id: number; nombre: string } | null;
    diseno: EstadoDiseno;
    general: DocumentoDiseno | null;
    versiones: VersionDiseno[];
    campanas: CampanaDiseno[];
    plantillas: PlantillaDiseno[];
    predeterminado: DocumentoDiseno;
    archivos: ArchivoImagen[];
    uso: { usados: number; max_archivos: number };
}>();

const clonar = <T>(v: T): T => JSON.parse(JSON.stringify(v)) as T;

// ---- Estado del documento ----
const doc = ref<DocumentoDiseno>(clonar(props.diseno.borrador));
const servidor = ref<EstadoDiseno>(props.diseno);
const guardado = ref(JSON.stringify(props.diseno.borrador));
const versiones = ref<VersionDiseno[]>(props.versiones);
const archivos = ref<ArchivoImagen[]>(props.archivos);
const conflicto = ref(false);
const ocupado = ref<null | 'guardar' | 'publicar' | 'restaurar' | 'variante'>(
    null,
);
const sucio = computed(() => JSON.stringify(doc.value) !== guardado.value);
const medios = computed<Record<string, string>>(() => ({
    ...servidor.value.medios,
    ...Object.fromEntries(archivos.value.map((a) => [a.id, a.url])),
}));
const esVariante = computed(() => props.variante !== null);
const avisos = computed(() => avisosLegibilidad(doc.value, esVariante.value));

watch(
    () => [doc.value.tipografia.titulos, doc.value.tipografia.texto],
    () =>
        cargarFuentes(props.general ? [doc.value, props.general] : [doc.value]),
    { immediate: true },
);

// ---- Deshacer / rehacer (instantáneas con breve espera) ----
const pasado: string[] = [];
const futuro: string[] = [];
const puedeDeshacer = ref(false);
const puedeRehacer = ref(false);
let ultimo = JSON.stringify(doc.value);
let aplicando = false;
let espera: ReturnType<typeof setTimeout> | null = null;

const confirmarInstantanea = () => {
    espera = null;
    const actual = JSON.stringify(doc.value);

    if (actual === ultimo) {
        return;
    }

    pasado.push(ultimo);

    if (pasado.length > 60) {
        pasado.shift();
    }

    futuro.length = 0;
    ultimo = actual;
    puedeDeshacer.value = true;
    puedeRehacer.value = false;
};

watch(
    doc,
    () => {
        if (aplicando) {
            return;
        }

        if (espera) {
            clearTimeout(espera);
        }

        espera = setTimeout(confirmarInstantanea, 350);
    },
    { deep: true },
);

const aplicar = (json: string) => {
    aplicando = true;
    doc.value = JSON.parse(json) as DocumentoDiseno;
    ultimo = json;
    void nextTick(() => (aplicando = false));
};

const deshacer = () => {
    if (espera) {
        clearTimeout(espera);
        confirmarInstantanea();
    }

    const previo = pasado.pop();

    if (previo !== undefined) {
        futuro.push(ultimo);
        aplicar(previo);
    }

    puedeDeshacer.value = pasado.length > 0;
    puedeRehacer.value = futuro.length > 0;
};

const rehacer = () => {
    const siguiente = futuro.pop();

    if (siguiente !== undefined) {
        pasado.push(ultimo);
        aplicar(siguiente);
    }

    puedeDeshacer.value = pasado.length > 0;
    puedeRehacer.value = futuro.length > 0;
};

/** Reemplaza el documento completo (queda en el historial de deshacer). */
const reemplazar = (nuevo: DocumentoDiseno) => {
    doc.value = clonar(nuevo);
};

const teclas = (e: KeyboardEvent) => {
    const objetivo = e.target as HTMLElement | null;

    if (
        !(e.metaKey || e.ctrlKey) ||
        objetivo?.closest('input, textarea, select, [contenteditable]')
    ) {
        return;
    }

    if (e.key.toLowerCase() === 'z' && !e.shiftKey) {
        e.preventDefault();
        deshacer();
    } else if (
        (e.key.toLowerCase() === 'z' && e.shiftKey) ||
        e.key.toLowerCase() === 'y'
    ) {
        e.preventDefault();
        rehacer();
    } else if (e.key.toLowerCase() === 's' && props.edicion.puede) {
        e.preventDefault();
        void guardar();
    }
};

// ---- Aviso de cambios sin guardar ----
let saltarAviso = false;
const antesDeSalir = (e: BeforeUnloadEvent) => {
    if (sucio.value && !saltarAviso) {
        e.preventDefault();
    }
};
let quitarGuardia: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', antesDeSalir);
    window.addEventListener('keydown', teclas);
    quitarGuardia = router.on('before', () => {
        if (
            sucio.value &&
            !saltarAviso &&
            !window.confirm(
                'Tienes cambios sin guardar en el diseño. ¿Salir sin guardarlos?',
            )
        ) {
            return false;
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', antesDeSalir);
    window.removeEventListener('keydown', teclas);
    quitarGuardia?.();

    if (espera) {
        clearTimeout(espera);
    }
});

// ---- Guardar, publicar, restaurar ----
const aplicarRespuesta = (r: RespuestaDiseno) => {
    servidor.value = r.diseno;
    versiones.value = r.versiones;
    const limpio = JSON.stringify(r.diseno.borrador);

    // El servidor pudo corregir valores (saneamiento): se muestra lo guardado.
    if (limpio !== JSON.stringify(doc.value)) {
        reemplazar(r.diseno.borrador);
    }

    guardado.value = limpio;
    toast.success(r.mensaje);
    r.avisos.forEach((a) => toast.warning(a));
};

const manejarError = (e: unknown) => {
    if (e instanceof ErrorPeticion && e.estado === 409) {
        conflicto.value = true;

        return;
    }

    toast.error(
        e instanceof ErrorPeticion
            ? e.message
            : 'No pudimos guardar el diseño. Inténtalo nuevamente.',
    );
};

const guardar = async () => {
    if (ocupado.value || !props.edicion.puede) {
        return;
    }

    ocupado.value = 'guardar';

    try {
        const r = await pedir(rutaGuardar.url(), {
            metodo: 'PUT',
            datos: {
                campana_id: props.variante?.campana_id ?? null,
                version: servidor.value.version,
                documento: doc.value,
            },
        });
        aplicarRespuesta((await r.json()) as RespuestaDiseno);
    } catch (e) {
        manejarError(e);
    } finally {
        ocupado.value = null;
    }
};

const dialogoPublicar = ref(false);
const publicar = async () => {
    if (ocupado.value || !props.edicion.puede) {
        return;
    }

    ocupado.value = 'publicar';

    try {
        const r = await pedir(rutaPublicar.url(), {
            metodo: 'POST',
            datos: {
                campana_id: props.variante?.campana_id ?? null,
                version: servidor.value.version,
                documento: doc.value,
            },
        });
        aplicarRespuesta((await r.json()) as RespuestaDiseno);
        dialogoPublicar.value = false;
    } catch (e) {
        manejarError(e);
        dialogoPublicar.value = false;
    } finally {
        ocupado.value = null;
    }
};

const versionARestaurar = ref<VersionDiseno | null>(null);
const dialogoRestaurar = computed({
    get: () => versionARestaurar.value !== null,
    set: (v: boolean) => {
        if (!v && !ocupado.value) {
            versionARestaurar.value = null;
        }
    },
});
const restaurar = async () => {
    const v = versionARestaurar.value;

    if (!v || ocupado.value) {
        return;
    }

    ocupado.value = 'restaurar';

    try {
        const r = await pedir(rutaRestaurar.url(v.id), {
            metodo: 'POST',
            datos: { version: servidor.value.version },
        });
        aplicarRespuesta((await r.json()) as RespuestaDiseno);
        versionPrevia.value = null;
    } catch (e) {
        manejarError(e);
    } finally {
        ocupado.value = null;
        versionARestaurar.value = null;
    }
};

const descartar = () => reemplazar(JSON.parse(guardado.value));
const volverAPublicado = () => {
    if (servidor.value.publicado) {
        reemplazar(servidor.value.publicado);
        toast.info('Se cargó el diseño publicado. Guarda para conservarlo.');
    }
};
const restablecer = () => {
    reemplazar({
        ...clonar(props.predeterminado),
        identidad: {
            ...props.predeterminado.identidad,
            logo: doc.value.identidad.logo,
        },
    });
    toast.info(
        'Se aplicó el diseño predeterminado. Puedes deshacerlo con Ctrl+Z.',
    );
};

const usarPlantilla = (p: PlantillaDiseno) => {
    const actual = doc.value;
    reemplazar({
        ...clonar(p.documento),
        identidad: {
            ...p.documento.identidad,
            logo: actual.identidad.logo,
            portada: actual.identidad.portada,
        },
        contenido: {
            ...p.documento.contenido,
            saludo: actual.contenido.saludo,
            secciones: clonar(actual.contenido.secciones),
            terminos: actual.contenido.terminos,
        },
    });
    toast.success(`Plantilla «${p.nombre}» aplicada.`, {
        description: 'Puedes deshacerlo con el botón Deshacer.',
    });
};

// ---- Variantes por campaña ----
const cambiarVariante = (valor: string) => {
    router.get(
        edit.url(valor === '' ? undefined : { query: { campana: valor } }),
    );
};
const dialogoQuitarVariante = ref(false);
const quitarVariante = async () => {
    if (!props.variante || ocupado.value) {
        return;
    }

    ocupado.value = 'variante';

    try {
        const r = await enviar<{ mensaje: string }>(
            rutaQuitarVariante.url(props.variante.campana_id),
            'DELETE',
        );
        toast.success(r.mensaje);
        saltarAviso = true;
        router.get(edit.url());
    } catch (e) {
        manejarError(e);
    } finally {
        ocupado.value = null;
        dialogoQuitarVariante.value = false;
    }
};

const recargar = () => {
    saltarAviso = true;
    window.location.reload();
};

// ---- Secciones y vista en móvil ----
const secciones = computed(() =>
    [
        { clave: 'plantillas', nombre: 'Plantillas', icono: LayoutTemplate },
        { clave: 'identidad', nombre: 'Identidad', icono: Store },
        { clave: 'tarjeta', nombre: 'Tarjeta', icono: CreditCard },
        { clave: 'progreso', nombre: 'Sellos', icono: Stamp },
        { clave: 'mensajes', nombre: 'Mensajes', icono: MessageSquareText },
        { clave: 'pagina', nombre: 'Página', icono: PanelTop },
        { clave: 'historial', nombre: 'Historial', icono: History },
    ].filter((s) => !(esVariante.value && s.clave === 'pagina')),
);
const seccion = ref(props.diseno.existe ? 'tarjeta' : 'plantillas');
const vistaMovil = ref<'herramientas' | 'vista'>('herramientas');
const avisosPorSeccion = (clave: string) =>
    avisos.value.filter((a) => a.seccion === clave).length;

// ---- Vista previa ----
const campanaPrevia = ref<number | null>(
    props.variante?.campana_id ?? props.campanas[0]?.id ?? null,
);
const metaInicial =
    props.campanas.find((c) => c.id === campanaPrevia.value)?.meta ?? 10;
const visitasPrevia = ref(Math.round(metaInicial * 0.6));
const estadoPrevia = ref<EstadoPrograma>('en_progreso');
const modoPrevia = ref<'tarjeta' | 'pagina'>('tarjeta');
const versionPrevia = ref<VersionDiseno | null>(null);
const documentoPrevia = computed(
    () => versionPrevia.value?.documento ?? doc.value,
);

watch(versionPrevia, (v) => v && cargarFuentes([v.documento]));

const estadoTexto = computed(() => {
    if (sucio.value) {
        return 'Cambios sin guardar';
    }

    if (!servidor.value.existe) {
        return esVariante.value
            ? 'Esta campaña usa el diseño general'
            : 'Tus clientes ven el diseño predeterminado';
    }

    if (servidor.value.cambios_sin_publicar) {
        return 'Borrador guardado, sin publicar';
    }

    return `Publicado ${relativo(servidor.value.publicado_en)}`;
});
</script>

<template>
    <Head title="Diseño de tarjeta" />

    <EncabezadoPagina
        titulo="Diseño de tarjeta"
        descripcion="Personaliza la tarjeta digital y la página que ven tus clientes al escanear su tarjeta o abrir su enlace."
        ayuda="El diseño solo cambia la apariencia. Las visitas, metas y recompensas vienen siempre de tus campañas y no se modifican al publicar."
        :icono="Palette"
    />

    <NavFidelidad :servicio="servicio" />

    <p
        v-if="!edicion.puede && edicion.motivo"
        class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-sm text-warning"
        role="status"
    >
        <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        {{ edicion.motivo }}
    </p>

    <div
        v-if="conflicto"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
        role="alert"
    >
        <span class="flex items-start gap-2">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Otra persona (u otra pestaña) guardó cambios en este diseño. Tus
            cambios no se guardaron: recarga para trabajar sobre la versión más
            reciente.
        </span>
        <Button size="sm" variant="outline" @click="recargar">Recargar</Button>
    </div>

    <!-- Barra de acciones -->
    <div
        class="sticky top-0 z-20 -mx-1 flex flex-wrap items-center gap-2 rounded-xl border bg-card/95 px-3 py-2 shadow-xs backdrop-blur"
    >
        <label class="sr-only" for="variante">Diseño que estás editando</label>
        <select
            id="variante"
            class="h-9 w-full min-w-0 rounded-lg border border-input bg-background px-2 text-sm sm:w-auto sm:max-w-[14rem]"
            :value="variante ? String(variante.campana_id) : ''"
            @change="
                cambiarVariante(($event.target as HTMLSelectElement).value)
            "
        >
            <option value="">Diseño general</option>
            <option v-for="c in campanas" :key="c.id" :value="String(c.id)">
                {{ c.variante ? 'Variante: ' : 'Crear variante: '
                }}{{ c.nombre }}
            </option>
        </select>
        <span
            class="hidden text-xs text-muted-foreground sm:inline"
            role="status"
            aria-live="polite"
            >{{ estadoTexto }}</span
        >
        <div
            class="ml-auto flex w-full flex-wrap items-center justify-end gap-1.5 sm:w-auto"
        >
            <Button
                variant="ghost"
                size="icon"
                :disabled="!puedeDeshacer || !edicion.puede"
                aria-label="Deshacer (Ctrl+Z)"
                title="Deshacer (Ctrl+Z)"
                @click="deshacer"
            >
                <Undo2 />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :disabled="!puedeRehacer || !edicion.puede"
                aria-label="Rehacer (Ctrl+Shift+Z)"
                title="Rehacer (Ctrl+Shift+Z)"
                @click="rehacer"
            >
                <Redo2 />
            </Button>
            <DropdownMenu v-if="edicion.puede">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Más acciones"
                    >
                        <Ellipsis />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64">
                    <DropdownMenuItem :disabled="!sucio" @select="descartar">
                        <RotateCcw /> Descartar cambios sin guardar
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        :disabled="!servidor.publicado"
                        @select="volverAPublicado"
                    >
                        <Eye /> Cargar el diseño publicado
                    </DropdownMenuItem>
                    <DropdownMenuItem @select="restablecer">
                        <Palette /> Restablecer diseño predeterminado
                    </DropdownMenuItem>
                    <template v-if="variante && servidor.existe">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive"
                            @select="dialogoQuitarVariante = true"
                        >
                            Quitar variante (usar el diseño general)
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
            <template v-if="edicion.puede">
                <Button
                    variant="outline"
                    :disabled="ocupado !== null || !sucio"
                    @click="guardar"
                >
                    <Spinner v-if="ocupado === 'guardar'" />
                    <Save v-else />
                    <span class="hidden sm:inline">Guardar borrador</span>
                    <span class="max-[379px]:sr-only sm:hidden">Guardar</span>
                </Button>
                <Button
                    :disabled="
                        ocupado !== null ||
                        (!sucio &&
                            servidor.existe &&
                            !servidor.cambios_sin_publicar)
                    "
                    @click="dialogoPublicar = true"
                >
                    <Send /> Publicar
                </Button>
            </template>
        </div>
        <span class="w-full text-xs text-muted-foreground sm:hidden">{{
            estadoTexto
        }}</span>
    </div>

    <p
        v-if="variante"
        class="rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
    >
        Estás editando la tarjeta de «{{ variante.nombre }}». El fondo de la
        página, la imagen principal y la información general se toman del diseño
        general.
    </p>

    <!-- Selector de vista en móvil -->
    <div
        class="inline-flex w-full rounded-lg border bg-background p-0.5 lg:hidden"
        role="group"
        aria-label="Mostrar"
    >
        <button
            type="button"
            class="h-9 flex-1 rounded-md text-sm font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
            :aria-pressed="vistaMovil === 'herramientas'"
            @click="vistaMovil = 'herramientas'"
        >
            Editar
        </button>
        <button
            type="button"
            class="h-9 flex-1 rounded-md text-sm font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
            :aria-pressed="vistaMovil === 'vista'"
            @click="vistaMovil = 'vista'"
        >
            Vista previa
        </button>
    </div>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_400px] xl:grid-cols-[minmax(0,1fr)_420px]"
    >
        <!-- Herramientas -->
        <section
            class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-xs"
            :class="vistaMovil === 'vista' ? 'hidden lg:block' : ''"
            aria-label="Herramientas de diseño"
        >
            <nav
                class="flex gap-1 overflow-x-auto border-b px-2 py-2"
                aria-label="Secciones del diseño"
            >
                <button
                    v-for="s in secciones"
                    :key="s.clave"
                    type="button"
                    class="relative inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground aria-[current=true]:bg-primary/10 aria-[current=true]:text-primary"
                    :aria-current="seccion === s.clave"
                    @click="seccion = s.clave"
                >
                    <component
                        :is="s.icono"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ s.nombre }}
                    <span
                        v-if="avisosPorSeccion(s.clave)"
                        class="ml-0.5 inline-flex size-4 items-center justify-center rounded-full bg-warning text-[0.6rem] font-bold text-white"
                        :aria-label="`${avisosPorSeccion(s.clave)} avisos de legibilidad`"
                        >{{ avisosPorSeccion(s.clave) }}</span
                    >
                </button>
            </nav>

            <div class="p-4 sm:p-5">
                <!-- Historial (también disponible en solo lectura) -->
                <div v-if="seccion === 'historial'" class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        Cada publicación queda guardada. Puedes verla en la
                        vista previa y restaurarla en tu borrador (se guarda un
                        respaldo de lo que tengas antes).
                    </p>
                    <ul
                        v-if="versiones.length"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="v in versiones"
                            :key="v.id"
                            class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5"
                        >
                            <div class="min-w-0 text-sm">
                                <p class="font-medium">{{ v.motivo }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ fechaHora(v.en) }}
                                    <template v-if="v.autor"
                                        >· {{ v.autor }}</template
                                    >
                                </p>
                            </div>
                            <div class="flex gap-1.5">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    :aria-pressed="versionPrevia?.id === v.id"
                                    @click="
                                        versionPrevia = v;
                                        vistaMovil = 'vista';
                                    "
                                >
                                    <Eye /> Ver
                                </Button>
                                <Button
                                    v-if="edicion.puede"
                                    size="sm"
                                    variant="outline"
                                    @click="versionARestaurar = v"
                                >
                                    <RotateCcw /> Restaurar
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
                    >
                        Aún no hay publicaciones. Cuando publiques, aparecerán
                        aquí.
                    </p>
                </div>

                <fieldset
                    v-else
                    :disabled="!edicion.puede"
                    class="min-w-0 disabled:opacity-90"
                >
                    <legend class="sr-only">
                        {{ secciones.find((s) => s.clave === seccion)?.nombre }}
                    </legend>
                    <PanelHerramientas
                        v-model:documento="doc"
                        :seccion="seccion"
                        :archivos="archivos"
                        :plantillas="plantillas"
                        :es-variante="esVariante"
                        :avisos="avisos"
                        @subida="archivos = [$event, ...archivos]"
                        @plantilla="usarPlantilla"
                    />
                </fieldset>
            </div>
        </section>

        <!-- Vista previa -->
        <section
            class="min-w-0 lg:sticky lg:top-20 lg:self-start"
            :class="vistaMovil === 'herramientas' ? 'hidden lg:block' : ''"
            aria-label="Vista previa"
        >
            <VistaPrevia
                v-model:campana="campanaPrevia"
                v-model:modo="modoPrevia"
                v-model:visitas="visitasPrevia"
                v-model:estado="estadoPrevia"
                :documento="documentoPrevia"
                :general="general"
                :negocio="negocio"
                :campanas="campanas"
                :medios="medios"
                :viendo-version="
                    versionPrevia
                        ? `Versión del ${fechaHora(versionPrevia.en)}`
                        : null
                "
                @salir-version="versionPrevia = null"
            />
        </section>
    </div>

    <DialogoConfirmacion
        v-model:open="dialogoPublicar"
        titulo="¿Publicar el diseño?"
        :descripcion="
            variante
                ? `Los clientes de «${variante.nombre}» verán la nueva tarjeta de inmediato. Sus visitas y recompensas no cambian.`
                : 'Tus clientes verán la nueva tarjeta de inmediato. Sus visitas, metas y recompensas no cambian.'
        "
        confirmar="Publicar"
        :procesando="ocupado === 'publicar'"
        @confirmar="publicar"
    >
        <p
            v-if="avisos.length"
            class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-xs text-warning"
        >
            <TriangleAlert
                class="mt-0.5 size-3.5 shrink-0"
                aria-hidden="true"
            />
            Hay {{ avisos.length }}
            {{ avisos.length === 1 ? 'aviso' : 'avisos' }} de legibilidad.
            Puedes publicar de todos modos, pero revisarlos ayuda a que tus
            clientes lean bien su tarjeta.
        </p>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        v-model:open="dialogoRestaurar"
        titulo="¿Restaurar esta versión?"
        :descripcion="
            sucio
                ? 'Se cargará en tu borrador. Perderás los cambios que no has guardado (el borrador guardado se respalda en el historial). No se publica hasta que lo decidas.'
                : 'Se cargará en tu borrador (el borrador actual se respalda en el historial). No se publica hasta que lo decidas.'
        "
        confirmar="Restaurar en borrador"
        :procesando="ocupado === 'restaurar'"
        @confirmar="restaurar"
    />

    <DialogoConfirmacion
        v-model:open="dialogoQuitarVariante"
        titulo="¿Quitar la variante?"
        :descripcion="`«${variante?.nombre}» volverá a usar el diseño general. Se borrará el historial de esta variante.`"
        confirmar="Quitar variante"
        destructivo
        :procesando="ocupado === 'variante'"
        @confirmar="quitarVariante"
    />
</template>
