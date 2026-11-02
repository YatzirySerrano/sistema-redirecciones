<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    CreditCard,
    Gift,
    LayoutDashboard,
    ScanLine,
    UserCog,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermisos } from '@/composables/usePermisos';
import {
    actividad,
    contratacion,
    inicio,
    mostrador,
} from '@/routes/cliente/fidelidad';
import { index as campanas } from '@/routes/cliente/fidelidad/campanas';
import { index as consumidores } from '@/routes/cliente/fidelidad/consumidores';
import { index as personal } from '@/routes/cliente/fidelidad/personal';
import type { EstadoServicio } from './tipos';

/**
 * Navegación interna del módulo Fidelidad (pestañas desplazables en móvil)
 * y aviso cuando el servicio está en solo lectura.
 */
const props = defineProps<{ servicio?: EstadoServicio }>();

const { puede } = usePermisos();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const secciones = computed(() =>
    [
        {
            titulo: 'Resumen',
            href: inicio(),
            icono: LayoutDashboard,
            exacta: true,
            permiso: 'fidelidad.ver',
        },
        {
            titulo: 'Mostrador',
            href: mostrador(),
            icono: ScanLine,
            permiso: 'fidelidad.ver',
        },
        {
            titulo: 'Consumidores',
            href: consumidores(),
            icono: UsersRound,
            permiso: 'fidelidad.ver',
        },
        {
            titulo: 'Campañas',
            href: campanas(),
            icono: Gift,
            permiso: 'fidelidad.ver',
        },
        {
            titulo: 'Personal',
            href: personal(),
            icono: UserCog,
            permiso: 'fidelidad.personal',
        },
        {
            titulo: 'Actividad',
            href: actividad(),
            icono: Activity,
            permiso: 'fidelidad.auditoria',
        },
        {
            titulo: 'Contratación',
            href: contratacion(),
            icono: CreditCard,
            permiso: 'servicios.contratar',
        },
    ]
        .filter((s) => puede(s.permiso))
        // Sin servicio contratado solo tiene sentido la contratación.
        .filter(
            (s) =>
                s.titulo === 'Contratación' ||
                !props.servicio ||
                props.servicio.activo ||
                props.servicio.solo_lectura,
        ),
);

const activa = (s: {
    href: NonNullable<InertiaLinkProps['href']>;
    exacta?: boolean;
}) => (s.exacta ? isCurrentUrl(s.href) : isCurrentOrParentUrl(s.href));
</script>

<template>
    <div class="space-y-3">
        <nav
            aria-label="Secciones de Fidelidad"
            class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1"
        >
            <Link
                v-for="s in secciones"
                :key="s.titulo"
                :href="s.href"
                :aria-current="activa(s) ? 'page' : undefined"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground aria-[current=page]:bg-primary/10 aria-[current=page]:text-primary"
            >
                <component :is="s.icono" class="size-4" aria-hidden="true" />
                {{ s.titulo }}
            </Link>
        </nav>
        <p
            v-if="props.servicio && props.servicio.solo_lectura"
            class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-sm text-warning"
            role="status"
        >
            Fidelidad está en <strong>solo lectura</strong>
            {{
                props.servicio.estado === 'vencida'
                    ? 'porque la contratación venció.'
                    : 'mientras DASTI revisa un pago.'
            }}
            Puedes consultar tu información, pero no registrar visitas ni
            canjes.
        </p>
    </div>
</template>
