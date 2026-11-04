<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Gift,
    HeartHandshake,
    Repeat,
    ScanLine,
    Sparkles,
    Ticket,
    UsersRound,
} from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    EstadoServicio,
    PuedeFidelidad,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { numero, relativo } from '@/lib/formato';
import { inicio, mostrador } from '@/routes/cliente/fidelidad';
import { show as verConsumidor } from '@/routes/cliente/fidelidad/consumidores';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fidelidad', href: inicio() }] },
});

defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    resumen: {
        consumidores: number;
        visitas_periodo: number;
        dias_periodo: number;
        recurrentes: number;
        recompensas_disponibles: number;
        recompensas_canjeadas_periodo: number;
        proximos_a_meta: {
            consumidor_id: number;
            nombre: string;
            campana: string;
            visitas: number;
            meta: number;
            faltan: number;
        }[];
    };
    canjes_recientes: {
        id: number;
        descripcion: string;
        consumidor: string;
        consumidor_id: number;
        por: string | null;
        en: string | null;
    }[];
}>();
</script>

<template>
    <Head title="Fidelidad" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        titulo="Fidelidad"
        descripcion="Tu tarjeta de sellos digital: visitas confirmadas por tu personal, recompensas y canjes."
        ayuda="Escanear una tarjeta solo identifica al cliente. Las visitas se suman únicamente cuando alguien de tu equipo las confirma en el mostrador."
        :icono="HeartHandshake"
    >
        <template #acciones>
            <Button v-if="puede.visitas || puede.canjear" as-child>
                <Link :href="mostrador()"><ScanLine /> Abrir mostrador</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <TarjetaIndicador
            etiqueta="Consumidores inscritos"
            :valor="numero(resumen.consumidores)"
            :icono="UsersRound"
        />
        <TarjetaIndicador
            etiqueta="Visitas válidas"
            :valor="numero(resumen.visitas_periodo)"
            :detalle="`Últimos ${resumen.dias_periodo} días`"
            :icono="Ticket"
            ayuda="Solo cuenta visitas confirmadas por tu personal; los escaneos no se suman."
        />
        <TarjetaIndicador
            etiqueta="Clientes recurrentes"
            :valor="numero(resumen.recurrentes)"
            :detalle="`Con 2 o más visitas en ${resumen.dias_periodo} días`"
            :icono="Repeat"
        />
        <TarjetaIndicador
            etiqueta="Recompensas disponibles"
            :valor="numero(resumen.recompensas_disponibles)"
            detalle="Ganadas y aún sin entregar"
            :icono="Gift"
        />
        <TarjetaIndicador
            etiqueta="Recompensas canjeadas"
            :valor="numero(resumen.recompensas_canjeadas_periodo)"
            :detalle="`Últimos ${resumen.dias_periodo} días`"
            :icono="Sparkles"
            class="col-span-2 lg:col-span-1"
        />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Cerca de su recompensa"
            descripcion="Consumidores a punto de completar la meta: buena ocasión para invitarlos a volver."
            sin-relleno
        >
            <ul v-if="resumen.proximos_a_meta.length" class="divide-y">
                <li
                    v-for="p in resumen.proximos_a_meta"
                    :key="`${p.consumidor_id}-${p.campana}`"
                    class="flex items-center gap-3 px-5 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="verConsumidor(p.consumidor_id)"
                            class="block truncate font-medium hover:text-enlace hover:underline"
                            >{{ p.nombre }}</Link
                        >
                        <p class="truncate text-xs text-muted-foreground">
                            {{ p.campana }}
                        </p>
                    </div>
                    <div class="w-28 shrink-0 text-right">
                        <p class="text-xs font-medium">
                            {{
                                p.faltan === 0
                                    ? 'Meta cumplida'
                                    : `Faltan ${p.faltan}`
                            }}
                        </p>
                        <div
                            class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="p.visitas"
                            :aria-valuemax="p.meta"
                            :aria-label="`${p.visitas} de ${p.meta} visitas`"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, (p.visitas / p.meta) * 100)}%`,
                                }"
                            />
                        </div>
                    </div>
                </li>
            </ul>
            <EstadoVacio
                v-else
                :icono="Gift"
                titulo="Nadie está cerca de la meta"
                descripcion="Aquí aparecerán quienes estén a una o dos visitas de su recompensa."
            />
        </Panel>

        <Panel titulo="Canjes recientes" sin-relleno>
            <ul v-if="canjes_recientes.length" class="divide-y">
                <li
                    v-for="c in canjes_recientes"
                    :key="c.id"
                    class="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ c.descripcion }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            <Link
                                :href="verConsumidor(c.consumidor_id)"
                                class="hover:underline"
                                >{{ c.consumidor }}</Link
                            >
                            <template v-if="c.por">
                                · entregó {{ c.por }}</template
                            >
                        </p>
                    </div>
                    <span class="shrink-0 text-xs text-muted-foreground">{{
                        relativo(c.en)
                    }}</span>
                </li>
            </ul>
            <EstadoVacio
                v-else
                :icono="Sparkles"
                titulo="Aún no hay canjes"
                descripcion="Cuando tu personal entregue recompensas aparecerán aquí."
            />
        </Panel>
    </div>
</template>
