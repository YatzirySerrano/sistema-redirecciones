<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Activity } from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type { EstadoServicio } from '@/components/fidelidad/tipos';
import { fechaHora, numero, relativo } from '@/lib/formato';
import { actividad, inicio } from '@/routes/cliente/fidelidad';
import { show as verConsumidor } from '@/routes/cliente/fidelidad/consumidores';
import type { Coleccion, RegistroAuditoria } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Actividad', href: actividad() },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    dias: number;
    por_empleado: {
        nombre: string;
        visitas: number;
        ajustes: number;
        anulaciones: number;
        consumidores: number;
        ultima: string | null;
    }[];
    canjes_por_empleado: { nombre: string; total: number }[];
    ultimos: {
        id: number;
        tipo: string;
        tipo_etiqueta: string;
        cantidad: number;
        campana: string;
        consumidor_id: number;
        motivo: string | null;
        por: string | null;
        created_at: string;
    }[];
    auditoria: Coleccion<RegistroAuditoria>;
}>();

const cambiarPeriodo = (v: string) =>
    router.get(
        actividad.url(),
        { dias: v },
        { preserveScroll: true, preserveState: true },
    );
const canjesDe = (nombre: string) =>
    props.canjes_por_empleado.find((c) => c.nombre === nombre)?.total ?? 0;
</script>

<template>
    <Head title="Actividad de Fidelidad" />

    <EncabezadoPagina
        titulo="Actividad"
        descripcion="Qué registró cada persona de tu equipo. Útil para detectar visitas o canjes indebidos."
        ayuda="Ninguna herramienta evita por completo el fraude interno. Compara visitas por empleado con tus ventas y revisa ajustes y anulaciones."
        :icono="Activity"
    >
        <template #acciones>
            <SelectNativo
                :model-value="String(dias)"
                :opciones="[
                    { value: '7', label: 'Últimos 7 días' },
                    { value: '30', label: 'Últimos 30 días' },
                    { value: '90', label: 'Últimos 90 días' },
                ]"
                etiqueta="Periodo"
                class="w-44"
                @update:model-value="cambiarPeriodo"
            />
        </template>
    </EncabezadoPagina>

    <NavFidelidad :servicio="servicio" />

    <Panel titulo="Por empleado" sin-relleno>
        <div v-if="por_empleado.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/40 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="h-9 px-5 font-medium">Persona</th>
                        <th class="h-9 px-3 text-right font-medium">Visitas</th>
                        <th class="h-9 px-3 text-right font-medium">
                            Clientes
                        </th>
                        <th class="h-9 px-3 text-right font-medium">Canjes</th>
                        <th class="h-9 px-3 text-right font-medium">Ajustes</th>
                        <th class="h-9 px-3 text-right font-medium">
                            Anulaciones
                        </th>
                        <th class="hidden h-9 px-5 font-medium sm:table-cell">
                            Última
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in por_empleado"
                        :key="e.nombre"
                        class="border-t"
                    >
                        <td class="px-5 py-2.5 font-medium">{{ e.nombre }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ numero(e.visitas) }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ numero(e.consumidores) }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ numero(canjesDe(e.nombre)) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right tabular-nums"
                            :class="e.ajustes > 0 && 'text-warning'"
                        >
                            {{ numero(e.ajustes) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right tabular-nums"
                            :class="e.anulaciones > 0 && 'text-warning'"
                        >
                            {{ numero(e.anulaciones) }}
                        </td>
                        <td
                            class="hidden px-5 py-2.5 text-muted-foreground sm:table-cell"
                        >
                            {{ relativo(e.ultima) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EstadoVacio
            v-else
            :icono="Activity"
            titulo="Sin actividad en el periodo"
        />
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel titulo="Últimos movimientos" sin-relleno>
            <ul v-if="ultimos.length" class="divide-y">
                <li
                    v-for="m in ultimos"
                    :key="m.id"
                    class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate">
                            {{ m.tipo_etiqueta }}
                            <span
                                class="tabular-nums"
                                :class="
                                    m.cantidad < 0
                                        ? 'text-destructive'
                                        : 'text-success'
                                "
                                >{{ m.cantidad > 0 ? '+' : ''
                                }}{{ m.cantidad }}</span
                            >
                            · {{ m.campana }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <Link
                                :href="verConsumidor(m.consumidor_id)"
                                class="hover:underline"
                                >Ver cliente</Link
                            >
                            · {{ m.por ?? 'Sistema'
                            }}<template v-if="m.motivo">
                                · {{ m.motivo }}</template
                            >
                        </p>
                    </div>
                    <time
                        class="shrink-0 text-xs text-muted-foreground"
                        :datetime="m.created_at"
                        :title="fechaHora(m.created_at)"
                        >{{ relativo(m.created_at) }}</time
                    >
                </li>
            </ul>
            <EstadoVacio v-else :icono="Activity" titulo="Sin movimientos" />
        </Panel>
        <Panel titulo="Auditoría del programa">
            <ListaAuditoria :registros="auditoria.data" />
        </Panel>
    </div>
</template>
