<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FilterX, Nfc, Plus } from '@lucide/vue';
import { computed } from 'vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoOrdenable from '@/components/comun/EncabezadoOrdenable.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { dominio, fechaHora, numero, relativo } from '@/lib/formato';
import { show as negocioShow } from '@/routes/admin/negocios';
import { create, index, show } from '@/routes/admin/productos';
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
    negocios: Opcion[];
    tipos: Opcion[];
    estados: Opcion[];
    preparaciones: Opcion[];
}>();

const { filtros, limpiar, ordenarPor } = useFiltros(index.url(), {
    ...props.filtros,
});

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
</script>

<template>
    <Head title="Productos" />

    <EncabezadoPagina
        titulo="Productos"
        descripcion="Tarjetas NFC, códigos QR y productos combinados vendidos a cada negocio, con su enlace permanente."
    >
        <template #acciones>
            <Button as-child>
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
                    <SelectNativo
                        v-model="filtros.negocio_id"
                        :opciones="negocios"
                        vacio="Todos los negocios"
                        etiqueta="Filtrar por negocio"
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
                <Button
                    v-if="hayFiltros"
                    variant="ghost"
                    size="sm"
                    class="ml-auto"
                    @click="limpiarFiltros"
                >
                    <FilterX /> Limpiar filtros
                </Button>
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
            <Button v-else as-child>
                <Link :href="create()"><Plus /> Registrar producto</Link>
            </Button>
        </EstadoVacio>

        <div v-else class="overflow-x-auto">
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
                        <th
                            scope="col"
                            class="hidden h-10 px-3 text-left font-medium text-muted-foreground xl:table-cell"
                        >
                            Preparación
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
                            class="hidden xl:table-cell"
                            @ordenar="ordenarPor"
                            >Último acceso</EncabezadoOrdenable
                        >
                        <EncabezadoOrdenable
                            campo="registro"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            class="hidden 2xl:table-cell"
                            @ordenar="ordenarPor"
                            >Registro</EncabezadoOrdenable
                        >
                        <th scope="col" class="h-10 px-3">
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
                        <td class="max-w-64 px-3 py-3">
                            <Link
                                :href="show(producto.id)"
                                class="block truncate font-medium hover:text-primary hover:underline"
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
                                v-if="producto.negocio"
                                :href="negocioShow(producto.negocio.id)"
                                class="block truncate hover:text-primary hover:underline"
                                >{{ producto.negocio.nombre_comercial }}</Link
                            >
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
                        </td>
                        <td class="hidden px-3 py-3 xl:table-cell">
                            <InsigniaEstado
                                v-if="producto.estado_preparacion"
                                :estado="producto.estado_preparacion"
                                :etiqueta="producto.estado_preparacion_etiqueta"
                            />
                        </td>
                        <td
                            class="hidden px-3 py-3 text-right tabular-nums sm:table-cell"
                        >
                            {{ numero(producto.total_accesos) }}
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground xl:table-cell"
                        >
                            <time
                                v-if="producto.ultimo_acceso_en"
                                :datetime="producto.ultimo_acceso_en"
                                :title="fechaHora(producto.ultimo_acceso_en)"
                                >{{ relativo(producto.ultimo_acceso_en) }}</time
                            >
                            <span v-else>—</span>
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground 2xl:table-cell"
                        >
                            {{ fechaHora(producto.created_at) }}
                        </td>
                        <td class="px-3 py-3 text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    :href="show(producto.id)"
                                    :aria-label="`Ver ${producto.nombre}`"
                                    >Ver <ChevronRight
                                /></Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacion :meta="productos.meta" nombre="productos" />
    </section>
</template>
