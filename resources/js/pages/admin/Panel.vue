<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Building2,
    CircleAlert,
    CircleCheck,
    CreditCard,
    HeartHandshake,
    LayoutDashboard,
    Link2,
    MousePointerClick,
    Nfc,
    Plus,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/composables/usePermisos';
import { numero, pesos, variacion, visitantes } from '@/lib/formato';
import { panel } from '@/routes/admin';
import { index as auditoriaIndex } from '@/routes/admin/auditoria';
import { index as enlacesIndex } from '@/routes/admin/enlaces';
import { index as estadisticasIndex } from '@/routes/admin/estadisticas';
import {
    create as negocioCreate,
    index as negociosIndex,
    show as negocioShow,
} from '@/routes/admin/negocios';
import { index as pagosIndex } from '@/routes/admin/pagos';
import {
    create as productoCreate,
    index as productosIndex,
    show as productoShow,
} from '@/routes/admin/productos';
import { index as serviciosIndex } from '@/routes/admin/servicios';
import { index as usuariosIndex } from '@/routes/admin/usuarios';
import type {
    Barra,
    CambioDestino,
    Coleccion,
    NegocioTop,
    ProductoTop,
    PuntoSerie,
    RegistroAuditoria,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: panel() }],
    },
});

type Incidencia = {
    clave: string;
    titulo: string;
    descripcion: string;
    cantidad: number;
    ruta: string;
    parametros: Record<string, string>;
    permiso: string;
};

const props = defineProps<{
    indicadores: {
        negocios: number;
        negocios_activos: number;
        productos: number;
        productos_activos: number;
        productos_suspendidos: number;
        enlaces_redirigiendo: number;
        enlaces_sin_destino: number;
        accesos_periodo: TotalesEstadisticas;
        accesos_periodo_anterior: number;
        accesos_totales: number;
    };
    incidencias: Incidencia[];
    fidelidad: {
        negocios_con_fidelidad: number;
        contrataciones_pendientes: number;
        pagos_confirmados: number;
        pagos_confirmados_centavos: number;
        pagos_pendientes: number;
        incidencias: number;
    } | null;
    serie: PuntoSerie[];
    productos_top: ProductoTop[];
    negocios_top: NegocioTop[];
    ultimos_cambios: Coleccion<CambioDestino>;
    actividad: Coleccion<RegistroAuditoria> | null;
}>();

const { puede } = usePermisos();
const i = computed(() => props.indicadores);

// Rutas de las incidencias (el servidor indica cuál y con qué filtro).
const RUTAS: Record<string, (q: Record<string, string>) => string> = {
    'admin.enlaces.index': (q) => enlacesIndex.url({ query: q }),
    'admin.productos.index': (q) => productosIndex.url({ query: q }),
    'admin.negocios.index': (q) => negociosIndex.url({ query: q }),
    'admin.usuarios.index': (q) => usuariosIndex.url({ query: q }),
};
const incidencias = computed(() =>
    props.incidencias.filter((x) => puede(x.permiso)),
);

const barrasProductos = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: `${p.negocio} · ${p.tipo}`,
        total: p.total,
        href: puede('productos.ver') ? productoShow.url(p.id) : undefined,
    })),
);
const barrasNegocios = computed<Barra[]>(() =>
    props.negocios_top.map((n) => ({
        clave: n.id,
        etiqueta: n.nombre,
        total: n.total,
        href: puede('negocios.ver') ? negocioShow.url(n.id) : undefined,
    })),
);
</script>

<template>
    <Head title="Panel" />

    <EncabezadoPagina
        titulo="Panel general"
        descripcion="Estado de los negocios, sus productos y el uso de los enlaces en los últimos 30 días."
        :icono="LayoutDashboard"
    >
        <template #acciones>
            <Button v-if="puede('negocios.crear')" variant="outline" as-child>
                <Link :href="negocioCreate()"><Building2 /> Nuevo negocio</Link>
            </Button>
            <Button v-if="puede('productos.crear')" as-child>
                <Link :href="productoCreate()"
                    ><Plus /> Registrar producto</Link
                >
            </Button>
        </template>
    </EncabezadoPagina>

    <!-- ¿Cómo está la plataforma? -->
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Negocios activos"
            :valor="numero(i.negocios_activos)"
            :detalle="`de ${numero(i.negocios)} registrados`"
            :icono="Building2"
            :href="puede('negocios.ver') ? negociosIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Productos activos"
            :valor="numero(i.productos_activos)"
            :detalle="`de ${numero(i.productos)} · ${numero(i.productos_suspendidos)} suspendidos`"
            :icono="Nfc"
            ayuda="Productos físicos vendidos. Una tarjeta QR + NFC con dos destinos cuenta como un solo producto."
            :href="puede('productos.ver') ? productosIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Enlaces que redirigen"
            :valor="numero(i.enlaces_redirigiendo)"
            :detalle="
                i.enlaces_sin_destino === 1
                    ? '1 activo sin destino'
                    : i.enlaces_sin_destino > 1
                      ? `${numero(i.enlaces_sin_destino)} activos sin destino`
                      : 'Todos los enlaces activos tienen destino'
            "
            :icono="Link2"
            :tono="i.enlaces_sin_destino > 0 ? 'alerta' : 'normal'"
            ayuda="Enlaces permanentes de productos y negocios activos que llevan a un destino configurado."
            :href="puede('redirecciones.ver') ? enlacesIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Accesos · 30 días"
            :valor="numero(i.accesos_periodo.total)"
            :detalle="
                variacion(i.accesos_periodo.total, i.accesos_periodo_anterior)
            "
            :icono="MousePointerClick"
            :ayuda="`Escaneos y lecturas NFC sin bots identificados. Visitantes estimados: ${visitantes(i.accesos_periodo)}. Histórico: ${numero(i.accesos_totales)}.`"
            :href="puede('estadisticas.ver') ? estadisticasIndex() : undefined"
        />
    </div>

    <div
        class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
    >
        <Panel
            titulo="Accesos por día"
            descripcion="Escaneos y lecturas NFC de todos los enlaces, sin bots identificados."
        >
            <template v-if="puede('estadisticas.ver')" #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="estadisticasIndex()"
                        >Estadísticas <ArrowRight
                    /></Link>
                </Button>
            </template>
            <GraficaArea :serie="serie" etiqueta="Accesos" />
        </Panel>

        <!-- ¿Qué requiere atención? -->
        <Panel
            titulo="Requiere atención"
            :descripcion="
                incidencias.length === 0
                    ? 'No hay incidencias pendientes.'
                    : 'Situaciones que afectan a clientes o a la seguridad.'
            "
            sin-relleno
        >
            <ul v-if="incidencias.length > 0" class="divide-y">
                <li v-for="x in incidencias" :key="x.clave">
                    <Link
                        :href="RUTAS[x.ruta]?.(x.parametros) ?? '#'"
                        class="flex items-start gap-3 px-5 py-3 transition hover:bg-muted/40"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning"
                            aria-hidden="true"
                        >
                            <CircleAlert class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{
                                x.titulo
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                x.descripcion
                            }}</span>
                        </span>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums"
                            >{{ numero(x.cantidad) }}</span
                        >
                    </Link>
                </li>
            </ul>
            <div
                v-else
                class="flex items-center gap-3 px-5 py-6 text-sm text-muted-foreground"
            >
                <CircleCheck class="size-5 text-success" aria-hidden="true" />
                Todo en orden.
            </div>
        </Panel>
    </div>

    <!-- Servicios adicionales (Fidelidad) -->
    <Panel
        v-if="fidelidad"
        titulo="Fidelidad DASTI"
        descripcion="Servicio adicional contratado por los negocios. Los importes son pagos confirmados, no proyecciones."
    >
        <template #acciones>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="serviciosIndex()"
                    >Contrataciones <ArrowRight
                /></Link>
            </Button>
        </template>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-muted/40 p-3">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <HeartHandshake class="size-3.5" aria-hidden="true" />
                    Negocios con Fidelidad
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.negocios_con_fidelidad) }}
                </p>
            </div>
            <div class="rounded-lg bg-muted/40 p-3">
                <p class="text-xs text-muted-foreground">
                    Contrataciones pendientes
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.contrataciones_pendientes) }}
                </p>
            </div>
            <Link
                :href="pagosIndex()"
                class="rounded-lg bg-muted/40 p-3 transition hover:bg-muted"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <CreditCard class="size-3.5" aria-hidden="true" />
                    Pagos confirmados
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.pagos_confirmados) }}
                </p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ pesos(fidelidad.pagos_confirmados_centavos) }} ·
                    {{ numero(fidelidad.pagos_pendientes) }} pendientes
                </p>
            </Link>
            <div
                class="rounded-lg p-3"
                :class="
                    fidelidad.incidencias > 0
                        ? 'bg-warning-soft text-warning'
                        : 'bg-muted/40'
                "
            >
                <p class="text-xs">Por revisar</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.incidencias) }}
                </p>
                <p class="text-xs opacity-80">
                    Reembolsos parciales y disputas
                </p>
            </div>
        </div>
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Productos más usados"
            descripcion="Por accesos en los últimos 30 días."
        >
            <GraficaBarras
                :barras="barrasProductos"
                vacio="Ningún producto ha recibido accesos en 30 días."
            />
        </Panel>
        <Panel
            titulo="Negocios con más uso"
            descripcion="Suma de accesos de sus productos en 30 días."
        >
            <GraficaBarras
                :barras="barrasNegocios"
                vacio="Ningún negocio ha recibido accesos en 30 días."
            />
        </Panel>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Cambios de destino recientes"
            descripcion="Quién cambió a dónde lleva cada enlace."
        >
            <HistorialDestinos
                :cambios="ultimos_cambios.data"
                mostrar-producto
            />
        </Panel>
        <Panel v-if="actividad" titulo="Actividad reciente">
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="auditoriaIndex()"><Activity /> Auditoría</Link>
                </Button>
            </template>
            <ListaAuditoria :registros="actividad.data" mostrar-negocio />
        </Panel>
    </div>
</template>
