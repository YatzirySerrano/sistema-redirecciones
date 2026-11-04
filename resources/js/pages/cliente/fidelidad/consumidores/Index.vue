<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Gift, Pencil, SearchX, UserPlus, UsersRound } from '@lucide/vue';
import AccionesRegistro from '@/components/comun/AccionesRegistro.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    EstadoServicio,
    PuedeFidelidad,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { fechaCorta, numero } from '@/lib/formato';
import { inicio } from '@/routes/cliente/fidelidad';
import {
    create,
    edit,
    index,
    show,
} from '@/routes/cliente/fidelidad/consumidores';
import type { MetaPaginacion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Consumidores', href: index() },
        ],
    },
});

type Fila = {
    id: number;
    nombre: string;
    alias: string | null;
    telefono: string | null;
    email: string | null;
    estado: string;
    visitas_totales: number;
    recompensas_disponibles: number;
    created_at: string | null;
};

const props = defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    consumidores: { data: Fila[]; meta: MetaPaginacion };
    filtros: { buscar: string };
}>();

const { filtros } = useFiltros(index.url(), { ...props.filtros });
</script>

<template>
    <Head title="Consumidores" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        titulo="Consumidores"
        descripcion="Clientes inscritos en tus programas. Sus visitas pertenecen a su cuenta, no a la tarjeta."
        :icono="UsersRound"
    >
        <template #acciones>
            <Button v-if="puede.consumidores" as-child>
                <Link :href="create()"><UserPlus /> Registrar consumidor</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div class="border-b p-4">
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Nombre, alias, correo o últimos dígitos del teléfono…"
                etiqueta="Buscar consumidores"
            />
        </div>

        <ul v-if="consumidores.data.length" class="divide-y">
            <li
                v-for="c in consumidores.data"
                :key="c.id"
                class="flex items-center gap-3 px-4 py-3 sm:px-5"
            >
                <div class="min-w-0 flex-1">
                    <Link
                        :href="show(c.id)"
                        class="block truncate font-medium hover:text-enlace hover:underline"
                        >{{ c.nombre
                        }}<span
                            v-if="c.alias"
                            class="font-normal text-muted-foreground"
                        >
                            · {{ c.alias }}</span
                        ></Link
                    >
                    <p class="truncate text-xs text-muted-foreground">
                        {{
                            [c.telefono, c.email].filter(Boolean).join(' · ') ||
                            'Sin contacto'
                        }}
                        · desde {{ fechaCorta(c.created_at) }}
                    </p>
                </div>
                <div class="hidden text-right text-xs sm:block">
                    <p class="font-medium tabular-nums">
                        {{ numero(c.visitas_totales) }} visitas
                    </p>
                    <p
                        v-if="c.recompensas_disponibles"
                        class="inline-flex items-center gap-1 text-primary"
                    >
                        <Gift class="size-3" />{{ c.recompensas_disponibles }}
                        por entregar
                    </p>
                </div>
                <AccionesRegistro
                    :nombre="c.nombre"
                    :acciones="[
                        {
                            clave: 'ver',
                            etiqueta: 'Ver',
                            icono: Eye,
                            href: show(c.id),
                            principal: true,
                        },
                        {
                            clave: 'editar',
                            etiqueta: 'Editar',
                            icono: Pencil,
                            href: edit(c.id),
                            principal: true,
                            visible: puede.consumidores,
                        },
                    ]"
                />
            </li>
        </ul>
        <EstadoVacio
            v-else-if="filtros.buscar"
            :icono="SearchX"
            titulo="Sin resultados"
            descripcion="Nadie coincide con esa búsqueda."
        />
        <EstadoVacio
            v-else
            :icono="UsersRound"
            titulo="Aún no hay consumidores"
            descripcion="Registra a tus clientes desde el mostrador o desde aquí."
        />

        <Paginacion :meta="consumidores.meta" nombre="consumidores" />
    </section>
</template>
