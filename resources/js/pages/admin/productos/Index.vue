<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Eye, FilterX, Nfc, Pencil, Plus } from '@lucide/vue';
import { computed } from 'vue';
import CambiarEstadoProducto from '@/components/admin-productos/CambiarEstadoProducto.vue';
import EliminarProducto from '@/components/admin-productos/EliminarProducto.vue';
import TransferirProducto from '@/components/admin-productos/TransferirProducto.vue';
import AccionFila from '@/components/comun/AccionFila.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoOrdenable from '@/components/comun/EncabezadoOrdenable.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorVista from '@/components/comun/SelectorVista.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { useVistaListado } from '@/composables/useVistaListado';
import { dominio, fechaHora, numero, relativo } from '@/lib/formato';
import { show as negocioShow } from '@/routes/admin/negocios';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { create, edit, index, show } from '@/routes/admin/productos';
import { show as qrShow } from '@/routes/productos/qr';
import type { Opcion, Paginado, Producto } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: index() }],
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
const { vista } = useVistaListado('productos');
const { puede } = usePermisos();

const hayFiltros = computed(() =>
    [
        'buscar',
        'negocio_id',
        'tipo',
        'estado',
        'preparacion',
        'desde',
        'hasta',
    ].some((clave) => (filtros as Record<string, string>)[clave] !== ''),
);

const limpiarFiltros = () => {
    const orden = filtros.orden;
    const direccion = filtros.direccion;
    limpiar();
    filtros.orden = orden;
    filtros.direccion = direccion;
};

const puedeCambiarEstado = (p: Producto) =>
    puede(
        p.estado === 'activo' ? 'productos.suspender' : 'productos.reactivar',
    );

// Eliminación física solo para productos pendientes sin accesos (el
// servidor vuelve a comprobarlo); el resto se suspende.
const puedeEliminarse = (p: Producto) =>
    puede('productos.eliminar') &&
    p.estado_preparacion === 'pendiente' &&
    (p.total_accesos ?? 0) === 0;

const descarga = (p: Producto) =>
    qrShow.url(p.id, { query: { formato: 'png', descargar: 1 } });
</script>

<template>
    <Head title="Productos" />

    <EncabezadoPagina
        titulo="Productos"
        descripcion="Tarjetas NFC, códigos QR y productos combinados vendidos a cada negocio, con su enlace permanente."
    >
        <template #acciones>
            <Button v-if="puede('productos.crear')" as-child>
                <Link :href="create()"><Plus /> Registrar producto</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div class="space-y-3 border-b p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <CampoBusqueda
                    v-model="filtros.buscar"
                    placeholder="Nombre, código, destino o referencia…"
                    etiqueta="Buscar productos"
                />
                <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
                    <SelectorBuscable
                        v-model="filtros.negocio_id"
                        :url="opcionesNegocios.url()"
                        :seleccion-inicial="negocio_filtro"
                        vacio="Todos los negocios"
                        etiqueta="Filtrar por negocio"
                        placeholder-busqueda="Buscar negocio…"
                        class="col-span-2 sm:col-span-1"
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
                        class="col-span-2 sm:col-span-1"
                    />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-foreground">Registrados entre</span>
                <label class="sr-only" for="filtro-desde"
                    >Registrados desde</label
                >
                <input
                    id="filtro-desde"
                    v-model="filtros.desde"
                    type="date"
                    :max="filtros.hasta || undefined"
                    class="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                />
                <span class="text-muted-foreground" aria-hidden="true">y</span>
                <label class="sr-only" for="filtro-hasta"
                    >Registrados hasta</label
                >
                <input
                    id="filtro-hasta"
                    v-model="filtros.hasta"
                    type="date"
                    :min="filtros.desde || undefined"
                    class="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                />
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        v-if="hayFiltros"
                        variant="ghost"
                        size="sm"
                        @click="limpiarFiltros"
                    >
                        <FilterX /> Limpiar filtros
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

        <!-- Vista de tabla -->
        <div v-else-if="vista === 'tabla'" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Listado de productos
                </caption>
                <thead class="bg-muted/40">
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
                            class="hidden h-10 px-3 text-left font-medium text-muted-foreground md:table-cell"
                        >
                            Negocio
                        </th>
                        <th
                            scope="col"
                            class="h-10 px-3 text-left font-medium text-muted-foreground"
                        >
                            Tipo
                        </th>
                        <th
                            scope="col"
                            class="hidden h-10 px-3 text-left font-medium text-muted-foreground lg:table-cell"
                        >
                            Destino
                        </th>
                        <th
                            scope="col"
                            class="h-10 px-3 text-left font-medium text-muted-foreground"
                        >
                            Estado
                        </th>
                        <EncabezadoOrdenable
                            campo="accesos"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            class="hidden sm:table-cell"
                            @ordenar="ordenarPor"
                            >Accesos</EncabezadoOrdenable
                        >
                        <EncabezadoOrdenable
                            campo="actividad"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            class="hidden 2xl:table-cell"
                            @ordenar="ordenarPor"
                            >Último acceso</EncabezadoOrdenable
                        >
                        <th
                            scope="col"
                            class="h-10 px-3 text-right font-medium text-muted-foreground"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="producto in productos.data"
                        :key="producto.id"
                        class="border-t transition-colors hover:bg-muted/30"
                    >
                        <td class="max-w-64 px-3 py-3">
                            <Link
                                :href="show(producto.id)"
                                class="block truncate font-medium hover:text-enlace hover:underline"
                                >{{ producto.nombre }}</Link
                            >
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ producto.codigo }}</span
                            >
                            <span
                                v-if="producto.negocio"
                                class="block truncate text-xs text-muted-foreground md:hidden"
                                >{{ producto.negocio.nombre_comercial }}</span
                            >
                        </td>
                        <td class="hidden max-w-52 px-3 py-3 md:table-cell">
                            <Link
                                v-if="producto.negocio && puede('negocios.ver')"
                                :href="negocioShow(producto.negocio.id)"
                                class="block truncate hover:text-enlace hover:underline"
                                >{{ producto.negocio.nombre_comercial }}</Link
                            >
                            <span v-else-if="producto.negocio">{{
                                producto.negocio.nombre_comercial
                            }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <InsigniaTipo
                                :tipo="producto.tipo"
                                :etiqueta="producto.tipo_etiqueta"
                            />
                        </td>
                        <td class="hidden max-w-56 px-3 py-3 lg:table-cell">
                            <span
                                v-if="producto.url_destino"
                                class="block truncate"
                                :title="producto.url_destino"
                                >{{ dominio(producto.url_destino) }}</span
                            >
                            <span v-else class="text-muted-foreground italic"
                                >Sin destino</span
                            >
                        </td>
                        <td class="px-3 py-3">
                            <InsigniaEstado
                                :estado="producto.estado"
                                :etiqueta="producto.estado_etiqueta"
                            />
                            <span
                                v-if="producto.estado_preparacion_etiqueta"
                                class="mt-1 block text-xs text-muted-foreground"
                                >{{
                                    producto.estado_preparacion_etiqueta
                                }}</span
                            >
                        </td>
                        <td
                            class="hidden px-3 py-3 text-right tabular-nums sm:table-cell"
                        >
                            {{ numero(producto.total_accesos) }}
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground 2xl:table-cell"
                        >
                            <time
                                v-if="producto.ultimo_acceso_en"
                                :datetime="producto.ultimo_acceso_en"
                                :title="fechaHora(producto.ultimo_acceso_en)"
                                >{{ relativo(producto.ultimo_acceso_en) }}</time
                            >
                            <span v-else>—</span>
                        </td>
                        <td class="px-2 py-2">
                            <div
                                class="flex flex-wrap items-center justify-end gap-0.5"
                            >
                                <AccionFila
                                    etiqueta="Ver"
                                    :icono="Eye"
                                    :href="show(producto.id)"
                                    compacta
                                />
                                <AccionFila
                                    v-if="puede('productos.editar')"
                                    etiqueta="Editar"
                                    :icono="Pencil"
                                    :href="edit(producto.id)"
                                    compacta
                                />
                                <AccionFila
                                    v-if="producto.permisos.descargar_qr"
                                    etiqueta="Descargar QR"
                                    :icono="Download"
                                    :href="descarga(producto)"
                                    externo
                                    compacta
                                />
                                <TransferirProducto
                                    v-if="puede('productos.transferir')"
                                    :producto="producto"
                                    compacto
                                />
                                <CambiarEstadoProducto
                                    v-if="puedeCambiarEstado(producto)"
                                    :producto="producto"
                                    compacto
                                />
                                <EliminarProducto
                                    v-if="puedeEliminarse(producto)"
                                    :producto="producto"
                                    compacto
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Vista de tarjetas -->
        <ul
            v-else
            class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3"
            aria-label="Listado de productos"
        >
            <li
                v-for="producto in productos.data"
                :key="producto.id"
                class="flex flex-col rounded-xl border bg-background p-4 shadow-xs transition hover:border-primary/40"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link
                            :href="show(producto.id)"
                            class="block truncate font-semibold hover:text-enlace hover:underline"
                            >{{ producto.nombre }}</Link
                        >
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ producto.codigo }}
                        </p>
                    </div>
                    <InsigniaEstado
                        :estado="producto.estado"
                        :etiqueta="producto.estado_etiqueta"
                    />
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <InsigniaTipo
                        :tipo="producto.tipo"
                        :etiqueta="producto.tipo_etiqueta"
                    />
                    <span
                        v-if="producto.negocio"
                        class="truncate text-muted-foreground"
                        >{{ producto.negocio.nombre_comercial }}</span
                    >
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div class="min-w-0 rounded-lg bg-muted/40 p-2">
                        <dt class="text-muted-foreground">Destino</dt>
                        <dd
                            class="truncate font-medium"
                            :title="producto.url_destino ?? undefined"
                        >
                            {{
                                producto.url_destino
                                    ? dominio(producto.url_destino)
                                    : 'Sin destino'
                            }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/40 p-2">
                        <dt class="text-muted-foreground">Accesos</dt>
                        <dd class="font-medium tabular-nums">
                            {{ numero(producto.total_accesos) }}
                            <span
                                v-if="producto.ultimo_acceso_en"
                                class="font-normal text-muted-foreground"
                                >·
                                {{ relativo(producto.ultimo_acceso_en) }}</span
                            >
                        </dd>
                    </div>
                </dl>
                <div
                    class="mt-3 flex flex-wrap items-center gap-0.5 border-t pt-2"
                >
                    <AccionFila
                        etiqueta="Ver"
                        :icono="Eye"
                        :href="show(producto.id)"
                    />
                    <AccionFila
                        v-if="puede('productos.editar')"
                        etiqueta="Editar"
                        :icono="Pencil"
                        :href="edit(producto.id)"
                    />
                    <AccionFila
                        v-if="producto.permisos.descargar_qr"
                        etiqueta="QR"
                        :icono="Download"
                        :href="descarga(producto)"
                        externo
                    />
                    <TransferirProducto
                        v-if="puede('productos.transferir')"
                        :producto="producto"
                        compacto
                    />
                    <CambiarEstadoProducto
                        v-if="puedeCambiarEstado(producto)"
                        :producto="producto"
                        compacto
                    />
                    <EliminarProducto
                        v-if="puedeEliminarse(producto)"
                        :producto="producto"
                        compacto
                    />
                </div>
            </li>
        </ul>

        <Paginacion :meta="productos.meta" nombre="productos" />
    </section>
</template>
