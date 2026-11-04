<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CirclePause, CirclePlay, Gift, Pencil, Plus } from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    EstadoServicio,
    PuedeFidelidad,
    ReglasCampana,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { fechaCorta, numero } from '@/lib/formato';
import { inicio } from '@/routes/cliente/fidelidad';
import {
    create,
    edit,
    estado,
    index,
} from '@/routes/cliente/fidelidad/campanas';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Campañas', href: index() },
        ],
    },
});

defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    campanas: {
        id: number;
        nombre: string;
        estado: string;
        estado_etiqueta: string;
        version: number;
        participantes: number;
        reglas: ReglasCampana | null;
    }[];
}>();

const cambiarEstado = (id: number, valor: string) =>
    router.patch(estado.url(id), { estado: valor }, { preserveScroll: true });
</script>

<template>
    <Head title="Campañas" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        titulo="Campañas"
        descripcion="Reglas de tus programas: cuántas visitas se necesitan y qué recompensa se entrega."
        ayuda="Si cambias las reglas se crea una versión nueva: quienes ya participan conservan las condiciones con las que se inscribieron."
        :icono="Gift"
    >
        <template #acciones>
            <Button v-if="puede.campanas" as-child>
                <Link :href="create()"><Plus /> Nueva campaña</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <div
        v-if="campanas.length"
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
    >
        <article
            v-for="c in campanas"
            :key="c.id"
            class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate font-semibold">{{ c.nombre }}</h2>
                    <p class="text-xs text-muted-foreground">
                        Versión {{ c.version }} ·
                        {{ numero(c.participantes) }} participantes
                    </p>
                </div>
                <InsigniaEstado
                    :estado="
                        c.estado === 'activa'
                            ? 'activo'
                            : c.estado === 'pausada'
                              ? 'pendiente'
                              : 'suspendido'
                    "
                    :etiqueta="c.estado_etiqueta"
                />
            </div>
            <p
                v-if="c.reglas"
                class="mt-4 text-2xl font-semibold tracking-tight"
            >
                {{ c.reglas.meta_visitas }} visitas
                <span class="block text-base font-medium text-primary"
                    >= {{ c.reglas.recompensa }}</span
                >
            </p>
            <dl v-if="c.reglas" class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-lg bg-muted/40 p-2">
                    <dt class="text-muted-foreground">Vigencia</dt>
                    <dd class="font-medium">
                        {{ fechaCorta(c.reglas.inicia_en) }} –
                        {{
                            c.reglas.termina_en
                                ? fechaCorta(c.reglas.termina_en)
                                : 'sin fin'
                        }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-2">
                    <dt class="text-muted-foreground">Límite diario</dt>
                    <dd class="font-medium">
                        {{ c.reglas.max_visitas_por_dia }} por cliente
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-2">
                    <dt class="text-muted-foreground">Recompensa vence</dt>
                    <dd class="font-medium">
                        {{
                            c.reglas.vigencia_recompensa_dias
                                ? `${c.reglas.vigencia_recompensa_dias} días`
                                : 'No vence'
                        }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-2">
                    <dt class="text-muted-foreground">Ciclo</dt>
                    <dd class="font-medium">
                        {{
                            c.reglas.repetir_ciclo
                                ? 'Se repite'
                                : 'Una sola vez'
                        }}
                    </dd>
                </div>
            </dl>
            <div
                v-if="puede.campanas"
                class="mt-4 flex flex-wrap gap-1 border-t pt-3"
            >
                <Button variant="ghost" size="sm" as-child
                    ><Link :href="edit(c.id)"><Pencil /> Editar</Link></Button
                >
                <Button
                    v-if="c.estado === 'activa'"
                    variant="ghost"
                    size="sm"
                    @click="cambiarEstado(c.id, 'pausada')"
                    ><CirclePause /> Pausar</Button
                >
                <Button
                    v-else-if="c.estado === 'pausada'"
                    variant="ghost"
                    size="sm"
                    @click="cambiarEstado(c.id, 'activa')"
                    ><CirclePlay /> Reanudar</Button
                >
            </div>
        </article>
    </div>
    <EstadoVacio
        v-else
        class="rounded-xl border bg-card"
        :icono="Gift"
        titulo="Aún no hay campañas"
        descripcion="Ejemplo: «Acumula 10 visitas y recibe un masaje gratis»."
    >
        <Button v-if="puede.campanas" as-child
            ><Link :href="create()"><Plus /> Crear la primera</Link></Button
        >
    </EstadoVacio>
</template>
