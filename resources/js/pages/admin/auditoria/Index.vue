<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FilterX } from '@lucide/vue';
import { computed } from 'vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { index as auditoriaIndex } from '@/routes/admin/auditoria';
import {
    negocios as opcionesNegocios,
    usuarios as opcionesUsuarios,
} from '@/routes/admin/opciones';
import type { Opcion, Paginado, RegistroAuditoria } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Auditoría', href: auditoriaIndex() }],
    },
});

const props = defineProps<{
    registros: Paginado<RegistroAuditoria>;
    filtros: {
        buscar: string;
        accion: string;
        negocio_id: string;
        usuario_id: string;
        desde: string;
        hasta: string;
    };
    acciones: Opcion[];
    negocio_filtro: Opcion | null;
    usuario_filtro: Opcion | null;
}>();

const { filtros, limpiar } = useFiltros(auditoriaIndex.url(), {
    ...props.filtros,
});

const hayFiltros = computed(() => Object.values(filtros).some((v) => v !== ''));
</script>

<template>
    <Head title="Auditoría" />

    <EncabezadoPagina
        titulo="Actividad y auditoría"
        descripcion="Historial de las operaciones sensibles: altas, cambios de destino, suspensiones, transferencias y accesos."
    />

    <div class="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
        <div
            class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Buscar en la descripción…"
                etiqueta="Buscar en la descripción"
            />
            <SelectorBuscable
                v-model="filtros.accion"
                :opciones="acciones"
                vacio="Todas las acciones"
                etiqueta="Filtrar por acción"
                placeholder-busqueda="Buscar acción…"
                class="sm:w-56"
            />
            <SelectorBuscable
                v-model="filtros.negocio_id"
                :url="opcionesNegocios.url()"
                :seleccion-inicial="negocio_filtro"
                vacio="Todos los negocios"
                etiqueta="Filtrar por negocio"
                placeholder-busqueda="Buscar negocio…"
                class="sm:w-52"
            />
            <SelectorBuscable
                v-model="filtros.usuario_id"
                :url="opcionesUsuarios.url()"
                :seleccion-inicial="usuario_filtro"
                vacio="Todos los usuarios"
                etiqueta="Filtrar por usuario"
                placeholder-busqueda="Nombre o correo…"
                class="sm:w-60"
            />
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <label for="auditoria-desde" class="text-muted-foreground"
                >Desde</label
            >
            <input
                id="auditoria-desde"
                v-model="filtros.desde"
                type="date"
                :max="filtros.hasta || undefined"
                class="h-8 rounded-lg border border-input bg-background px-2"
            />
            <label for="auditoria-hasta" class="text-muted-foreground"
                >hasta</label
            >
            <input
                id="auditoria-hasta"
                v-model="filtros.hasta"
                type="date"
                :min="filtros.desde || undefined"
                class="h-8 rounded-lg border border-input bg-background px-2"
            />
            <Button
                v-if="hayFiltros"
                variant="ghost"
                size="sm"
                class="ml-auto"
                @click="limpiar"
            >
                <FilterX /> Limpiar filtros
            </Button>
        </div>
    </div>

    <Panel sin-relleno>
        <div class="p-5">
            <ListaAuditoria :registros="registros.data" mostrar-negocio />
        </div>
        <Paginacion :meta="registros.meta" nombre="registros" />
    </Panel>
</template>
