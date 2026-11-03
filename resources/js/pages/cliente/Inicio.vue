<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    Gift,
    HeartHandshake,
    MousePointerClick,
    Nfc,
    Repeat,
    Stamp,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import ResumenServicios from '@/components/servicios/ResumenServicios.vue';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/composables/usePermisos';
import { numero, variacion, visitantes } from '@/lib/formato';
import { inicio } from '@/routes/cliente';
import { index as estadisticas } from '@/routes/cliente/estadisticas';
import {
    inicio as fidelidadInicio,
    mostrador,
} from '@/routes/cliente/fidelidad';
import {
    index as productos,
    show as producto,
} from '@/routes/cliente/productos';
import type {
    Barra,
    CambioDestino,
    Coleccion,
    ProductoTop,
    PuntoSerie,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inicio', href: inicio() }] },
});

const props = defineProps<{
    negocio: { nombre_comercial: string };
    indicadores: {
        productos: number;
        productos_activos: number;
        enlaces_sin_destino: number;
        accesos_30_dias: TotalesEstadisticas | null;
        accesos_previos: number | null;
    };
    serie: PuntoSerie[] | null;
    productos_top: ProductoTop[];
    alertas: {
        clave: string;
        titulo: string;
        descripcion: string;
        producto_id: number | null;
    }[];
    actividad: Coleccion<CambioDestino>;
    fidelidad: {
        consumidores: number;
        visitas_periodo: number;
        dias_periodo: number;
        recurrentes: number;
        recompensas_disponibles: number;
        recompensas_canjeadas_periodo: number;
        proximos_a_meta: {
            consumidor_id: number;
            nombre: string;
            campana: string;
            visitas: number;
            meta: number;
            faltan: number;
        }[];
    } | null;
}>();

const page = usePage();
const { puede } = usePermisos();
const nombre = computed(() => page.props.auth.user.name.split(' ')[0]);
const saludo = computed(() => {
    const h = new Date().getHours();

    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const accesos = computed(() => props.indicadores.accesos_30_dias);

const barras = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: p.tipo,
        total: p.total,
        href: producto.url(p.id),
    })),
);
</script>

<template>
    <Head title="Inicio" />

    <EncabezadoPagina
        :titulo="`${saludo}, ${nombre}`"
        :descripcion="`Así van los productos de ${negocio.nombre_comercial}.`"
    >
        <template v-if="puede('productos.ver')" #acciones>
            <Button as-child>
                <Link :href="productos()"><Nfc /> Mis productos</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <!-- Alertas importantes -->
    <div v-if="alertas.length > 0" class="space-y-2">
        <component
            :is="a.producto_id ? Link : 'div'"
            v-for="a in alertas"
            :key="a.clave"
            :href="a.producto_id ? producto(a.producto_id) : undefined"
            class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-warning"
            role="status"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold">{{ a.titulo }}</span>
                <span class="block text-sm opacity-90">{{
                    a.descripcion
                }}</span>
            </span>
            <ArrowRight
                v-if="a.producto_id"
                class="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
        </component>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Productos activos"
            :valor="numero(indicadores.productos_activos)"
            :detalle="`de ${numero(indicadores.productos)} productos`"
            :icono="Nfc"
            :href="puede('productos.ver') ? productos() : undefined"
        />
        <template v-if="accesos">
            <TarjetaIndicador
                etiqueta="Visitas · 30 días"
                :valor="numero(accesos.total)"
                :detalle="
                    variacion(accesos.total, indicadores.accesos_previos ?? 0)
                "
                :icono="MousePointerClick"
                ayuda="Veces que alguien escaneó tus QR o acercó tus tarjetas NFC, sin contar bots identificados."
            />
            <TarjetaIndicador
                etiqueta="Visitantes estimados"
                :valor="visitantes(accesos)"
                detalle="Personas distintas por día (aproximado)"
                :icono="UsersRound"
                ayuda="Es una estimación anónima: no identifica personas y puede variar si alguien usa varios dispositivos."
            />
            <TarjetaIndicador
                etiqueta="Promedio diario"
                :valor="numero(accesos.promedio_diario)"
                detalle="Visitas por día en 30 días"
                :href="estadisticas()"
            />
        </template>
    </div>

    <div
        v-if="serie"
        class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
    >
        <Panel
            titulo="Evolución de visitas"
            descripcion="Visitas por día en los últimos 30 días."
        >
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="estadisticas()">Ver más <ArrowRight /></Link>
                </Button>
            </template>
            <GraficaArea :serie="serie" etiqueta="Visitas" />
        </Panel>
        <Panel titulo="Productos más usados" descripcion="Visitas en 30 días.">
            <GraficaBarras
                :barras="barras"
                unidad="visitas"
                vacio="Aún no hay visitas en este periodo."
            />
        </Panel>
    </div>

    <ResumenServicios />

    <!-- Fidelidad (si el negocio la contrató) -->
    <Panel
        v-if="fidelidad"
        titulo="Programa de fidelidad"
        :descripcion="`Últimos ${fidelidad.dias_periodo} días. Las visitas solo cuentan cuando tu personal las confirma.`"
    >
        <template #acciones>
            <Button v-if="puede('fidelidad.visitas')" size="sm" as-child>
                <Link :href="mostrador()"><Stamp /> Registrar visita</Link>
            </Button>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="fidelidadInicio()"
                    >Ver programa <ArrowRight
                /></Link>
            </Button>
        </template>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div
                v-for="d in [
                    {
                        e: 'Consumidores inscritos',
                        v: fidelidad.consumidores,
                        i: UsersRound,
                    },
                    {
                        e: 'Visitas válidas',
                        v: fidelidad.visitas_periodo,
                        i: Stamp,
                    },
                    {
                        e: 'Clientes recurrentes',
                        v: fidelidad.recurrentes,
                        i: Repeat,
                    },
                    {
                        e: 'Recompensas disponibles',
                        v: fidelidad.recompensas_disponibles,
                        i: Gift,
                    },
                    {
                        e: 'Recompensas canjeadas',
                        v: fidelidad.recompensas_canjeadas_periodo,
                        i: HeartHandshake,
                    },
                ]"
                :key="d.e"
                class="rounded-lg bg-muted/40 p-3"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <component :is="d.i" class="size-3.5" aria-hidden="true" />
                    {{ d.e }}
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(d.v) }}
                </p>
            </div>
        </div>
        <div v-if="fidelidad.proximos_a_meta.length > 0" class="mt-5">
            <p class="mb-2 text-xs font-medium text-muted-foreground">
                Próximos a completar su meta
            </p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="c in fidelidad.proximos_a_meta"
                    :key="`${c.consumidor_id}-${c.campana}`"
                    class="flex items-center gap-3 px-3 py-2 text-sm"
                >
                    <span class="min-w-0 flex-1 truncate font-medium">{{
                        c.nombre
                    }}</span>
                    <span
                        class="hidden truncate text-xs text-muted-foreground sm:inline"
                        >{{ c.campana }}</span
                    >
                    <span class="w-28 shrink-0">
                        <span
                            class="block h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="block h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, (c.visitas / c.meta) * 100)}%`,
                                }"
                            />
                        </span>
                    </span>
                    <span
                        class="w-20 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                        >faltan {{ c.faltan }}</span
                    >
                </li>
            </ul>
        </div>
    </Panel>

    <Panel
        titulo="Cambios de destino recientes"
        descripcion="Cada cambio queda registrado con quién lo hizo."
    >
        <HistorialDestinos :cambios="actividad.data" mostrar-producto />
    </Panel>
</template>
