<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FilterX, Link2, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import FormularioDestino from '@/components/productos/FormularioDestino.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFiltros } from '@/composables/useFiltros';
import { dominio, fechaHora, numero, relativo } from '@/lib/formato';
import { index } from '@/routes/admin/enlaces';
import { show as negocioShow } from '@/routes/admin/negocios';
import { destino, show as productoShow } from '@/routes/admin/productos';
import type {
    CambioDestino,
    Coleccion,
    Opcion,
    Paginado,
    Producto,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Redirecciones', href: index() }],
    },
});

const props = defineProps<{
    enlaces: Paginado<Producto>;
    filtros: { buscar: string; negocio_id: string; situacion: string };
    situaciones: Opcion[];
    negocios: Opcion[];
    cambios_recientes: Coleccion<CambioDestino>;
}>();

const { filtros, limpiar } = useFiltros(index.url(), { ...props.filtros });
const hayFiltros = computed(() => Object.values(filtros).some((v) => v !== ''));

const situacion = (p: Producto) => {
    if (p.negocio?.estado === 'suspendido') {
        return 'negocio_suspendido';
    }

    if (p.estado === 'suspendido') {
        return 'producto_suspendido';
    }

    return p.url_destino ? 'redirigiendo' : 'sin_destino';
};

const editandoId = ref<number | null>(null);
const editando = computed(
    () => props.enlaces.data.find((p) => p.id === editandoId.value) ?? null,
);
const dialogoAbierto = computed({
    get: () => editando.value !== null,
    set: (valor: boolean) => {
        if (!valor) {
            editandoId.value = null;
        }
    },
});
</script>

<template>
    <Head title="Redirecciones" />

    <EncabezadoPagina
        titulo="Redirecciones"
        descripcion="Enlaces permanentes y su destino efectivo. Cambiar el destino no altera el QR impreso ni la tarjeta NFC."
    />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section
            class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <div
                class="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center"
            >
                <CampoBusqueda
                    v-model="filtros.buscar"
                    placeholder="Código, producto o destino…"
                    etiqueta="Buscar enlaces"
                />
                <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
                    <SelectNativo
                        v-model="filtros.negocio_id"
                        :opciones="negocios"
                        vacio="Todos los negocios"
                        etiqueta="Filtrar por negocio"
                    />
                    <SelectNativo
                        v-model="filtros.situacion"
                        :opciones="situaciones"
                        vacio="Todas las situaciones"
                        etiqueta="Filtrar por situación"
                    />
                </div>
                <Button
                    v-if="hayFiltros"
                    variant="ghost"
                    size="sm"
                    @click="limpiar"
                    ><FilterX /> Limpiar</Button
                >
            </div>

            <EstadoVacio
                v-if="enlaces.data.length === 0"
                :icono="Link2"
                :titulo="
                    hayFiltros
                        ? 'Ningún enlace coincide con los filtros'
                        : 'Aún no hay enlaces permanentes'
                "
                :descripcion="
                    hayFiltros
                        ? 'Prueba con otros criterios.'
                        : 'Los enlaces se crean al registrar productos.'
                "
            >
                <Button v-if="hayFiltros" variant="outline" @click="limpiar"
                    >Limpiar filtros</Button
                >
            </EstadoVacio>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Enlaces permanentes
                    </caption>
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Enlace
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium md:table-cell"
                            >
                                Producto
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium lg:table-cell"
                            >
                                Destino
                            </th>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Situación
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium sm:table-cell"
                            >
                                Último cambio
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 text-right font-medium sm:table-cell"
                            >
                                Accesos
                            </th>
                            <th scope="col" class="h-10 px-3">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="enlace in enlaces.data"
                            :key="enlace.id"
                            class="border-t align-middle hover:bg-muted/30"
                        >
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1">
                                    <code class="font-mono text-[13px]"
                                        >/r/{{ enlace.codigo }}</code
                                    >
                                    <BotonCopiar
                                        :texto="enlace.url_permanente"
                                        etiqueta="Copiar enlace permanente"
                                        mensaje="Enlace permanente copiado"
                                        solo-icono
                                        variante="ghost"
                                    />
                                </div>
                                <Link
                                    :href="productoShow(enlace.id)"
                                    class="block max-w-48 truncate text-xs text-muted-foreground hover:text-primary md:hidden"
                                    >{{ enlace.nombre }}</Link
                                >
                            </td>
                            <td class="hidden max-w-56 px-3 py-3 md:table-cell">
                                <Link
                                    :href="productoShow(enlace.id)"
                                    class="block truncate font-medium hover:text-primary hover:underline"
                                    >{{ enlace.nombre }}</Link
                                >
                                <Link
                                    v-if="enlace.negocio"
                                    :href="negocioShow(enlace.negocio.id)"
                                    class="block truncate text-xs text-muted-foreground hover:text-primary"
                                    >{{ enlace.negocio.nombre_comercial }}</Link
                                >
                            </td>
                            <td class="hidden max-w-60 px-3 py-3 lg:table-cell">
                                <a
                                    v-if="enlace.url_destino"
                                    :href="enlace.url_destino"
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    class="block truncate hover:text-primary hover:underline"
                                    :title="enlace.url_destino"
                                    >{{ dominio(enlace.url_destino) }}</a
                                >
                                <span
                                    v-else
                                    class="text-muted-foreground italic"
                                    >Sin destino</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <InsigniaEstado :estado="situacion(enlace)" />
                            </td>
                            <td
                                class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground sm:table-cell"
                            >
                                <time
                                    v-if="enlace.destino_actualizado_en"
                                    :datetime="enlace.destino_actualizado_en"
                                    :title="
                                        fechaHora(enlace.destino_actualizado_en)
                                    "
                                    >{{
                                        relativo(enlace.destino_actualizado_en)
                                    }}</time
                                >
                                <span v-else>—</span>
                            </td>
                            <td
                                class="hidden px-3 py-3 text-right tabular-nums sm:table-cell"
                            >
                                {{ numero(enlace.total_accesos) }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Editar destino de ${enlace.nombre}`"
                                    @click="editandoId = enlace.id"
                                >
                                    <Pencil />
                                    <span class="hidden sm:inline"
                                        >Destino</span
                                    >
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :meta="enlaces.meta" nombre="enlaces" />
        </section>

        <aside>
            <Panel
                titulo="Cambios recientes"
                descripcion="Últimas modificaciones de destino en toda la plataforma."
            >
                <HistorialDestinos
                    :cambios="cambios_recientes.data"
                    mostrar-producto
                />
            </Panel>
        </aside>
    </div>

    <Dialog v-model:open="dialogoAbierto">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Editar destino</DialogTitle>
                <DialogDescription v-if="editando">
                    {{ editando.nombre
                    }}<template v-if="editando.negocio">
                        · {{ editando.negocio.nombre_comercial }}</template
                    >
                </DialogDescription>
            </DialogHeader>
            <div v-if="editando" class="space-y-4">
                <p
                    class="rounded-lg bg-muted/50 px-3 py-2 font-mono text-xs break-all text-muted-foreground"
                >
                    {{ editando.url_permanente }}
                </p>
                <FormularioDestino
                    :producto="editando"
                    :accion="destino.url(editando.id)"
                    :obligatorio="false"
                    compacto
                    @guardado="editandoId = null"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>
