<script setup lang="ts">
import { CreditCard, Eye, PanelTop, X } from '@lucide/vue';
import { computed, watch } from 'vue';
import { fondoCss } from '@/components/fidelidad-diseno/catalogo';
import PaginaConsumidor from '@/components/fidelidad-diseno/PaginaConsumidor.vue';
import TarjetaPrograma from '@/components/fidelidad-diseno/TarjetaPrograma.vue';
import type {
    DocumentoDiseno,
    EstadoPrograma,
} from '@/components/fidelidad-diseno/tipos';
import { datosDeMuestra, programaDeMuestra } from './muestras';
import type { CampanaDiseno } from './tipos';

/**
 * Vista previa en un marco de teléfono, con datos de EJEMPLO (cliente
 * «Ana») y la meta real de la campaña elegida. Nunca usa datos personales.
 */
const props = defineProps<{
    documento: DocumentoDiseno;
    general: DocumentoDiseno | null;
    negocio: string;
    campanas: CampanaDiseno[];
    medios: Record<string, string>;
    /** Texto del aviso cuando se ve una versión del historial. */
    viendoVersion: string | null;
}>();

const emit = defineEmits<{ salirVersion: [] }>();

const campanaId = defineModel<number | null>('campana', { required: true });
const modo = defineModel<'tarjeta' | 'pagina'>('modo', { required: true });
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
const paginaBase = computed(() => props.general ?? props.documento);
const fondoPagina = computed(() =>
    fondoCss(
        paginaBase.value.pagina.fondo_tipo,
        paginaBase.value.pagina.color1,
        paginaBase.value.pagina.color2,
        paginaBase.value.pagina.angulo,
        paginaBase.value.pagina.imagen,
        props.medios,
        20,
    ),
);
</script>

<template>
    <div class="flex flex-col items-center gap-4">
        <div class="flex w-full max-w-[380px] flex-wrap items-center gap-2">
            <div
                class="inline-flex rounded-lg border bg-background p-0.5"
                role="group"
                aria-label="Qué previsualizar"
            >
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                    :aria-pressed="modo === 'tarjeta'"
                    @click="modo = 'tarjeta'"
                >
                    <CreditCard class="size-3.5" aria-hidden="true" /> Tarjeta
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                    :aria-pressed="modo === 'pagina'"
                    @click="modo = 'pagina'"
                >
                    <PanelTop class="size-3.5" aria-hidden="true" /> Página del
                    cliente
                </button>
            </div>
            <select
                v-model="campanaId"
                class="h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-xs"
                aria-label="Campaña para la vista previa"
            >
                <option :value="null">Ejemplo (10 visitas)</option>
                <option v-for="c in campanas" :key="c.id" :value="c.id">
                    {{ c.nombre }} · {{ c.meta }} visitas ·
                    {{ c.participantes }}
                    {{ c.participantes === 1 ? 'cliente' : 'clientes' }}
                </option>
            </select>
        </div>

        <div
            class="flex w-full max-w-[380px] flex-wrap items-center gap-3 text-xs"
        >
            <label class="flex flex-1 items-center gap-2">
                <span class="shrink-0 text-muted-foreground">Visitas</span>
                <input
                    v-model.number="visitas"
                    type="range"
                    min="0"
                    :max="meta"
                    class="h-2 min-w-0 flex-1 accent-primary"
                    aria-label="Visitas de ejemplo"
                />
                <span class="w-10 text-right tabular-nums"
                    >{{ Math.min(visitas, meta) }}/{{ meta }}</span
                >
            </label>
            <select
                v-model="estado"
                class="h-8 rounded-lg border border-input bg-background px-2"
                aria-label="Estado de ejemplo"
            >
                <option value="en_progreso">Acumulando</option>
                <option value="disponible">Recompensa lista</option>
                <option value="canjeada">Recién canjeada</option>
            </select>
        </div>

        <p
            v-if="viendoVersion"
            class="flex w-full max-w-[380px] items-center justify-between gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary"
            role="status"
        >
            <span class="inline-flex items-center gap-1.5"
                ><Eye class="size-3.5" aria-hidden="true" />
                {{ viendoVersion }}</span
            >
            <button
                type="button"
                class="inline-flex items-center gap-1 font-medium underline-offset-2 hover:underline"
                @click="emit('salirVersion')"
            >
                <X class="size-3.5" aria-hidden="true" /> Volver a mi diseño
            </button>
        </p>

        <!-- Marco de teléfono -->
        <div
            class="relative w-full max-w-[380px] rounded-[2.6rem] bg-neutral-900 p-2.5 shadow-2xl ring-1 shadow-black/30 ring-black/10"
        >
            <div
                class="absolute top-4 left-1/2 z-10 h-5 w-24 -translate-x-1/2 rounded-full bg-neutral-900"
                aria-hidden="true"
            />
            <div
                class="h-[min(72vh,700px)] [scrollbar-width:thin] overflow-y-auto overscroll-contain rounded-[2.1rem] bg-white"
                aria-label="Vista previa (datos de ejemplo)"
                role="region"
                tabindex="0"
            >
                <PaginaConsumidor
                    v-if="modo === 'pagina'"
                    :datos="datos"
                    vista-previa
                />
                <div
                    v-else
                    class="flex min-h-full items-center px-4 py-12"
                    :style="{ background: fondoPagina }"
                >
                    <TarjetaPrograma
                        class="w-full"
                        :diseno="documento"
                        :programa="programa"
                        :negocio="negocio"
                        :consumidor="
                            paginaBase.contenido.saludo === 'alias'
                                ? 'Ana'
                                : null
                        "
                        :medios="medios"
                    />
                </div>
            </div>
        </div>
        <p class="max-w-[380px] text-center text-xs text-muted-foreground">
            Vista previa con datos de ejemplo. El número de sellos es la meta
            real de la campaña elegida.
        </p>
    </div>
</template>
