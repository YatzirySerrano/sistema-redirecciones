<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
} from '@lucide/vue';
import { computed } from 'vue';
import CambiarEstadoProducto from '@/components/admin-productos/CambiarEstadoProducto.vue';
import EliminarProducto from '@/components/admin-productos/EliminarProducto.vue';
import TransferirProducto from '@/components/admin-productos/TransferirProducto.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EnlacePermanente from '@/components/comun/EnlacePermanente.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import DisenadorQr from '@/components/productos/DisenadorQr.vue';
import FormularioDestino from '@/components/productos/FormularioDestino.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import InstruccionesNfc from '@/components/productos/InstruccionesNfc.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fechaCorta, fechaHora, numero, relativo } from '@/lib/formato';
import { show as negocioShow } from '@/routes/admin/negocios';
import { destino, edit, index } from '@/routes/admin/productos';
import type {
    CambioDestino,
    Coleccion,
    ElementoDesglose,
    Opcion,
    OpcionesQr,
    Producto,
    PuntoSerie,
    Recurso,
    RegistroAuditoria,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: index() },
            { title: 'Detalle del producto', href: '#' },
        ],
    },
});

const props = defineProps<{
    producto: Recurso<Producto>;
    opciones_qr: OpcionesQr;
    puede_eliminarse: boolean;
    estadisticas: {
        totales: TotalesEstadisticas;
        serie: PuntoSerie[];
        dispositivos: ElementoDesglose[];
    };
    cambios: Coleccion<CambioDestino>;
    auditoria: Coleccion<RegistroAuditoria>;
    negocios: Opcion[];
}>();

const producto = computed(() => props.producto.data);
const negocioSuspendido = computed(
    () => producto.value.negocio?.estado === 'suspendido',
);
const llevaNfc = computed(() => producto.value.tipo !== 'qr');
const llevaQr = computed(() => producto.value.tipo !== 'nfc');

const situacion = computed(() => {
    if (negocioSuspendido.value) {
        return {
            clave: 'negocio_suspendido',
            texto: 'El negocio está suspendido: el enlace muestra «no disponible».',
        };
    }

    if (producto.value.estado === 'suspendido') {
        return {
            clave: 'producto_suspendido',
            texto: 'El producto está suspendido: el enlace muestra «no disponible».',
        };
    }

    if (!producto.value.url_destino) {
        return {
            clave: 'sin_destino',
            texto: 'Sin destino configurado: el enlace muestra «no disponible».',
        };
    }

    return {
        clave: 'redirigiendo',
        texto: 'El enlace redirige al destino configurado.',
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
            <Button variant="outline" as-child>
                <Link :href="edit(producto.id)"><Pencil /> Editar</Link>
            </Button>
            <TransferirProducto :producto="producto" :negocios="negocios" />
            <CambiarEstadoProducto :producto="producto" />
            <EliminarProducto v-if="puede_eliminarse" :producto="producto" />
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
        <Tabs default-value="enlace" class="min-w-0 gap-4">
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
                <Panel
                    titulo="Enlace permanente"
                    descripcion="Es la URL impresa en el QR o grabada en el NFC. Nunca cambia."
                >
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
                        :key="producto.url_destino ?? ''"
                        :producto="producto"
                        :accion="destino.url(producto.id)"
                        :obligatorio="false"
                    />
                </Panel>
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
                    <DisenadorQr :producto="producto" :opciones="opciones_qr" />
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
                    />
                    <TarjetaIndicador
                        etiqueta="Visitantes estimados"
                        :valor="
                            numero(estadisticas.totales.visitantes_estimados)
                        "
                        :icono="UsersRound"
                        detalle="Huellas diarias anónimas distintas; no son personas únicas."
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
                    descripcion="Últimos 30 días, sin bots identificados."
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
                    Total histórico:
                    {{ numero(producto.total_accesos) }} accesos.
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
                            {{ producto.referencia_comercial || '—' }}
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
                                    : '—'
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
                            {{ numero(producto.total_accesos) }}
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
