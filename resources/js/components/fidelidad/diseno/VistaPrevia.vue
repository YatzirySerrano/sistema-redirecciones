<script setup lang="ts">
import {
    Eye,
    Maximize2,
    Monitor,
    MousePointerClick,
    Smartphone,
    Tablet,
    X,
} from '@lucide/vue';
import { computed, provide, ref, toRef, watch } from 'vue';
import { CLAVE_EDICION } from '@/components/fidelidad-diseno/edicion';
import type {
    ClaveZona,
    ContextoEdicion,
} from '@/components/fidelidad-diseno/edicion';
import PaginaConsumidor from '@/components/fidelidad-diseno/PaginaConsumidor.vue';
import type {
    DocumentoDiseno,
    EstadoPrograma,
} from '@/components/fidelidad-diseno/tipos';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { datosDeMuestra, programaDeMuestra } from './muestras';
import { ZONAS } from './pasos';
import type { CampanaDiseno } from './tipos';

/**
 * Vista previa de la página COMPLETA del consumidor (encabezado, fondo,
 * tarjeta con su avance, información y condiciones), con datos de EJEMPLO
 * y la meta real de la campaña elegida. Nunca usa datos personales.
 *
 * Cada parte se puede tocar (o enfocar y pulsar Intro) para abrir sus
 * herramientas. Esa interacción solo existe aquí: el contexto de edición
 * se provee únicamente en el editor.
 */
const props = defineProps<{
    documento: DocumentoDiseno;
    general: DocumentoDiseno | null;
    negocio: string;
    campanas: CampanaDiseno[];
    medios: Record<string, string>;
    /** Texto del aviso cuando se ve una versión del historial. */
    viendoVersion: string | null;
    seleccionada: ClaveZona | null;
}>();

const emit = defineEmits<{ salirVersion: []; seleccionar: [ClaveZona] }>();

const campanaId = defineModel<number | null>('campana', { required: true });
const visitas = defineModel<number>('visitas', { required: true });
const estado = defineModel<EstadoPrograma>('estado', { required: true });

const campana = computed(
    () => props.campanas.find((c) => c.id === campanaId.value) ?? null,
);
const meta = computed(() => campana.value?.meta ?? 10);

watch(meta, (m) => (visitas.value = Math.round(m * 0.6)));
watch(estado, (e) => {
    if (e === 'canjeada' && visitas.value >= meta.value) {
        visitas.value = 0;
    }
});

const esVariante = computed(() => props.general !== null);
const programa = computed(() =>
    programaDeMuestra(campana.value, visitas.value, estado.value),
);
const datos = computed(() =>
    datosDeMuestra(
        props.negocio,
        props.general ?? props.documento,
        props.documento,
        programa.value,
        props.medios,
        esVariante.value,
    ),
);

// ---- Selección de partes de la página (solo en el editor) ----
const ampliada = ref(false);
const contexto: ContextoEdicion = {
    seleccionada: toRef(props, 'seleccionada'),
    seleccionar: (zona) => {
        ampliada.value = false;
        emit('seleccionar', zona);
    },
    nombre: (zona) => ZONAS[zona].nombre,
    // Una versión del historial solo se mira; en una variante, la página
    // (fondo, imagen principal, información) viene del diseño general.
    disponible: (zona) =>
        props.viendoVersion === null &&
        !(esVariante.value && ZONAS[zona].soloGeneral),
};

provide(CLAVE_EDICION, contexto);

// ---- Vista ampliada por dispositivo ----
const anchos = {
    telefono: { nombre: 'Teléfono', icono: Smartphone, ancho: '390px' },
    tableta: { nombre: 'Tableta', icono: Tablet, ancho: '768px' },
    computadora: { nombre: 'Computadora', icono: Monitor, ancho: '100%' },
} as const;
const dispositivo = ref<keyof typeof anchos>('computadora');
</script>

<template>
    <div class="vista-previa-editor flex flex-col gap-3">
        <!-- Datos de ejemplo -->
        <div
            class="flex flex-wrap items-center gap-2 text-xs"
            role="group"
            aria-label="Datos de ejemplo de la vista previa"
        >
            <select
                v-model="campanaId"
                class="h-10 min-w-0 flex-[1_1_12rem] rounded-lg border border-input bg-background px-2 text-xs"
                aria-label="Campaña para la vista previa"
            >
                <option :value="null">Campaña de ejemplo (10 visitas)</option>
                <option v-for="c in campanas" :key="c.id" :value="c.id">
                    {{ c.nombre }} · {{ c.meta }} visitas
                </option>
            </select>
            <select
                v-model="estado"
                class="h-10 flex-[1_1_8rem] rounded-lg border border-input bg-background px-2"
                aria-label="Momento del cliente de ejemplo"
            >
                <option value="en_progreso">Acumulando visitas</option>
                <option value="disponible">Recompensa lista</option>
                <option value="canjeada">Recién canjeada</option>
            </select>
            <label class="flex h-10 flex-[1_1_12rem] items-center gap-2">
                <span class="shrink-0 text-muted-foreground">Visitas</span>
                <input
                    v-model.number="visitas"
                    type="range"
                    min="0"
                    :max="meta"
                    class="h-2 min-w-0 flex-1 accent-primary"
                    aria-label="Visitas del cliente de ejemplo"
                />
                <span class="w-10 text-right tabular-nums"
                    >{{ Math.min(visitas, meta) }}/{{ meta }}</span
                >
            </label>
        </div>

        <p
            v-if="viendoVersion"
            class="flex items-center justify-between gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary"
            role="status"
        >
            <span class="inline-flex items-center gap-1.5"
                ><Eye class="size-3.5" aria-hidden="true" />
                {{ viendoVersion }}</span
            >
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-md px-2 font-medium hover:bg-primary/10"
                @click="emit('salirVersion')"
            >
                <X class="size-3.5" aria-hidden="true" /> Volver a mi diseño
            </button>
        </p>
        <p
            v-else
            class="flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <MousePointerClick class="size-3.5 shrink-0" aria-hidden="true" />
            Toca una parte de la página para editarla.
        </p>

        <!-- Marco: teléfono en pantallas amplias; directo en teléfonos -->
        <div class="marco-telefono relative mx-auto w-full">
            <span
                class="pointer-events-none absolute top-3 left-1/2 z-30 -translate-x-1/2 rounded-full bg-black/70 px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide text-white uppercase"
                >Vista de ejemplo</span
            >
            <div
                class="pantalla overflow-hidden rounded-2xl border bg-white"
                aria-label="Vista previa de la página de tu cliente (datos de ejemplo)"
                role="region"
            >
                <PaginaConsumidor :datos="datos" vista-previa />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                Cliente y visitas de ejemplo. Los sellos usan la meta real de la
                campaña elegida.
            </p>
            <button
                type="button"
                class="inline-flex h-10 items-center gap-1.5 rounded-lg border bg-background px-3 text-xs font-medium hover:bg-muted"
                @click="ampliada = true"
            >
                <Maximize2 class="size-3.5" aria-hidden="true" />
                Ver en otros dispositivos
            </button>
        </div>

        <Dialog v-model:open="ampliada">
            <DialogContent
                class="flex h-[calc(100dvh-2rem)] max-w-[calc(100vw-2rem)] flex-col gap-3 p-4 sm:max-w-[min(1100px,calc(100vw-2rem))]"
            >
                <div class="pr-8">
                    <DialogTitle>Así verán tus clientes su página</DialogTitle>
                    <DialogDescription class="text-xs">
                        Datos de ejemplo. Toca una parte para editarla.
                    </DialogDescription>
                </div>
                <div
                    class="inline-flex self-start rounded-lg border bg-background p-0.5"
                    role="radiogroup"
                    aria-label="Dispositivo"
                >
                    <button
                        v-for="(d, clave) in anchos"
                        :key="clave"
                        type="button"
                        role="radio"
                        :aria-checked="dispositivo === clave"
                        class="inline-flex h-10 items-center gap-1.5 rounded-md px-3 text-xs font-medium aria-checked:bg-primary aria-checked:text-primary-foreground"
                        @click="dispositivo = clave"
                    >
                        <component
                            :is="d.icono"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <span class="max-sm:sr-only">{{ d.nombre }}</span>
                    </button>
                </div>
                <div
                    class="vista-previa-editor min-h-0 flex-1 overflow-auto rounded-xl border bg-muted/40 p-2"
                >
                    <div
                        class="mx-auto overflow-hidden rounded-lg bg-white shadow-sm"
                        :style="{
                            width: anchos[dispositivo].ancho,
                            maxWidth: '100%',
                        }"
                    >
                        <PaginaConsumidor :datos="datos" vista-previa />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* Resaltado de zonas editables (solo existe dentro del editor). */
.vista-previa-editor .zona-editable {
    position: relative;
    cursor: pointer;
    outline: 2px solid transparent;
    outline-offset: -2px;
    border-radius: 6px;
    transition: outline-color 0.12s;
}

.vista-previa-editor .zona-editable:hover:not(:has(.zona-editable:hover)),
.vista-previa-editor .zona-editable:focus-visible {
    outline: 2px dashed rgb(14 165 233 / 0.9);
}

.vista-previa-editor .zona-editable[data-seleccionada] {
    outline: 2px solid rgb(2 132 199);
}

.vista-previa-editor .zona-etiqueta {
    position: absolute;
    top: 0;
    left: 8px;
    transform: translateY(-55%);
    z-index: 20;
    display: none;
    border-radius: 9999px;
    background: rgb(2 132 199);
    padding: 2px 8px;
    font:
        600 11px/1.4 ui-sans-serif,
        system-ui,
        sans-serif;
    color: #fff;
    pointer-events: none;
    letter-spacing: 0;
    text-transform: none;
}

.vista-previa-editor
    .zona-editable:is([data-zona='fondo'], [data-zona='portada'])
    > .zona-etiqueta {
    top: 36px;
    transform: none;
}

.vista-previa-editor .zona-editable[data-seleccionada] > .zona-etiqueta,
.vista-previa-editor
    .zona-editable:hover:not(:has(.zona-editable:hover))
    > .zona-etiqueta,
.vista-previa-editor .zona-editable:focus-visible > .zona-etiqueta {
    display: block;
}

.vista-previa-editor .zona-vacia {
    display: block;
    margin-top: 1.25rem;
    border: 1.5px dashed currentColor;
    border-radius: 1rem;
    padding: 0.85rem 1rem;
    text-align: center;
    font-size: 0.8rem;
    opacity: 0.55;
}

/* Marco de teléfono en columnas amplias del editor. */
@container editor (min-width: 42rem) {
    .marco-telefono {
        max-width: 390px;
        border-radius: 2.6rem;
        background: #171717;
        padding: 10px;
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.3);
    }

    .marco-telefono .pantalla {
        height: min(70vh, 700px);
        overflow-y: auto;
        overscroll-behavior: contain;
        border: 0;
        border-radius: 2.1rem;
        scrollbar-width: thin;
    }
}
</style>
