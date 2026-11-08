<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { Download, Eye, PackageOpen, PencilLine, SearchX } from "@lucide/vue";
import TarjetaProducto from "@/components/cliente/TarjetaProducto.vue";
import type { AccionRegistro } from "@/components/comun/AccionesRegistro.vue";
import AccionesRegistro from "@/components/comun/AccionesRegistro.vue";
import CampoBusqueda from "@/components/comun/CampoBusqueda.vue";
import EncabezadoPagina from "@/components/comun/EncabezadoPagina.vue";
import EstadoVacio from "@/components/comun/EstadoVacio.vue";
import InsigniaEstado from "@/components/comun/InsigniaEstado.vue";
import InsigniaTipo from "@/components/comun/InsigniaTipo.vue";
import Paginacion from "@/components/comun/Paginacion.vue";
import SelectNativo from "@/components/comun/SelectNativo.vue";
import SelectorVista from "@/components/comun/SelectorVista.vue";
import { Button } from "@/components/ui/button";
import { useFiltros } from "@/composables/useFiltros";
import { useVistaListado } from "@/composables/useVistaListado";
import { dominio, numero } from "@/lib/formato";
import { enlacesQr } from "@/lib/productos";
import { index, show } from "@/routes/cliente/productos";
import { show as qrShow } from "@/routes/productos/qr";
import type { Opcion, Paginado, Producto } from "@/types";

defineOptions({
    layout: { breadcrumbs: [{ title: "Mis productos", href: index() }] },
});

const props = defineProps<{
    productos: Paginado<Producto>;
    filtros: { buscar: string; tipo: string; estado: string };
    tipos: Opcion[];
    estados: Opcion[];
    total_productos: number;
}>();

const { filtros, limpiar } = useFiltros(index().url, { ...props.filtros });
const { vista } = useVistaListado("cliente_productos");

const acciones = (p: Producto): AccionRegistro[] => {
    const qrs = enlacesQr(p);

    return [
        {
            clave: "ver",
            etiqueta: p.permisos.cambiar_destino ? "Cambiar destino" : "Ver",
            icono: p.permisos.cambiar_destino ? PencilLine : Eye,
            href: show(p.id),
            principal: true,
        },
        // Un QR: descarga directa; dos QR: una acción por cada QR.
        ...qrs.map((qr, i) => ({
            clave: i === 0 ? "qr" : `qr-${qr.id}`,
            etiqueta: qr.numero_qr
                ? `Descargar ${qr.medio_etiqueta}`
                : "Descargar QR",
            icono: Download,
            href: descarga(qr),
            externo: true,
            principal: qrs.length === 1,
            visible: qr.permisos.descargar_qr && p.tipo !== "nfc",
        })),
    ];
};

const descarga = (p: Producto) =>
    qrShow.url(p.id, { query: { formato: "png", descargar: 1 } });
</script>

<template>
    <Head title="Mis productos" />

    <EncabezadoPagina
        titulo="Mis productos"
        :descripcion="`Tienes ${numero(total_productos)} ${total_productos === 1 ? 'producto' : 'productos'}. Cambia su destino cuando quieras: el QR y la tarjeta siguen siendo los mismos.`"
    />

    <EstadoVacio
        v-if="total_productos === 0"
        class="rounded-xl border bg-card"
        :icono="PackageOpen"
        titulo="Aún no tienes productos asignados"
        descripcion="Cuando DASTI registre tus QR o tarjetas NFC aparecerán aquí."
    />

    <template v-else>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Buscar por nombre o destino…"
                etiqueta="Buscar productos"
            />
            <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                <SelectNativo
                    v-model="filtros.tipo"
                    :opciones="tipos"
                    vacio="Todos los tipos"
                    etiqueta="Filtrar por tipo"
                    class="sm:w-40"
                />
                <SelectNativo
                    v-model="filtros.estado"
                    :opciones="estados"
                    vacio="Todos los estados"
                    etiqueta="Filtrar por estado"
                    class="sm:w-40"
                />
                <SelectorVista v-model="vista" class="col-span-2 sm:ml-2" />
            </div>
        </div>

        <EstadoVacio
            v-if="productos.data.length === 0"
            class="rounded-xl border bg-card"
            :icono="SearchX"
            titulo="No hay productos con esos filtros"
            descripcion="Prueba con otra búsqueda o quita los filtros."
        >
            <Button variant="outline" @click="limpiar">Quitar filtros</Button>
        </EstadoVacio>

        <template v-else>
            <div
                v-if="vista === 'tabla'"
                class="overflow-x-auto rounded-xl border bg-card shadow-xs"
            >
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Mis productos
                    </caption>
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="h-10 px-4 font-medium">
                                Producto
                            </th>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Tipo
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium md:table-cell"
                            >
                                Destino
                            </th>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Estado
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 text-right font-medium sm:table-cell"
                            >
                                Accesos
                            </th>
                            <th
                                scope="col"
                                class="h-10 px-3 text-right font-medium"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="producto in productos.data"
                            :key="producto.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="max-w-64 px-4 py-3">
                                <Link
                                    :href="show(producto.id)"
                                    class="block truncate font-medium hover:text-enlace hover:underline"
                                    >{{ producto.nombre }}</Link
                                >
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ producto.codigo }}</span
                                >
                            </td>
                            <td class="px-3 py-3">
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
                            </td>
                            <td class="hidden max-w-56 px-3 py-3 md:table-cell">
                                <span
                                    class="block truncate"
                                    :title="producto.url_destino ?? undefined"
                                    >{{
                                        producto.url_destino
                                            ? dominio(producto.url_destino)
                                            : "Sin destino"
                                    }}</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <InsigniaEstado
                                    :estado="producto.estado"
                                    :etiqueta="producto.estado_etiqueta"
                                />
                            </td>
                            <td
                                class="hidden px-3 py-3 text-right tabular-nums sm:table-cell"
                            >
                                {{
                                    producto.total_accesos === null
                                        ? "—"
                                        : numero(producto.total_accesos)
                                }}
                            </td>
                            <td class="px-2 py-2">
                                <AccionesRegistro
                                    :acciones="acciones(producto)"
                                    :nombre="producto.nombre"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul
                v-else
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                role="list"
            >
                <li
                    v-for="producto in productos.data"
                    :key="producto.id"
                    class="flex"
                >
                    <TarjetaProducto :producto="producto" class="w-full" />
                </li>
            </ul>
            <div class="rounded-xl border bg-card">
                <Paginacion :meta="productos.meta" nombre="productos" />
            </div>
        </template>
    </template>
</template>
