<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
    Activity,
    Building2,
    CalendarClock,
    ChartColumnBig,
    Eye,
    MousePointerClick,
    Pencil,
    TriangleAlert,
    UsersRound,
} from "@lucide/vue";
import { computed, ref, watch } from "vue";
import CambiarEstadoProducto from "@/components/admin-productos/CambiarEstadoProducto.vue";
import EliminarProducto from "@/components/admin-productos/EliminarProducto.vue";
import TransferirProducto from "@/components/admin-productos/TransferirProducto.vue";
import EncabezadoPagina from "@/components/comun/EncabezadoPagina.vue";
import Ayuda from "@/components/comun/Ayuda.vue";
import EnlacePermanente from "@/components/comun/EnlacePermanente.vue";
import InsigniaEstado from "@/components/comun/InsigniaEstado.vue";
import InsigniaTipo from "@/components/comun/InsigniaTipo.vue";
import ListaAuditoria from "@/components/comun/ListaAuditoria.vue";
import Panel from "@/components/comun/Panel.vue";
import TarjetaIndicador from "@/components/comun/TarjetaIndicador.vue";
import GraficaArea from "@/components/graficas/GraficaArea.vue";
import GraficaBarras from "@/components/graficas/GraficaBarras.vue";
import CambiarCantidadQr from "@/components/productos/CambiarCantidadQr.vue";
import DisenadorQr from "@/components/productos/DisenadorQr.vue";
import EnlacesPorMedio from "@/components/productos/EnlacesPorMedio.vue";
import FormularioDestino from "@/components/productos/FormularioDestino.vue";
import HistorialDestinos from "@/components/productos/HistorialDestinos.vue";
import InstruccionesNfc from "@/components/productos/InstruccionesNfc.vue";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    coberturaVisitantes,
    fechaCorta,
    fechaHora,
    numero,
    relativo,
    visitantes,
} from "@/lib/formato";
import { usePermisos } from "@/composables/usePermisos";
import { enlacesQr } from "@/lib/productos";
import { show as negocioShow } from "@/routes/admin/negocios";
import { cantidadQr, destino, edit, index } from "@/routes/admin/productos";
import type {
    CambioDestino,
    Coleccion,
    ElementoDesglose,
    EstadisticaMedio,
    OpcionesQr,
    Producto,
    PuntoSerie,
    Recurso,
    RegistroAuditoria,
    TotalesEstadisticas,
} from "@/types";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Productos", href: index() },
            { title: "Detalle del producto", href: "#" },
        ],
    },
});

const { puede } = usePermisos();

const props = defineProps<{
    producto: Recurso<Producto>;
    opciones_qr: OpcionesQr;
    /** Diseño guardado de cada QR, por id de enlace. */
    opciones_qr_enlaces: Record<number, OpcionesQr>;
    qr2_archivado: { codigo: string; archivado_en: string | null } | null;
    puede_eliminarse: boolean;
    estadisticas: {
        totales: TotalesEstadisticas;
        serie: PuntoSerie[];
        dispositivos: ElementoDesglose[];
        total_historico: number;
        varios_enlaces: boolean;
    };
    cambios: Coleccion<CambioDestino>;
    auditoria: Coleccion<RegistroAuditoria>;
    estadisticas_medios: EstadisticaMedio[];
}>();

const producto = computed(() => props.producto.data);
const independiente = computed(
    () => producto.value.modalidad_destino === "independiente",
);
/** Enlaces impresos como QR: el reverso en los combinados independientes; QR 1 y QR 2 si lleva dos. */
const qrs = computed(() => enlacesQr(producto.value));
const qrSeleccionadoId = ref<number>(qrs.value[0].id);
watch(qrs, (lista) => {
    if (!lista.some((e) => e.id === qrSeleccionadoId.value)) {
        qrSeleccionadoId.value = lista[0].id;
    }
});
const productoQr = computed(
    () =>
        qrs.value.find((e) => e.id === qrSeleccionadoId.value) ?? qrs.value[0],
);
const opcionesDe = (id: number) =>
    props.opciones_qr_enlaces?.[id] ?? props.opciones_qr;
const pestana = ref("enlace");
const abrirDiseno = (id: number) => {
    qrSeleccionadoId.value = id;
    pestana.value = "qr";
};
const negocioSuspendido = computed(
    () => producto.value.negocio?.estado === "suspendido",
);
const llevaNfc = computed(() => producto.value.tipo !== "qr");
const llevaQr = computed(() => producto.value.tipo !== "nfc");

const situacion = computed(() => {
    if (negocioSuspendido.value) {
        return {
            clave: "negocio_suspendido",
            texto: "El negocio está suspendido: el enlace muestra «no disponible».",
        };
    }

    if (producto.value.estado === "suspendido") {
        return {
            clave: "producto_suspendido",
            texto: "El producto está suspendido: el enlace muestra «no disponible».",
        };
    }

    if (!producto.value.url_destino) {
        return {
            clave: "sin_destino",
            texto: "Sin destino configurado: el enlace muestra «no disponible».",
        };
    }

    return {
        clave: "redirigiendo",
        texto: "El enlace redirige al destino configurado.",
    };
});

const barrasDispositivos = computed(() =>
    props.estadisticas.dispositivos.map((d) => ({
        clave: d.clave,
        etiqueta: d.etiqueta,
        total: d.total,
    })),
);
</script>

<template>
    <Head :title="producto.nombre" />

    <EncabezadoPagina :titulo="producto.nombre">
        <template #antes>
            <div class="flex flex-wrap items-center gap-2">
                <InsigniaTipo
                    :tipo="producto.tipo"
                    :etiqueta="producto.tipo_etiqueta"
                />
                <span
                    v-if="producto.cantidad_qr === 2"
                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >2 QR</span
                >
                <span
                    v-if="producto.tipo === 'combinado'"
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >{{
                        independiente
                            ? "Destinos independientes"
                            : "Destino compartido"
                    }}</span
                >
                <InsigniaEstado
                    :estado="producto.estado"
                    :etiqueta="producto.estado_etiqueta"
                />
                <span class="font-mono text-xs text-muted-foreground">{{
                    producto.codigo
                }}</span>
            </div>
        </template>
        <template #detalle>
            <p
                v-if="producto.negocio"
                class="flex items-center gap-1.5 text-sm text-muted-foreground"
            >
                <Building2 class="size-4" aria-hidden="true" />
                <Link
                    :href="negocioShow(producto.negocio.id)"
                    class="font-medium text-foreground hover:text-primary hover:underline"
                >
                    {{ producto.negocio.nombre_comercial }}
                </Link>
                <InsigniaEstado
                    v-if="negocioSuspendido"
                    estado="negocio_suspendido"
                />
            </p>
        </template>
        <template #acciones>
            <Button v-if="puede('productos.editar')" variant="outline" as-child>
                <Link :href="edit(producto.id)"><Pencil /> Editar</Link>
            </Button>
            <CambiarCantidadQr
                v-if="producto.permisos.cambiar_cantidad_qr"
                :producto="producto"
                :accion="cantidadQr.url(producto.id)"
                :qr2-archivado="qr2_archivado"
                con-destino
            />
            <TransferirProducto
                v-if="puede('productos.transferir')"
                :producto="producto"
            />
            <CambiarEstadoProducto
                v-if="
                    puede(
                        producto.estado === 'activo'
                            ? 'productos.suspender'
                            : 'productos.reactivar',
                    )
                "
                :producto="producto"
            />
            <EliminarProducto
                v-if="puede_eliminarse && puede('productos.eliminar')"
                :producto="producto"
            />
        </template>
    </EncabezadoPagina>

    <div
        v-if="situacion.clave !== 'redirigiendo'"
        class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning"
        role="status"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <p>{{ situacion.texto }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <Tabs v-model="pestana" class="min-w-0 gap-4">
            <TabsList
                class="w-full justify-start overflow-x-auto sm:w-auto [&>button]:flex-none [&>button]:px-3"
            >
                <TabsTrigger value="enlace">Enlace y destino</TabsTrigger>
                <TabsTrigger value="qr">Código QR</TabsTrigger>
                <TabsTrigger value="nfc">NFC</TabsTrigger>
                <TabsTrigger value="estadisticas">Estadísticas</TabsTrigger>
                <TabsTrigger value="historial">Historial</TabsTrigger>
            </TabsList>

            <TabsContent value="enlace" class="space-y-6">
                <EnlacesPorMedio
                    v-if="independiente"
                    :producto="producto"
                    :accion-destino="(id: number) => destino.url(id)"
                    :estadisticas="estadisticas_medios"
                    :opciones-qr="opciones_qr_enlaces"
                    @disenar="abrirDiseno"
                />
                <template v-else>
                    <Panel
                        titulo="Enlace permanente"
                        descripcion="Es la URL impresa en el QR o grabada en el NFC. Nunca cambia."
                    >
                        <template #acciones>
                            <Ayuda
                                texto="Es la dirección grabada en tu tarjeta NFC o código QR. Puedes modificar su destino sin cambiar esta dirección."
                            />
                        </template>
                        <EnlacePermanente :url="producto.url_permanente" />
                        <div
                            class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <InsigniaEstado :estado="situacion.clave" />
                            <span>{{ situacion.texto }}</span>
                        </div>
                    </Panel>
                    <Panel
                        titulo="Destino actual"
                        descripcion="A dónde lleva hoy el enlace permanente."
                    >
                        <p
                            class="mb-4 rounded-lg bg-muted/50 px-3 py-2 font-mono text-sm break-all"
                        >
                            <a
                                v-if="producto.url_destino"
                                :href="producto.url_destino"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="hover:text-primary hover:underline"
                                >{{ producto.url_destino }}</a
                            >
                            <span v-else class="text-muted-foreground italic"
                                >Sin destino configurado</span
                            >
                        </p>
                        <FormularioDestino
                            v-if="producto.permisos.cambiar_destino"
                            :key="producto.url_destino ?? ''"
                            :producto="producto"
                            :accion="destino.url(producto.id)"
                            :obligatorio="false"
                        />
                    </Panel>
                </template>
            </TabsContent>

            <TabsContent value="qr">
                <Panel
                    titulo="Código QR"
                    :descripcion="
                        llevaQr
                            ? 'Diseña y descarga el QR limpio, sin marca de agua.'
                            : 'Producto NFC: el QR es opcional (por ejemplo, para un respaldo impreso).'
                    "
                >
                    <div
                        v-if="qrs.length > 1"
                        class="mb-4 inline-flex rounded-lg border bg-muted/40 p-1"
                        role="tablist"
                        aria-label="QR a diseñar"
                    >
                        <button
                            v-for="qr in qrs"
                            :key="qr.id"
                            type="button"
                            role="tab"
                            :aria-selected="qr.id === productoQr.id"
                            class="rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition aria-selected:bg-background aria-selected:text-foreground aria-selected:shadow-xs"
                            @click="qrSeleccionadoId = qr.id"
                        >
                            {{ qr.medio_etiqueta }}
                        </button>
                    </div>
                    <p
                        v-if="independiente"
                        class="mb-4 rounded-lg bg-accent px-3 py-2 text-xs text-accent-foreground"
                    >
                        {{
                            productoQr.numero_qr
                                ? `Este es el ${productoQr.medio_etiqueta}: tiene su propio enlace, destino, diseño y estadísticas`
                                : "Este QR corresponde al reverso de la tarjeta y lleva a su propio destino"
                        }}
                        (/r/{{ productoQr.codigo }}).
                    </p>
                    <DisenadorQr
                        :key="productoQr.id"
                        :producto="productoQr"
                        :opciones="opcionesDe(productoQr.id)"
                    />
                </Panel>
            </TabsContent>

            <TabsContent value="nfc">
                <Panel
                    titulo="Programación NFC"
                    :descripcion="
                        llevaNfc
                            ? 'Graba esta URL en la tarjeta con NFC Tools.'
                            : 'Producto QR: no requiere tarjeta, pero puedes grabar la misma URL si se necesita.'
                    "
                >
                    <InstruccionesNfc :url="producto.url_permanente" />
                    <dl
                        class="mt-5 grid gap-3 border-t pt-4 text-sm sm:grid-cols-2"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Estado de preparación
                            </dt>
                            <dd class="mt-1">
                                <InsigniaEstado
                                    v-if="producto.estado_preparacion"
                                    :estado="producto.estado_preparacion"
                                    :etiqueta="
                                        producto.estado_preparacion_etiqueta
                                    "
                                />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Fecha de entrega
                            </dt>
                            <dd class="mt-1">
                                {{ fechaCorta(producto.fecha_entrega) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Actualiza la preparación desde
                        <Link
                            :href="edit(producto.id)"
                            class="font-medium text-primary hover:underline"
                            >Editar</Link
                        >.
                    </p>
                </Panel>
            </TabsContent>

            <TabsContent value="estadisticas" class="space-y-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <TarjetaIndicador
                        etiqueta="Accesos (30 días)"
                        :valor="numero(estadisticas.totales.total)"
                        :icono="MousePointerClick"
                        :detalle="
                            estadisticas.varios_enlaces
                                ? 'Veces que se abrieron todos los enlaces del producto, sin accesos automatizados.'
                                : 'Veces que se abrió el enlace, sin accesos automatizados.'
                        "
                    />
                    <TarjetaIndicador
                        etiqueta="Visitantes estimados"
                        :valor="visitantes(estadisticas.totales)"
                        :icono="UsersRound"
                        :detalle="
                            coberturaVisitantes(estadisticas.totales) ??
                            'Aproximado: teléfonos o equipos distintos por día; no son personas únicas.'
                        "
                    />
                    <TarjetaIndicador
                        etiqueta="Promedio diario"
                        :valor="numero(estadisticas.totales.promedio_diario)"
                        :icono="Activity"
                        :detalle="`${numero(estadisticas.totales.bots)} accesos automatizados excluidos`"
                    />
                </div>
                <Panel
                    titulo="Accesos por día"
                    :descripcion="
                        estadisticas.varios_enlaces
                            ? 'Cuántos accesos recibió el producto cada día en los últimos 30 días, sumando todos sus enlaces y sin bots identificados.'
                            : 'Cuántos accesos recibió el producto cada día en los últimos 30 días, sin bots identificados.'
                    "
                >
                    <GraficaArea :serie="estadisticas.serie" />
                </Panel>
                <Panel
                    titulo="Dispositivos"
                    descripcion="Categoría aproximada según el navegador."
                >
                    <GraficaBarras :barras="barrasDispositivos" />
                </Panel>
                <p
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <ChartColumnBig class="size-4" />
                    Total histórico{{
                        estadisticas.varios_enlaces
                            ? " (todos los enlaces)"
                            : ""
                    }}:
                    {{ numero(estadisticas.total_historico) }} accesos, desde
                    que el producto pertenece a su negocio actual.
                </p>
            </TabsContent>

            <TabsContent value="historial" class="space-y-6">
                <Panel titulo="Historial de destinos">
                    <HistorialDestinos :cambios="cambios.data" />
                </Panel>
                <Panel titulo="Auditoría del producto">
                    <ListaAuditoria :registros="auditoria.data" />
                </Panel>
            </TabsContent>
        </Tabs>

        <aside class="space-y-6">
            <Panel titulo="Datos administrativos">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Referencia</dt>
                        <dd class="text-right font-medium break-all">
                            {{ producto.referencia_comercial || "—" }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Preparación</dt>
                        <dd>
                            <InsigniaEstado
                                v-if="producto.estado_preparacion"
                                :estado="producto.estado_preparacion"
                                :etiqueta="producto.estado_preparacion_etiqueta"
                            />
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Entrega</dt>
                        <dd>{{ fechaCorta(producto.fecha_entrega) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Registrado</dt>
                        <dd>{{ fechaCorta(producto.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">
                            Destino actualizado
                        </dt>
                        <dd :title="fechaHora(producto.destino_actualizado_en)">
                            {{
                                producto.destino_actualizado_en
                                    ? relativo(producto.destino_actualizado_en)
                                    : "—"
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="producto.suspendido_en"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Suspendido</dt>
                        <dd>{{ fechaHora(producto.suspendido_en) }}</dd>
                    </div>
                </dl>
                <div v-if="producto.notas_internas" class="mt-4 border-t pt-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        Notas internas
                    </p>
                    <p class="mt-1 text-sm whitespace-pre-line">
                        {{ producto.notas_internas }}
                    </p>
                </div>
            </Panel>
            <Panel titulo="Actividad">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Eye class="size-4" /> Accesos totales
                        </dt>
                        <dd class="font-semibold tabular-nums">
                            {{ numero(estadisticas.total_historico) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <CalendarClock class="size-4" /> Último acceso
                        </dt>
                        <dd :title="fechaHora(producto.ultimo_acceso_en)">
                            {{ relativo(producto.ultimo_acceso_en) }}
                        </dd>
                    </div>
                </dl>
            </Panel>
        </aside>
    </div>
</template>
