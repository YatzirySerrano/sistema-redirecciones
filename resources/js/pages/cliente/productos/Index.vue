<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PackageOpen, SearchX } from '@lucide/vue';
import TarjetaProducto from '@/components/cliente/TarjetaProducto.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { numero } from '@/lib/formato';
import { index } from '@/routes/cliente/productos';
import type { Opcion, Paginado, Producto } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Mis productos', href: index() }] },
});

const props = defineProps<{
    productos: Paginado<Producto>;
    filtros: { buscar: string; tipo: string; estado: string };
    tipos: Opcion[];
    estados: Opcion[];
    total_productos: number;
}>();

const { filtros, limpiar } = useFiltros(index().url, { ...props.filtros });
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
            <div class="grid grid-cols-2 gap-2 sm:flex">
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
            <ul
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
