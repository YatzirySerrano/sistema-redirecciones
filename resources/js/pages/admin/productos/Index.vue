<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
    ArrowRightLeft,
    CirclePause,
    CirclePlay,
    Download,
    Eye,
    FilterX,
    Link2,
    Nfc,
    Pencil,
    Plus,
    QrCode,
    Trash2,
} from "@lucide/vue";
import { computed, ref } from "vue";
import CambiarEstadoProducto from "@/components/admin-productos/CambiarEstadoProducto.vue";
import EliminarProducto from "@/components/admin-productos/EliminarProducto.vue";
import TransferirProducto from "@/components/admin-productos/TransferirProducto.vue";
import type { AccionRegistro } from "@/components/comun/AccionesRegistro.vue";
import AccionesRegistro from "@/components/comun/AccionesRegistro.vue";
import CampoBusqueda from "@/components/comun/CampoBusqueda.vue";
import EncabezadoOrdenable from "@/components/comun/EncabezadoOrdenable.vue";
import EncabezadoPagina from "@/components/comun/EncabezadoPagina.vue";
import EstadoVacio from "@/components/comun/EstadoVacio.vue";
import InsigniaEstado from "@/components/comun/InsigniaEstado.vue";
import InsigniaTipo from "@/components/comun/InsigniaTipo.vue";
import Paginacion from "@/components/comun/Paginacion.vue";
import SelectNativo from "@/components/comun/SelectNativo.vue";
import SelectorVista from "@/components/comun/SelectorVista.vue";
import SelectorBuscable from "@/components/formularios/SelectorBuscable.vue";
import { Button } from "@/components/ui/button";
import { useFiltros } from "@/composables/useFiltros";
import { usePermisos } from "@/composables/usePermisos";
import { useVistaListado } from "@/composables/useVistaListado";
import { dominio, fechaHora, numero, relativo } from "@/lib/formato";
import {
    enlacesDe,
    enlacesQr,
    etiquetaCorta,
    tieneVariosEnlaces,
} from "@/lib/productos";
import { show as negocioShow } from "@/routes/admin/negocios";
import { negocios as opcionesNegocios } from "@/routes/admin/opciones";
import { create, edit, index, show } from "@/routes/admin/productos";
import { show as qrShow } from "@/routes/productos/qr";
import type { Opcion, Paginado, Producto } from "@/types";

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Productos", href: index() }],
    },
});

const props = defineProps<{
    productos: Paginado<Producto>;
    filtros: {
        buscar: string;
        negocio_id: string;
        tipo: string;
        estado: string;
        preparacion: string;
        desde: string;
        hasta: string;
        orden: string;
        direccion: string;
    };
    negocio_filtro: Opcion | null;
    tipos: Opcion[];
    estados: Opcion[];
    preparaciones: Opcion[];
}>();

const { filtros, limpiar, ordenarPor } = useFiltros(index.url(), {
    ...props.filtros,
});
const { vista } = useVistaListado("productos");
const { puede } = usePermisos();

const hayFiltros = computed(() =>
    [
        "buscar",
        "negocio_id",
        "tipo",
        "estado",
        "preparacion",
        "desde",
        "hasta",
    ].some((clave) => (filtros as Record<string, string>)[clave] !== ""),
);

const limpiarFiltros = () => {
    const orden = filtros.orden;
    const direccion = filtros.direccion;
    limpiar();
    filtros.orden = orden;
    filtros.direccion = direccion;
};

/** Varios enlaces: NFC + QR con destinos independientes, o dos QR. */
const independiente = (p: Producto) => tieneVariosEnlaces(p);

const acciones = (p: Producto): AccionRegistro[] => [
    {
        clave: "ver",
        etiqueta: "Ver",
        icono: Eye,
        href: show(p.id),
        principal: true,
    },
    {
        clave: "editar",
        etiqueta: "Editar",
        icono: Pencil,
        href: edit(p.id),
        principal: true,
        visible: puede("productos.editar"),
    },
    // Una acción de descarga por cada QR impreso (QR 1 / QR 2).
    ...enlacesQr(p).map((qr, i) => ({
        clave: i === 0 ? "qr" : `qr-${qr.id}`,
        etiqueta: qr.numero_qr
            ? `Descargar ${qr.medio_etiqueta}`
            : independiente(p)
              ? "Descargar QR (reverso)"
              : "Descargar QR",
        icono: Download,
        href: qrShow.url(qr.id, {
            query: { formato: "png", descargar: 1 },
        }),
        externo: true,
        visible: qr.permisos.descargar_qr && p.tipo !== "nfc",
    })),
    {
        clave: "transferir",
        etiqueta: "Transferir",
        icono: ArrowRightLeft,
        visible: puede("productos.transferir"),
    },
    {
        clave: "estado",
        etiqueta: p.estado === "activo" ? "Suspender" : "Reactivar",
        icono: p.estado === "activo" ? CirclePause : CirclePlay,
        destructiva: p.estado === "activo",
        visible: puede(
            p.estado === "activo"
                ? "productos.suspender"
                : "productos.reactivar",
        ),
    },
    {
        // Eliminación física solo para productos pendientes sin accesos (el
        // servidor vuelve a comprobarlo); el resto se suspende.
        clave: "eliminar",
        etiqueta: "Eliminar",
        icono: Trash2,
        destructiva: true,
        visible:
            puede("productos.eliminar") &&
            p.estado_preparacion === "pendiente" &&
            (p.total_accesos ?? 0) === 0,
    },
];

// Un solo diálogo por tipo de acción, para el producto elegido.
const seleccionado = ref<Producto | null>(null);
const dialogo = ref<"transferir" | "estado" | "eliminar" | null>(null);
const abrir = (p: Producto, clave: string) => {
    if (clave === "transferir" || clave === "estado" || clave === "eliminar") {
        seleccionado.value = p;
        dialogo.value = clave;
    }
};
const dialogoAbierto = (clave: string) =>
    computed({
        get: () => dialogo.value === clave,
        set: (v: boolean) => {
            if (!v) {
                dialogo.value = null;
            }
        },
    });
const abiertoTransferir = dialogoAbierto("transferir");
const abiertoEstado = dialogoAbierto("estado");
const abiertoEliminar = dialogoAbierto("eliminar");
</script>

<template>
    <Head title="Productos" />

    <EncabezadoPagina
        titulo="Productos"
        descripcion="Tarjetas NFC, códigos QR y productos combinados de cada negocio, con su enlace permanente."
        ayuda="Cada producto físico tiene un enlace permanente propio. El destino puede cambiar; el enlace grabado o impreso nunca."
        :icono="Nfc"
    >
        <template #acciones>
            <Button v-if="puede('productos.crear')" as-child>
                <Link :href="create()"><Plus /> Registrar producto</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <section class="rounded-xl border bg-card shadow-xs">
        <div class="space-y-3 border-b p-3 sm:p-4">
            <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                <CampoBusqueda
                    v-model="filtros.buscar"
                    placeholder="Nombre, código, destino o referencia…"
                    etiqueta="Buscar productos"
                />
                <div class="grid flex-1 grid-cols-2 gap-2 md:grid-cols-4">
                    <SelectorBuscable
                        v-model="filtros.negocio_id"
                        :url="opcionesNegocios.url()"
                        :seleccion-inicial="negocio_filtro"
                        vacio="Todos los negocios"
                        etiqueta="Filtrar por negocio"
                        placeholder-busqueda="Buscar negocio…"
                        class="col-span-2 md:col-span-1"
                    />
                    <SelectNativo
                        v-model="filtros.tipo"
                        :opciones="tipos"
                        vacio="Todos los tipos"
                        etiqueta="Filtrar por tipo"
                    />
                    <SelectNativo
                        v-model="filtros.estado"
                        :opciones="estados"
                        vacio="Todos los estados"
                        etiqueta="Filtrar por estado"
                    />
                    <SelectNativo
                        v-model="filtros.preparacion"
                        :opciones="preparaciones"
                        vacio="Toda preparación"
                        etiqueta="Filtrar por preparación"
                        class="col-span-2 md:col-span-1"
                    />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-xs text-muted-foreground"
                    >Registrados entre</span
                >
                <label class="sr-only" for="filtro-desde"
                    >Registrados desde</label
                >
                <input
                    id="filtro-desde"
                    v-model="filtros.desde"
                    type="date"
                    :max="filtros.hasta || undefined"
                    class="h-8 min-w-0 rounded-lg border border-input bg-background px-2 text-xs"
                />
                <span class="text-xs text-muted-foreground" aria-hidden="true"
                    >y</span
                >
                <label class="sr-only" for="filtro-hasta"
                    >Registrados hasta</label
                >
                <input
                    id="filtro-hasta"
                    v-model="filtros.hasta"
                    type="date"
                    :min="filtros.desde || undefined"
                    class="h-8 min-w-0 rounded-lg border border-input bg-background px-2 text-xs"
                />
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        v-if="hayFiltros"
                        variant="ghost"
                        size="sm"
                        @click="limpiarFiltros"
                    >
                        <FilterX /> Limpiar
                    </Button>
                    <SelectorVista v-model="vista" />
                </div>
            </div>
        </div>

        <EstadoVacio
            v-if="productos.data.length === 0"
            :icono="Nfc"
            :titulo="
                hayFiltros
                    ? 'Ningún producto coincide con los filtros'
                    : 'Aún no hay productos registrados'
            "
            :descripcion="
                hayFiltros
                    ? 'Prueba con otros criterios o limpia los filtros.'
                    : 'Registra la primera tarjeta NFC o código QR de un negocio.'
            "
        >
            <Button v-if="hayFiltros" variant="outline" @click="limpiarFiltros"
                >Limpiar filtros</Button
            >
            <Button v-else-if="puede('productos.crear')" as-child>
                <Link :href="create()"><Plus /> Registrar producto</Link>
            </Button>
        </EstadoVacio>

        <!-- Tabla: lectura rápida y comparación de muchos productos. -->
        <div v-else-if="vista === 'tabla'" class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-sm">
                <caption class="sr-only">
                    Listado de productos
                </caption>
                <thead
                    class="bg-muted/40 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <EncabezadoOrdenable
                            campo="nombre"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            @ordenar="ordenarPor"
                            >Producto</EncabezadoOrdenable
                        >
                        <th
                            scope="col"
                            class="hidden h-10 px-3 font-medium xl:table-cell"
                        >
                            Tipo
                        </th>
                        <th scope="col" class="h-10 px-3 font-medium">
                            Destino
                        </th>
                        <th scope="col" class="h-10 px-3 font-medium">
                            Estado
                        </th>
                        <EncabezadoOrdenable
                            campo="accesos"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            class="text-right"
                            @ordenar="ordenarPor"
                            >Accesos</EncabezadoOrdenable
                        >
                        <th
                            scope="col"
                            class="h-10 w-px px-3 text-right font-medium"
                        >
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="producto in productos.data"
                        :key="producto.id"
                        class="border-t transition-colors hover:bg-muted/30"
                    >
                        <td class="max-w-52 px-3 py-2.5 xl:max-w-72">
                            <Link
                                :href="show(producto.id)"
                                class="block truncate font-medium hover:text-enlace"
                                >{{ producto.nombre }}</Link
                            >
                            <p
                                class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span class="shrink-0 font-mono">{{
                                    producto.codigo
                                }}</span>
                                <span class="shrink-0 xl:hidden"
                                    >· {{ producto.tipo_etiqueta }}</span
                                >
                                <template v-if="producto.negocio">
                                    <span aria-hidden="true">·</span>
                                    <Link
                                        v-if="puede('negocios.ver')"
                                        :href="negocioShow(producto.negocio.id)"
                                        class="truncate hover:text-enlace"
                                        >{{
                                            producto.negocio.nombre_comercial
                                        }}</Link
                                    >
                                    <span v-else class="truncate">{{
                                        producto.negocio.nombre_comercial
                                    }}</span>
                                </template>
                            </p>
                        </td>
                        <td
                            class="hidden px-3 py-2.5 whitespace-nowrap xl:table-cell"
                        >
                            <InsigniaTipo
                                :tipo="producto.tipo"
                                :etiqueta="producto.tipo_etiqueta"
                            />
                            <p
                                v-if="producto.cantidad_qr === 2"
                                class="mt-0.5 text-[11px] font-medium text-primary"
                            >
                                2 QR
                            </p>
                            <p
                                v-else-if="independiente(producto)"
                                class="mt-0.5 text-[11px] text-muted-foreground"
                            >
                                2 destinos
                            </p>
                        </td>
                        <td class="max-w-40 px-3 py-2.5 xl:max-w-56">
                            <template v-if="independiente(producto)">
                                <p
                                    v-for="enlace in enlacesDe(producto)"
                                    :key="enlace.id"
                                    class="truncate text-xs"
                                    :title="enlace.url_destino ?? undefined"
                                >
                                    <span class="text-muted-foreground"
                                        >{{ etiquetaCorta(enlace) }}:</span
                                    >
                                    {{
                                        enlace.url_destino
                                            ? dominio(enlace.url_destino)
                                            : "sin destino"
                                    }}
                                </p>
                            </template>
                            <span
                                v-else-if="producto.url_destino"
                                class="block truncate"
                                :title="producto.url_destino"
                                >{{ dominio(producto.url_destino) }}</span
                            >
                            <span v-else class="text-warning">Sin destino</span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            <InsigniaEstado
                                :estado="producto.estado"
                                :etiqueta="producto.estado_etiqueta"
                            />
                            <p
                                v-if="producto.estado_preparacion_etiqueta"
                                class="mt-0.5 text-[11px] text-muted-foreground"
                            >
                                {{ producto.estado_preparacion_etiqueta }}
                            </p>
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ numero(producto.total_accesos) }}
                            <p
                                v-if="producto.ultimo_acceso_en"
                                class="text-[11px] text-muted-foreground"
                                :title="fechaHora(producto.ultimo_acceso_en)"
                            >
                                {{ relativo(producto.ultimo_acceso_en) }}
                            </p>
                        </td>
                        <td class="px-2 py-2.5">
                            <AccionesRegistro
                                :acciones="acciones(producto)"
                                :nombre="producto.nombre"
                                @accion="abrir(producto, $event)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tarjetas: lo esencial de cada producto físico de un vistazo. -->
        <ul
            v-else
            class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 sm:p-4 xl:grid-cols-3"
            aria-label="Listado de productos"
        >
            <li
                v-for="producto in productos.data"
                :key="producto.id"
                class="flex min-w-0 flex-col rounded-xl border bg-background transition hover:border-primary/40 hover:shadow-sm"
            >
                <div class="flex items-start gap-3 p-4 pb-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        aria-hidden="true"
                    >
                        <QrCode v-if="producto.tipo === 'qr'" class="size-5" />
                        <Nfc v-else class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="show(producto.id)"
                            class="block truncate font-semibold hover:text-enlace"
                            >{{ producto.nombre }}</Link
                        >
                        <p class="truncate text-xs text-muted-foreground">
                            {{ producto.negocio?.nombre_comercial }} ·
                            {{ producto.tipo_etiqueta }}
                        </p>
                    </div>
                    <InsigniaEstado
                        :estado="producto.estado"
                        :etiqueta="producto.estado_etiqueta"
                    />
                </div>
                <div class="mx-4 space-y-1.5 rounded-lg bg-muted/40 p-2.5">
                    <p
                        v-for="enlace in enlacesDe(producto)"
                        :key="enlace.id"
                        class="flex min-w-0 items-center gap-1.5 text-xs"
                    >
                        <Link2
                            class="size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            v-if="independiente(producto)"
                            class="shrink-0 text-muted-foreground"
                            >{{ etiquetaCorta(enlace) }}</span
                        >
                        <span
                            class="min-w-0 truncate font-medium"
                            :class="!enlace.url_destino && 'text-warning'"
                            :title="enlace.url_destino ?? undefined"
                            >{{
                                enlace.url_destino
                                    ? dominio(enlace.url_destino)
                                    : "Sin destino"
                            }}</span
                        >
                    </p>
                </div>
                <div
                    class="mt-auto flex items-center justify-between gap-2 px-4 pt-3 pb-3"
                >
                    <p class="text-xs text-muted-foreground tabular-nums">
                        <span class="font-semibold text-foreground">{{
                            numero(producto.total_accesos)
                        }}</span>
                        accesos
                    </p>
                    <AccionesRegistro
                        :acciones="acciones(producto)"
                        :nombre="producto.nombre"
                        variante="tarjeta"
                        @accion="abrir(producto, $event)"
                    />
                </div>
            </li>
        </ul>

        <Paginacion :meta="productos.meta" nombre="productos" />
    </section>

    <template v-if="seleccionado">
        <TransferirProducto
            :key="`t-${seleccionado.id}`"
            v-model:open="abiertoTransferir"
            :producto="seleccionado"
            sin-disparador
        />
        <CambiarEstadoProducto
            :key="`e-${seleccionado.id}`"
            v-model:open="abiertoEstado"
            :producto="seleccionado"
            sin-disparador
        />
        <EliminarProducto
            :key="`d-${seleccionado.id}`"
            v-model:open="abiertoEliminar"
            :producto="seleccionado"
            sin-disparador
        />
    </template>
</template>
