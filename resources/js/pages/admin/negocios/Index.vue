<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus, SearchX } from '@lucide/vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoOrdenable from '@/components/comun/EncabezadoOrdenable.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { fechaCorta, fechaHora, numero, relativo } from '@/lib/formato';
import { create, index, show } from '@/routes/admin/negocios';
import type { Negocio, Opcion, Paginado } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Negocios', href: index() }],
    },
});

const props = defineProps<{
    negocios: Paginado<Negocio>;
    filtros: {
        buscar: string;
        estado: string;
        orden: string;
        direccion: string;
    };
    estados: Opcion[];
}>();

const { filtros, ordenarPor } = useFiltros(index.url(), { ...props.filtros });

const hayFiltros = () => filtros.buscar !== '' || filtros.estado !== '';

const limpiarFiltros = () => {
    filtros.buscar = '';
    filtros.estado = '';
};
</script>

<template>
    <Head title="Negocios" />

    <EncabezadoPagina
        titulo="Negocios"
        descripcion="Clientes de DASTI y los productos QR y NFC que tienen asignados."
    >
        <template #acciones>
            <Button as-child>
                <Link :href="create()"><Plus /> Nuevo negocio</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div
            class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
        >
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Buscar por nombre, contacto, correo…"
                etiqueta="Buscar negocios"
            />
            <SelectNativo
                v-model="filtros.estado"
                :opciones="estados"
                vacio="Todos los estados"
                etiqueta="Filtrar por estado"
                class="sm:w-48"
            />
            <Button
                v-if="hayFiltros()"
                variant="ghost"
                size="sm"
                class="sm:ml-auto"
                @click="limpiarFiltros"
            >
                Limpiar filtros
            </Button>
        </div>

        <div v-if="negocios.data.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Listado de negocios
                </caption>
                <thead class="bg-muted/40">
                    <tr>
                        <EncabezadoOrdenable
                            campo="nombre"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            @ordenar="ordenarPor"
                            >Negocio</EncabezadoOrdenable
                        >
                        <th
                            scope="col"
                            class="hidden h-10 px-3 text-left font-medium text-muted-foreground lg:table-cell"
                        >
                            Contacto
                        </th>
                        <th
                            scope="col"
                            class="hidden h-10 px-3 text-left font-medium text-muted-foreground xl:table-cell"
                        >
                            Teléfono
                        </th>
                        <EncabezadoOrdenable
                            campo="productos"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            @ordenar="ordenarPor"
                            >Productos</EncabezadoOrdenable
                        >
                        <th
                            scope="col"
                            class="h-10 px-3 text-left font-medium text-muted-foreground"
                        >
                            Estado
                        </th>
                        <EncabezadoOrdenable
                            class="hidden md:table-cell"
                            campo="registro"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            @ordenar="ordenarPor"
                            >Registro</EncabezadoOrdenable
                        >
                        <EncabezadoOrdenable
                            class="hidden lg:table-cell"
                            campo="actividad"
                            :orden="filtros.orden"
                            :direccion="filtros.direccion"
                            @ordenar="ordenarPor"
                            >Última actividad</EncabezadoOrdenable
                        >
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="negocio in negocios.data"
                        :key="negocio.id"
                        class="border-t transition hover:bg-muted/30"
                    >
                        <td class="px-3 py-3">
                            <Link
                                :href="show(negocio.id)"
                                class="font-medium hover:text-primary hover:underline"
                                >{{ negocio.nombre_comercial }}</Link
                            >
                            <p class="text-xs text-muted-foreground lg:hidden">
                                {{
                                    negocio.responsable ??
                                    negocio.email_contacto ??
                                    ''
                                }}
                            </p>
                        </td>
                        <td class="hidden px-3 py-3 lg:table-cell">
                            <p>{{ negocio.responsable ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ negocio.email_contacto ?? 'Sin correo' }}
                            </p>
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground xl:table-cell"
                        >
                            {{ negocio.telefono ?? '—' }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <p class="font-medium tabular-nums">
                                {{ numero(negocio.productos_count) }}
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                QR {{ numero(negocio.productos_qr_count) }} ·
                                NFC {{ numero(negocio.productos_nfc_count) }} ·
                                {{ numero(negocio.productos_activos_count) }}
                                activos
                            </p>
                        </td>
                        <td class="px-3 py-3">
                            <InsigniaEstado
                                :estado="negocio.estado"
                                :etiqueta="negocio.estado_etiqueta"
                            />
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground md:table-cell"
                        >
                            {{ fechaCorta(negocio.created_at) }}
                        </td>
                        <td
                            class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground lg:table-cell"
                        >
                            <time
                                v-if="negocio.ultima_actividad"
                                :datetime="negocio.ultima_actividad"
                                :title="fechaHora(negocio.ultima_actividad)"
                                >{{ relativo(negocio.ultima_actividad) }}</time
                            >
                            <span v-else>Sin accesos</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EstadoVacio
            v-else-if="hayFiltros()"
            :icono="SearchX"
            titulo="Sin resultados"
            descripcion="Ningún negocio coincide con los filtros aplicados."
        >
            <Button variant="outline" size="sm" @click="limpiarFiltros"
                >Limpiar filtros</Button
            >
        </EstadoVacio>
        <EstadoVacio
            v-else
            :icono="Building2"
            titulo="Aún no hay negocios"
            descripcion="Registra el primer cliente para asignarle sus tarjetas NFC y códigos QR."
        >
            <Button as-child
                ><Link :href="create()"
                    ><Plus /> Registrar negocio</Link
                ></Button
            >
        </EstadoVacio>

        <Paginacion :meta="negocios.meta" nombre="negocios" />
    </section>
</template>
