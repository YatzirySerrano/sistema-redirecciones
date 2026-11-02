<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    Eye,
    Mail,
    PackagePlus,
    Pencil,
    Phone,
    Plus,
    SearchX,
} from '@lucide/vue';
import { computed } from 'vue';
import AccionFila from '@/components/comun/AccionFila.vue';
import CambiarEstadoRegistro from '@/components/comun/CambiarEstadoRegistro.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoOrdenable from '@/components/comun/EncabezadoOrdenable.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorVista from '@/components/comun/SelectorVista.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { useVistaListado } from '@/composables/useVistaListado';
import { fechaCorta, fechaHora, numero, relativo } from '@/lib/formato';
import { create, edit, estado, index, show } from '@/routes/admin/negocios';
import { create as crearProducto } from '@/routes/admin/productos';
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
const { vista } = useVistaListado('negocios');
const { puede } = usePermisos();

const hayFiltros = computed(
    () => filtros.buscar !== '' || filtros.estado !== '',
);

const limpiarFiltros = () => {
    filtros.buscar = '';
    filtros.estado = '';
};

const puedeCambiarEstado = (negocio: Negocio) =>
    puede(
        negocio.estado === 'activo'
            ? 'negocios.suspender'
            : 'negocios.reactivar',
    );
</script>

<template>
    <Head title="Negocios" />

    <EncabezadoPagina
        titulo="Negocios"
        descripcion="Clientes de DASTI y los productos QR y NFC que tienen asignados."
    >
        <template #acciones>
            <Button v-if="puede('negocios.crear')" as-child>
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
            <div class="flex items-center gap-2 sm:ml-auto">
                <Button
                    v-if="hayFiltros"
                    variant="ghost"
                    size="sm"
                    @click="limpiarFiltros"
                >
                    Limpiar filtros
                </Button>
                <SelectorVista v-model="vista" class="ml-auto" />
            </div>
        </div>

        <template v-if="negocios.data.length > 0">
            <!-- Vista de tabla -->
            <div v-if="vista === 'tabla'" class="overflow-x-auto">
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
                                class="hidden xl:table-cell"
                                campo="actividad"
                                :orden="filtros.orden"
                                :direccion="filtros.direccion"
                                @ordenar="ordenarPor"
                                >Última actividad</EncabezadoOrdenable
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
                            v-for="negocio in negocios.data"
                            :key="negocio.id"
                            class="border-t transition hover:bg-muted/30"
                        >
                            <td class="px-3 py-3">
                                <Link
                                    :href="show(negocio.id)"
                                    class="font-medium hover:text-enlace hover:underline"
                                    >{{ negocio.nombre_comercial }}</Link
                                >
                                <p
                                    class="text-xs text-muted-foreground lg:hidden"
                                >
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
                                    <template v-if="negocio.telefono">
                                        · {{ negocio.telefono }}</template
                                    >
                                </p>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <p class="font-medium tabular-nums">
                                    {{ numero(negocio.productos_count) }}
                                </p>
                                <p
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    QR {{ numero(negocio.productos_qr_count) }}
                                    · NFC
                                    {{ numero(negocio.productos_nfc_count) }}
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
                                class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground xl:table-cell"
                            >
                                <time
                                    v-if="negocio.ultima_actividad"
                                    :datetime="negocio.ultima_actividad"
                                    :title="fechaHora(negocio.ultima_actividad)"
                                    >{{
                                        relativo(negocio.ultima_actividad)
                                    }}</time
                                >
                                <span v-else>Sin accesos</span>
                            </td>
                            <td class="px-2 py-2">
                                <div
                                    class="flex flex-wrap items-center justify-end gap-0.5"
                                >
                                    <AccionFila
                                        etiqueta="Ver"
                                        :icono="Eye"
                                        :href="show(negocio.id)"
                                        compacta
                                    />
                                    <AccionFila
                                        v-if="puede('negocios.editar')"
                                        etiqueta="Editar"
                                        :icono="Pencil"
                                        :href="edit(negocio.id)"
                                        compacta
                                    />
                                    <AccionFila
                                        v-if="
                                            puede('productos.crear') &&
                                            negocio.estado === 'activo'
                                        "
                                        etiqueta="Agregar producto"
                                        :icono="PackagePlus"
                                        :href="
                                            crearProducto({
                                                query: {
                                                    negocio_id: negocio.id,
                                                },
                                            })
                                        "
                                        compacta
                                    />
                                    <CambiarEstadoRegistro
                                        v-if="puedeCambiarEstado(negocio)"
                                        :url="estado.url(negocio.id)"
                                        :estado="negocio.estado"
                                        :nombre="negocio.nombre_comercial"
                                        tipo="negocio"
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
                aria-label="Listado de negocios"
            >
                <li
                    v-for="negocio in negocios.data"
                    :key="negocio.id"
                    class="flex flex-col rounded-xl border bg-background p-4 shadow-xs transition hover:border-primary/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link
                                :href="show(negocio.id)"
                                class="block truncate font-semibold hover:text-enlace hover:underline"
                                >{{ negocio.nombre_comercial }}</Link
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                {{ negocio.responsable ?? 'Sin responsable' }}
                            </p>
                        </div>
                        <InsigniaEstado
                            :estado="negocio.estado"
                            :etiqueta="negocio.estado_etiqueta"
                        />
                    </div>
                    <dl
                        class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-muted/40 p-2 text-center"
                    >
                        <div>
                            <dt class="text-[11px] text-muted-foreground">
                                Productos
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ numero(negocio.productos_count) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-muted-foreground">
                                QR
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ numero(negocio.productos_qr_count) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-muted-foreground">
                                NFC
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ numero(negocio.productos_nfc_count) }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-3 space-y-1 text-xs text-muted-foreground">
                        <p
                            v-if="negocio.email_contacto"
                            class="flex items-center gap-1.5 truncate"
                        >
                            <Mail
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ negocio.email_contacto }}
                        </p>
                        <p
                            v-if="negocio.telefono"
                            class="flex items-center gap-1.5"
                        >
                            <Phone
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ negocio.telefono }}
                        </p>
                        <p>
                            Última actividad:
                            {{
                                negocio.ultima_actividad
                                    ? relativo(negocio.ultima_actividad)
                                    : 'sin accesos'
                            }}
                        </p>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-0.5 border-t pt-2"
                    >
                        <AccionFila
                            etiqueta="Ver"
                            :icono="Eye"
                            :href="show(negocio.id)"
                        />
                        <AccionFila
                            v-if="puede('negocios.editar')"
                            etiqueta="Editar"
                            :icono="Pencil"
                            :href="edit(negocio.id)"
                        />
                        <CambiarEstadoRegistro
                            v-if="puedeCambiarEstado(negocio)"
                            :url="estado.url(negocio.id)"
                            :estado="negocio.estado"
                            :nombre="negocio.nombre_comercial"
                            tipo="negocio"
                            compacto
                        />
                    </div>
                </li>
            </ul>
        </template>

        <EstadoVacio
            v-else-if="hayFiltros"
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
            <Button v-if="puede('negocios.crear')" as-child
                ><Link :href="create()"
                    ><Plus /> Registrar negocio</Link
                ></Button
            >
        </EstadoVacio>

        <Paginacion :meta="negocios.meta" nombre="negocios" />
    </section>
</template>
