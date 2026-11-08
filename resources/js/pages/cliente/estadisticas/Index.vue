<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChartNoAxesCombined,
    FilterX,
    Info,
    MoonStar,
    Nfc,
    QrCode,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorPeriodo from '@/components/comun/SelectorPeriodo.vue';
import AnalisisAccesos from '@/components/estadisticas/AnalisisAccesos.vue';
import FiltroCanal from '@/components/estadisticas/FiltroCanal.vue';
import TarjetaGrafica from '@/components/graficas/TarjetaGrafica.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { fechaCorta, numero, relativo } from '@/lib/formato';
import { index } from '@/routes/cliente/estadisticas';
import { index as misProductos, show } from '@/routes/cliente/productos';
import type {
    ActividadProducto,
    AnalisisAccesos as Analisis,
    DiaSemana,
    ElementoDesglose,
    MapaHorario,
    Opcion,
    ProductosSinActividad,
    PuntoSerieCanales,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Estadísticas', href: index() }] },
});

// Las claves se declaran una por una: el compilador de Vue no resuelve
// intersecciones con tipos importados en defineProps.
const props = defineProps<{
    totales: TotalesEstadisticas;
    anterior: Analisis['anterior'];
    serie: PuntoSerieCanales[];
    serie_anterior: number[];
    canales: ElementoDesglose[];
    dias_semana: DiaSemana[];
    horario: MapaHorario;
    productos_top: ActividadProducto[];
    dispositivos: ElementoDesglose[];
    navegadores: ElementoDesglose[];
    sistemas: ElementoDesglose[];
    sin_redirigir: ElementoDesglose[];
    cobertura: Analisis['cobertura'];
    filtros: {
        desde: string;
        hasta: string;
        producto_id: string;
        canal: string;
    };
    sin_actividad: ProductosSinActividad | null;
    productos: Opcion[];
    canales_disponibles: string[];
    tiene_productos: boolean;
}>();

const { filtros } = useFiltros(index().url, { ...props.filtros });
const { puede } = usePermisos();

const analisis = computed<Analisis>(() => ({
    totales: props.totales,
    anterior: props.anterior,
    serie: props.serie,
    serie_anterior: props.serie_anterior,
    canales: props.canales,
    dias_semana: props.dias_semana,
    horario: props.horario,
    productos_top: props.productos_top,
    dispositivos: props.dispositivos,
    navegadores: props.navegadores,
    sistemas: props.sistemas,
    sin_redirigir: props.sin_redirigir,
    cobertura: props.cobertura,
}));

const conFiltros = computed(
    () => filtros.producto_id !== '' || filtros.canal !== '',
);
const limpiar = () => {
    filtros.producto_id = '';
    filtros.canal = '';
};
const sinActividadEnPeriodo = computed(() => props.totales.total === 0);
</script>

<template>
    <Head title="Estadísticas" />

    <EncabezadoPagina
        titulo="Estadísticas"
        :icono="ChartNoAxesCombined"
        :descripcion="`Del ${fechaCorta(filtros.desde)} al ${fechaCorta(filtros.hasta)}, comparado con el ${fechaCorta(anterior.desde)} – ${fechaCorta(anterior.hasta)}.`"
    />

    <EstadoVacio
        v-if="!tiene_productos"
        class="rounded-xl border bg-card"
        :icono="Nfc"
        titulo="Todavía no tienes productos"
        descripcion="Cuando DASTI registre tus tarjetas NFC o códigos QR, aquí verás cuántas veces se usan, cuándo y desde qué dispositivos."
    />

    <template v-else>
        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs 2xl:flex-row 2xl:items-center 2xl:justify-between"
        >
            <SelectorPeriodo
                v-model:desde="filtros.desde"
                v-model:hasta="filtros.hasta"
            />
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <FiltroCanal
                    v-model="filtros.canal"
                    :disponibles="canales_disponibles"
                />
                <SelectNativo
                    v-if="productos.length > 1"
                    v-model="filtros.producto_id"
                    :opciones="productos"
                    vacio="Todos mis productos"
                    etiqueta="Filtrar por producto"
                    class="sm:w-56"
                />
                <Button
                    v-if="conFiltros"
                    variant="ghost"
                    size="sm"
                    @click="limpiar"
                >
                    <FilterX /> Quitar filtros
                </Button>
            </div>
        </div>

        <EstadoVacio
            v-if="sinActividadEnPeriodo && anterior.total === 0 && !conFiltros"
            class="rounded-xl border bg-card"
            :icono="MoonStar"
            titulo="Aún no hay interacciones en este periodo"
            descripcion="Las estadísticas aparecen en cuanto alguien escanea uno de tus códigos QR o acerca su teléfono a una tarjeta NFC. Prueba un periodo más amplio o revisa que tus productos tengan destino."
        >
            <div class="flex flex-wrap justify-center gap-2">
                <Button
                    v-if="puede('productos.ver')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="misProductos()"
                        ><QrCode /> Revisar mis productos</Link
                    >
                </Button>
            </div>
        </EstadoVacio>

        <AnalisisAccesos
            v-else
            :analisis="analisis"
            :canal="filtros.canal"
            :producto-filtrado="filtros.producto_id !== ''"
            :enlace-producto="(id: number) => show.url(id)"
        />

        <TarjetaGrafica
            v-if="sin_actividad && sin_actividad.total > 0"
            titulo="Productos sin interacciones en el periodo"
            :descripcion="`${numero(sin_actividad.total)} ${sin_actividad.total === 1 ? 'producto activo no se usó' : 'productos activos no se usaron'}. Revisa dónde están colocados o si su destino es el correcto.`"
        >
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="p in sin_actividad.productos"
                    :key="p.id"
                    class="flex items-center gap-3 px-3 py-2.5 text-sm"
                >
                    <span class="min-w-0 flex-1">
                        <Link
                            :href="show(p.id)"
                            class="block truncate font-medium hover:text-primary hover:underline"
                            >{{ p.nombre }}</Link
                        >
                        <span class="text-xs text-muted-foreground"
                            >{{ p.tipo }} · {{ p.codigo }}</span
                        >
                    </span>
                    <span
                        class="shrink-0 text-right text-xs text-muted-foreground"
                    >
                        {{
                            p.ultimo_acceso_en
                                ? `Último uso ${relativo(p.ultimo_acceso_en)}`
                                : 'Nunca se ha usado'
                        }}
                    </span>
                </li>
            </ul>
            <p
                v-if="sin_actividad.total > sin_actividad.productos.length"
                class="mt-2 text-xs text-muted-foreground"
            >
                Y
                {{
                    numero(sin_actividad.total - sin_actividad.productos.length)
                }}
                más.
                <Link
                    :href="misProductos()"
                    class="font-medium text-enlace hover:underline"
                    >Ver todos mis productos</Link
                >
            </p>
        </TarjetaGrafica>

        <details
            class="group rounded-xl border bg-card px-4 py-3 text-sm shadow-xs"
        >
            <summary
                class="flex cursor-pointer list-none items-center gap-2 font-medium text-muted-foreground hover:text-foreground"
            >
                <Info class="size-4" aria-hidden="true" />
                Cómo leer estas cifras
            </summary>
            <ul
                class="mt-3 list-disc space-y-1.5 pl-5 text-xs text-pretty text-muted-foreground"
            >
                <li>
                    <strong class="text-foreground">Interacción</strong>: cada
                    vez que se abrió uno de tus enlaces y llegó a su destino. No
                    equivale a personas distintas, ventas ni clientes nuevos.
                </li>
                <li>
                    Se excluyen los accesos automatizados que reconocemos
                    (vistas previas de WhatsApp, buscadores, etc.). La detección
                    no es perfecta.
                </li>
                <li>
                    El
                    <strong class="text-foreground">periodo anterior</strong>
                    tiene la misma duración y termina justo antes del periodo
                    elegido.
                </li>
                <li>
                    Horarios, dispositivos y navegadores se conservan
                    {{ cobertura.retencion_dias }} días; los totales por día se
                    conservan siempre. Las horas son del centro de México.
                </li>
                <li>
                    Si un producto pasó a otro negocio, solo ves la actividad
                    del tiempo en que fue tuyo.
                </li>
                <li>
                    No registramos ubicación ni datos personales de quien usa
                    tus tarjetas.
                </li>
            </ul>
        </details>
    </template>
</template>
