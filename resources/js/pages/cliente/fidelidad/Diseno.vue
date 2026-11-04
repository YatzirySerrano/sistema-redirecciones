<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useElementSize } from '@vueuse/core';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Ellipsis,
    Eye,
    Lock,
    Palette,
    PencilRuler,
    Redo2,
    RotateCcw,
    Save,
    Send,
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
import Ayuda from '@/components/comun/Ayuda.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import { cargarFuentes } from '@/components/fidelidad-diseno/catalogo';
import type { ClaveZona } from '@/components/fidelidad-diseno/edicion';
import type {
    ClaveDecoracion,
    DocumentoDiseno,
    EstadoPrograma,
} from '@/components/fidelidad-diseno/tipos';
import { enviar } from '@/components/fidelidad/diseno/api';
import { avisosLegibilidad } from '@/components/fidelidad/diseno/legibilidad';
import PanelHerramientas from '@/components/fidelidad/diseno/PanelHerramientas.vue';
import {
    herramienta as datosHerramienta,
    herramientasCambiadas,
    herramientasDe,
    pasoDe,
    PASOS,
    ZONAS,
} from '@/components/fidelidad/diseno/pasos';
import type {
    ClaveHerramienta,
    Paso,
} from '@/components/fidelidad/diseno/pasos';
import RevisarPublicar from '@/components/fidelidad/diseno/RevisarPublicar.vue';
import type {
    ArchivoImagen,
    CampanaDiseno,
    EstadoDiseno,
    PlantillaDiseno,
    RespuestaDiseno,
    VersionDiseno,
} from '@/components/fidelidad/diseno/tipos';
import VistaPrevia from '@/components/fidelidad/diseno/VistaPrevia.vue';
import AvisoDisenoDasti from '@/components/disenos/AvisoDisenoDasti.vue';
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
import { edit } from '@/routes/cliente/fidelidad/diseno';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Diseñar mi programa', href: edit() },
        ],
    },
});

/** URL que entrega el controlador (DisenoController::rutasEditor). */
type RutasEditor = {
    editar: string;
    editar_campana: string;
    guardar: string;
    publicar: string;
    restaurar: string;
    quitar_variante: string;
    subir_imagen: string;
    inicio: string;
    campanas: string;
};

const props = defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    /** Personal DASTI diseñando para el negocio («Diseño para clientes»). */
    contexto_dasti?: {
        negocio_id: number;
        negocio: string;
        volver: string;
    } | null;
    rutas: RutasEditor;
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
const conId = (plantilla: string, id: number | string) =>
    plantilla.replace('__ID__', encodeURIComponent(String(id)));

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
const puedePublicar = computed(
    () =>
        props.edicion.puede &&
        ocupado.value === null &&
        (sucio.value ||
            !servidor.value.existe ||
            servidor.value.cambios_sin_publicar),
);

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

const cuerpo = () => ({
    campana_id: props.variante?.campana_id ?? null,
    version: servidor.value.version,
    documento: doc.value,
});

const guardar = async () => {
    if (ocupado.value || !props.edicion.puede) {
        return;
    }

    ocupado.value = 'guardar';

    try {
        const r = await pedir(props.rutas.guardar, {
            metodo: 'PUT',
            datos: cuerpo(),
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
        const r = await pedir(props.rutas.publicar, {
            metodo: 'POST',
            datos: cuerpo(),
        });
        aplicarRespuesta((await r.json()) as RespuestaDiseno);
    } catch (e) {
        manejarError(e);
    } finally {
        dialogoPublicar.value = false;
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
        const r = await pedir(conId(props.rutas.restaurar, v.id), {
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

const dialogoDescartar = ref(false);
const descartar = () => {
    reemplazar(JSON.parse(guardado.value));
    dialogoDescartar.value = false;
};
const volverAPublicado = () => {
    if (servidor.value.publicado) {
        reemplazar(servidor.value.publicado);
        toast.info('Se cargó el diseño publicado. Guarda para conservarlo.');
    }
};
const dialogoRestablecer = ref(false);
const restablecer = () => {
    reemplazar({
        ...clonar(props.predeterminado),
        identidad: {
            ...props.predeterminado.identidad,
            logo: doc.value.identidad.logo,
        },
    });
    dialogoRestablecer.value = false;
    toast.info('Se recuperaron los colores y estilos originales.', {
        action: { label: 'Deshacer', onClick: deshacer },
    });
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
        description: 'Sigue con el paso 2 para ponerle tu identidad.',
        action: { label: 'Deshacer', onClick: deshacer },
    });
};

// ---- Diseño general o de una campaña ----
const cambiarVariante = (valor: string) => {
    router.get(
        valor === ''
            ? props.rutas.editar
            : conId(props.rutas.editar_campana, valor),
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
            conId(props.rutas.quitar_variante, props.variante.campana_id),
            'DELETE',
        );
        toast.success(r.mensaje);
        saltarAviso = true;
        router.get(props.rutas.editar);
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

// ---- Pasos y herramientas ----
const raiz = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const { width: anchoEditor } = useElementSize(raiz);
/** Mismo corte que `@2xl/editor` (42rem): una sola columna. */
const angosto = computed(
    () => anchoEditor.value > 0 && anchoEditor.value < 672,
);

const herramientaActual = ref<ClaveHerramienta>(
    props.diseno.existe ? 'encabezado' : 'plantillas',
);
const pasoActual = computed<Paso>(() => pasoDe(herramientaActual.value));
const herramientasPaso = computed(() =>
    herramientasDe(pasoActual.value, esVariante.value),
);
const vistaMovil = ref<'vista' | 'editar'>('editar');
const zonaElegida = ref<ClaveZona | null>(null);

/** Zona resaltada en la vista previa: la tocada o la de la herramienta. */
const zonaSeleccionada = computed<ClaveZona | null>(() => {
    if (
        zonaElegida.value &&
        ZONAS[zonaElegida.value].herramienta === herramientaActual.value
    ) {
        return zonaElegida.value;
    }

    const encontrada = (Object.keys(ZONAS) as ClaveZona[]).find(
        (z) =>
            ZONAS[z].herramienta === herramientaActual.value &&
            !(esVariante.value && ZONAS[z].soloGeneral),
    );

    return encontrada ?? null;
});

const mostrarPanel = () => {
    if (angosto.value) {
        vistaMovil.value = 'editar';
    }

    void nextTick(() => {
        const p = panel.value;

        if (p && p.getBoundingClientRect().top < 60) {
            p.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    });
};

const abrirHerramienta = (clave: ClaveHerramienta) => {
    herramientaActual.value = clave;
    mostrarPanel();
};

const abrirPaso = (paso: Paso) => {
    herramientaActual.value = herramientasDe(paso, esVariante.value)[0].clave;
    mostrarPanel();
};

const seleccionarZona = (zona: ClaveZona) => {
    zonaElegida.value = zona;
    herramientaActual.value = ZONAS[zona].herramienta;
    mostrarPanel();
};

const pasoVecino = (delta: number) =>
    PASOS.find((p) => p.numero === pasoActual.value.numero + delta) ?? null;

const avisosPaso = (paso: Paso) =>
    avisos.value.filter((a) =>
        herramientasDe(paso, esVariante.value).some(
            (h) => h.clave === a.herramienta,
        ),
    ).length;
const avisosHerramienta = (clave: ClaveHerramienta) =>
    avisos.value.filter((a) => a.herramienta === clave).length;

// ---- Resumen de cambios (paso 4) ----
const referencia = computed(
    () =>
        servidor.value.publicado ??
        (esVariante.value ? props.general : null) ??
        props.predeterminado,
);
const cambios = computed(() =>
    herramientasCambiadas(doc.value, referencia.value, esVariante.value),
);

// ---- Vista previa ----
const campanaPrevia = ref<number | null>(
    props.variante?.campana_id ?? props.campanas[0]?.id ?? null,
);
const metaInicial =
    props.campanas.find((c) => c.id === campanaPrevia.value)?.meta ?? 10;
const visitasPrevia = ref(Math.round(metaInicial * 0.6));
const estadoPrevia = ref<EstadoPrograma>('en_progreso');
const versionPrevia = ref<VersionDiseno | null>(null);
/** Previsualización temporal (p. ej. una decoración bajo el cursor). */
const previa = ref<{
    ruta: 'pagina.decoracion' | 'tarjeta.decoracion';
    valor: ClaveDecoracion;
} | null>(null);
const documentoPrevia = computed<DocumentoDiseno>(() => {
    if (versionPrevia.value) {
        return versionPrevia.value.documento;
    }

    const p = previa.value;

    if (!p) {
        return doc.value;
    }

    return p.ruta === 'pagina.decoracion'
        ? { ...doc.value, pagina: { ...doc.value.pagina, decoracion: p.valor } }
        : {
              ...doc.value,
              tarjeta: { ...doc.value.tarjeta, decoracion: p.valor },
          };
});

watch(versionPrevia, (v) => v && cargarFuentes([v.documento]));
watch(herramientaActual, () => (previa.value = null));

const verVersion = (v: VersionDiseno) => {
    versionPrevia.value = v;

    if (angosto.value) {
        vistaMovil.value = 'vista';
    }
};

// ---- Barra inferior en teléfonos: se oculta mientras se escribe ----
const escribiendo = ref(false);
const alEnfocar = (e: FocusEvent) => {
    const t = e.target as HTMLElement | null;
    escribiendo.value =
        !!t?.matches(
            'input:not([type=range]):not([type=color]):not([type=checkbox]):not([type=radio]):not([type=file]), textarea',
        ) && angosto.value;
};
const alDesenfocar = () => (escribiendo.value = false);

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

const destino = computed(() =>
    props.variante
        ? `Los clientes de «${props.variante.nombre}» verán la nueva página de inmediato.`
        : 'Todos tus clientes verán la nueva página de inmediato.',
);
</script>

<template>
    <Head title="Diseñar mi programa" />

    <AvisoDisenoDasti
        v-if="contexto_dasti"
        :contexto="contexto_dasti"
        editor="Diseñar Fidelidad"
        class="mb-4"
    />
    <NavFidelidad v-else :servicio="servicio" />

    <EncabezadoPagina
        titulo="Diseñar mi programa"
        descripcion="Personaliza la página que abren tus clientes con su tarjeta o enlace: tu identidad, su tarjeta con el avance de visitas y la información del programa."
        ayuda="El diseño solo cambia la apariencia. Las visitas, metas, recompensas y canjes vienen de tus campañas y nunca cambian al guardar o publicar."
        :icono="Palette"
    />

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

    <div
        ref="raiz"
        class="@container/editor"
        @focusin="alEnfocar"
        @focusout="alDesenfocar"
    >
        <div class="flex flex-col gap-4">
            <!-- Qué diseño se edita -->
            <section
                class="flex flex-col gap-2 rounded-xl border bg-card px-3 py-3 shadow-xs @2xl/editor:flex-row @2xl/editor:items-center"
                aria-label="Diseño que estás editando"
            >
                <div
                    class="flex min-w-0 flex-1 flex-col gap-1.5 @lg/editor:flex-row @lg/editor:items-center"
                >
                    <label
                        for="variante"
                        class="flex shrink-0 items-center gap-1.5 text-sm font-medium"
                    >
                        Estás editando
                        <Ayuda
                            texto="El diseño general se usa en todas tus campañas. Si una campaña necesita una tarjeta distinta, crea su propio diseño: la página (fondo, imagen principal e información) sigue siendo la del diseño general."
                        />
                    </label>
                    <select
                        id="variante"
                        class="h-10 w-full min-w-0 rounded-lg border border-input bg-background px-2 text-sm @lg/editor:max-w-xs"
                        :value="variante ? String(variante.campana_id) : ''"
                        @change="
                            cambiarVariante(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">
                            Diseño general (todas las campañas)
                        </option>
                        <option
                            v-for="c in campanas"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{
                                c.variante
                                    ? `Diseño para «${c.nombre}»`
                                    : `Crear diseño para «${c.nombre}»`
                            }}
                        </option>
                    </select>
                </div>
                <p
                    class="text-xs text-muted-foreground @2xl/editor:max-w-xs @2xl/editor:text-right"
                    role="status"
                    aria-live="polite"
                >
                    <span class="font-medium text-foreground">{{
                        estadoTexto
                    }}</span>
                    <template v-if="variante">
                        · Solo cambia la tarjeta de «{{ variante.nombre }}».
                    </template>
                </p>
            </section>

            <!-- Acciones: arriba en pantallas amplias, abajo en teléfonos -->
            <div
                class="sticky bottom-0 z-30 order-last -mx-4 border-t bg-card/95 px-4 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_-6px_18px_-12px_rgb(0_0_0/0.3)] backdrop-blur @2xl/editor:top-14 @2xl/editor:bottom-auto @2xl/editor:order-none @2xl/editor:mx-0 @2xl/editor:rounded-xl @2xl/editor:border @2xl/editor:px-3 @2xl/editor:pb-2 @2xl/editor:shadow-xs"
                :class="escribiendo ? 'max-@2xl/editor:hidden' : ''"
            >
                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-2">
                    <div
                        class="grid basis-full grid-cols-2 gap-1 rounded-lg border bg-background p-0.5 @md/editor:max-w-xs @md/editor:flex-1 @md/editor:basis-auto @2xl/editor:hidden"
                        role="group"
                        aria-label="Mostrar"
                    >
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-md text-sm font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                            :aria-pressed="vistaMovil === 'vista'"
                            @click="vistaMovil = 'vista'"
                        >
                            <Eye class="size-4" aria-hidden="true" /> Vista
                            previa
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-md text-sm font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                            :aria-pressed="vistaMovil === 'editar'"
                            @click="vistaMovil = 'editar'"
                        >
                            <PencilRuler class="size-4" aria-hidden="true" />
                            Editar
                        </button>
                    </div>
                    <div class="flex flex-1 items-center gap-1.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-10"
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
                            class="size-10"
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
                                    class="size-10"
                                    aria-label="Más acciones"
                                >
                                    <Ellipsis />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-72">
                                <DropdownMenuItem
                                    class="min-h-10"
                                    :disabled="!sucio"
                                    @select="dialogoDescartar = true"
                                >
                                    <RotateCcw /> Descartar cambios sin guardar
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    class="min-h-10"
                                    :disabled="!servidor.publicado"
                                    @select="volverAPublicado"
                                >
                                    <Eye /> Cargar el diseño publicado
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    class="min-h-10"
                                    @select="dialogoRestablecer = true"
                                >
                                    <Palette /> Restablecer colores originales
                                </DropdownMenuItem>
                                <template v-if="variante && servidor.existe">
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="min-h-10 text-destructive"
                                        @select="dialogoQuitarVariante = true"
                                    >
                                        Quitar el diseño de esta campaña
                                    </DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <div
                            v-if="edicion.puede"
                            class="ml-auto flex items-center gap-1.5"
                        >
                            <Button
                                variant="outline"
                                class="h-10"
                                :disabled="ocupado !== null || !sucio"
                                aria-label="Guardar borrador"
                                @click="guardar"
                            >
                                <Spinner v-if="ocupado === 'guardar'" />
                                <Save v-else />
                                <span class="max-[379px]:hidden">Guardar</span>
                            </Button>
                            <Button
                                class="h-10"
                                :disabled="!puedePublicar"
                                @click="dialogoPublicar = true"
                            >
                                <Send /> Publicar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-5 @2xl/editor:grid-cols-[minmax(0,1fr)_minmax(300px,360px)] @5xl/editor:grid-cols-[13.5rem_minmax(0,1fr)_380px]"
            >
                <!-- Pasos (columna en pantallas muy amplias) -->
                <nav
                    class="hidden @5xl/editor:block"
                    aria-label="Pasos del diseño"
                >
                    <ol class="sticky top-32 space-y-1">
                        <li v-for="p in PASOS" :key="p.clave">
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm transition hover:bg-muted aria-[current=step]:bg-primary/8 aria-[current=step]:font-semibold aria-[current=step]:text-primary"
                                :aria-current="
                                    pasoActual.clave === p.clave
                                        ? 'step'
                                        : undefined
                                "
                                @click="abrirPaso(p)"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-bold"
                                    :class="
                                        pasoActual.clave === p.clave
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background'
                                    "
                                    >{{ p.numero }}</span
                                >
                                <span class="min-w-0 flex-1">{{
                                    p.nombre
                                }}</span>
                                <span
                                    v-if="avisosPaso(p)"
                                    class="inline-flex size-5 items-center justify-center rounded-full bg-warning text-[0.65rem] font-bold text-white"
                                    :aria-label="`${avisosPaso(p)} avisos de legibilidad`"
                                    >{{ avisosPaso(p) }}</span
                                >
                            </button>
                            <ul
                                v-if="
                                    pasoActual.clave === p.clave &&
                                    herramientasPaso.length > 1
                                "
                                class="mt-1 mb-2 ml-5 space-y-0.5 border-l pl-2"
                            >
                                <li
                                    v-for="h in herramientasPaso"
                                    :key="h.clave"
                                >
                                    <button
                                        type="button"
                                        class="flex min-h-10 w-full items-center gap-2 rounded-md px-2 text-left text-sm text-muted-foreground hover:bg-muted hover:text-foreground aria-[current=true]:bg-muted aria-[current=true]:font-medium aria-[current=true]:text-foreground"
                                        :aria-current="
                                            herramientaActual === h.clave
                                        "
                                        @click="abrirHerramienta(h.clave)"
                                    >
                                        <component
                                            :is="h.icono"
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="min-w-0 flex-1 leading-tight"
                                            >{{ h.nombre }}</span
                                        >
                                        <TriangleAlert
                                            v-if="avisosHerramienta(h.clave)"
                                            class="size-3.5 text-warning"
                                            aria-label="Con avisos"
                                        />
                                    </button>
                                </li>
                            </ul>
                        </li>
                    </ol>
                </nav>

                <!-- Propiedades del paso -->
                <section
                    ref="panel"
                    class="min-w-0 scroll-mt-32 overflow-hidden rounded-xl border bg-card shadow-xs"
                    :class="
                        vistaMovil === 'vista' ? 'max-@2xl/editor:hidden' : ''
                    "
                    :aria-label="`Paso ${pasoActual.numero}: ${pasoActual.nombre}`"
                >
                    <!-- Pasos compactos -->
                    <nav
                        class="border-b p-2 @5xl/editor:hidden"
                        aria-label="Pasos del diseño"
                    >
                        <ol class="grid grid-cols-4 gap-1">
                            <li v-for="p in PASOS" :key="p.clave">
                                <button
                                    type="button"
                                    class="relative flex min-h-12 w-full flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-1 text-[0.7rem] leading-tight font-medium text-muted-foreground transition hover:bg-muted aria-[current=step]:bg-primary/8 aria-[current=step]:text-primary"
                                    :aria-current="
                                        pasoActual.clave === p.clave
                                            ? 'step'
                                            : undefined
                                    "
                                    :aria-label="`Paso ${p.numero}: ${p.nombre}`"
                                    @click="abrirPaso(p)"
                                >
                                    <span
                                        class="flex size-6 items-center justify-center rounded-full border text-xs font-bold"
                                        :class="
                                            pasoActual.clave === p.clave
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'bg-background'
                                        "
                                        >{{ p.numero }}</span
                                    >
                                    <span class="truncate">{{ p.corto }}</span>
                                    <span
                                        v-if="avisosPaso(p)"
                                        class="absolute top-1 right-1 size-2 rounded-full bg-warning"
                                        aria-hidden="true"
                                    />
                                </button>
                            </li>
                        </ol>
                    </nav>

                    <div class="space-y-4 p-4 sm:p-5">
                        <header class="space-y-1">
                            <p
                                class="text-[0.68rem] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Paso {{ pasoActual.numero }} de
                                {{ PASOS.length }}
                            </p>
                            <h2 class="text-lg font-semibold">
                                {{ pasoActual.nombre }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ pasoActual.descripcion }}
                            </p>
                        </header>

                        <!-- Herramientas del paso (compactas) -->
                        <div
                            v-if="herramientasPaso.length > 1"
                            class="grid grid-cols-2 gap-1.5 @sm/editor:grid-cols-3 @2xl/editor:grid-cols-2 @5xl/editor:hidden"
                            role="tablist"
                            aria-label="Herramientas del paso"
                        >
                            <button
                                v-for="h in herramientasPaso"
                                :key="h.clave"
                                type="button"
                                role="tab"
                                :aria-selected="herramientaActual === h.clave"
                                class="relative flex min-h-10 items-center gap-1.5 rounded-lg border bg-background px-2 text-left text-xs font-medium transition hover:bg-muted aria-selected:border-primary aria-selected:bg-primary/8 aria-selected:text-primary"
                                @click="abrirHerramienta(h.clave)"
                            >
                                <component
                                    :is="h.icono"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1 leading-tight">{{
                                    h.nombre
                                }}</span>
                                <TriangleAlert
                                    v-if="avisosHerramienta(h.clave)"
                                    class="size-3.5 shrink-0 text-warning"
                                    aria-label="Con avisos"
                                />
                            </button>
                        </div>
                        <h3
                            v-if="herramientasPaso.length > 1"
                            class="hidden items-center gap-2 border-t pt-4 text-sm font-semibold @5xl/editor:flex"
                        >
                            <component
                                :is="datosHerramienta(herramientaActual).icono"
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ datosHerramienta(herramientaActual).nombre }}
                        </h3>

                        <RevisarPublicar
                            v-if="herramientaActual === 'revisar'"
                            :cambios="cambios"
                            :avisos="avisos"
                            :servidor="servidor"
                            :sucio="sucio"
                            :puede-publicar="puedePublicar"
                            :puede-editar="edicion.puede"
                            :publicando="ocupado === 'publicar'"
                            :destino="destino"
                            :versiones="versiones"
                            :version-vista="versionPrevia?.id ?? null"
                            @ir="abrirHerramienta"
                            @publicar="dialogoPublicar = true"
                            @ver="verVersion"
                            @restaurar="versionARestaurar = $event"
                        />
                        <fieldset
                            v-else
                            :disabled="!edicion.puede"
                            class="min-w-0 disabled:opacity-90"
                        >
                            <legend class="sr-only">
                                {{ datosHerramienta(herramientaActual).nombre }}
                            </legend>
                            <PanelHerramientas
                                v-model:documento="doc"
                                :herramienta="herramientaActual"
                                :archivos="archivos"
                                :plantillas="plantillas"
                                :es-variante="esVariante"
                                :avisos="avisos"
                                :url-subida="rutas.subir_imagen"
                                :medios="medios"
                                :general="general"
                                @subida="archivos = [$event, ...archivos]"
                                @plantilla="usarPlantilla"
                                @previsualizar="previa = $event"
                            />
                        </fieldset>

                        <!-- Orden sugerido -->
                        <div
                            class="flex items-center justify-between gap-2 border-t pt-4"
                        >
                            <Button
                                v-if="pasoVecino(-1)"
                                variant="ghost"
                                class="h-10"
                                @click="abrirPaso(pasoVecino(-1)!)"
                            >
                                <ArrowLeft /> Anterior
                            </Button>
                            <span v-else />
                            <Button
                                v-if="pasoVecino(1)"
                                variant="outline"
                                class="h-10"
                                @click="abrirPaso(pasoVecino(1)!)"
                            >
                                {{ pasoVecino(1)!.corto }}
                                <ArrowRight />
                            </Button>
                            <span
                                v-else-if="!cambios.length && !sucio"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Check class="size-4" aria-hidden="true" />
                                Todo listo
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Vista previa de la página completa -->
                <section
                    class="min-w-0 @2xl/editor:sticky @2xl/editor:top-32 @2xl/editor:self-start"
                    :class="
                        vistaMovil === 'editar' ? 'max-@2xl/editor:hidden' : ''
                    "
                    aria-label="Vista previa"
                >
                    <VistaPrevia
                        v-model:campana="campanaPrevia"
                        v-model:visitas="visitasPrevia"
                        v-model:estado="estadoPrevia"
                        :documento="documentoPrevia"
                        :general="general"
                        :negocio="negocio"
                        :campanas="campanas"
                        :medios="medios"
                        :seleccionada="zonaSeleccionada"
                        :viendo-version="
                            versionPrevia
                                ? `Versión del ${fechaHora(versionPrevia.en)}`
                                : null
                        "
                        @salir-version="versionPrevia = null"
                        @seleccionar="seleccionarZona"
                    />
                </section>
            </div>
        </div>
    </div>

    <DialogoConfirmacion
        v-model:open="dialogoPublicar"
        titulo="¿Publicar el diseño?"
        :descripcion="`${destino} Sus visitas, metas, recompensas y canjes no cambian.`"
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
            clientes lean bien su página.
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
        v-model:open="dialogoRestablecer"
        titulo="¿Restablecer los colores originales?"
        descripcion="Recupera los colores y estilos originales de DASTI (se conserva tu logotipo). Los cambios sin guardar se perderán, aunque puedes deshacerlo con «Deshacer»."
        confirmar="Restablecer"
        destructivo
        @confirmar="restablecer"
    />

    <DialogoConfirmacion
        v-model:open="dialogoDescartar"
        titulo="¿Descartar los cambios sin guardar?"
        descripcion="Volverás a lo último que guardaste. Puedes recuperarlos con «Deshacer»."
        confirmar="Descartar"
        destructivo
        @confirmar="descartar"
    />

    <DialogoConfirmacion
        v-model:open="dialogoQuitarVariante"
        titulo="¿Quitar el diseño de esta campaña?"
        :descripcion="`«${variante?.nombre}» volverá a usar el diseño general. Se borrará el historial de este diseño.`"
        confirmar="Quitar diseño"
        destructivo
        :procesando="ocupado === 'variante'"
        @confirmar="quitarVariante"
    />
</template>
