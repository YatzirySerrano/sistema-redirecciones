<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChartColumnBig,
    History,
    House,
    LayoutDashboard,
    Link2,
    Nfc,
    CreditCard,
    Gift,
    HeartHandshake,
    Palette,
    Stamp,
    UserCog,
    Settings2,
    ShieldCheck,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { inicio } from '@/routes';
import { panel } from '@/routes/admin';
import { index as auditoria } from '@/routes/admin/auditoria';
import { edit as configuracion } from '@/routes/admin/configuracion';
import { index as enlaces } from '@/routes/admin/enlaces';
import { index as estadisticasAdmin } from '@/routes/admin/estadisticas';
import { index as negocios } from '@/routes/admin/negocios';
import { edit as apariencia } from '@/routes/admin/personalizacion';
import { index as pagos } from '@/routes/admin/pagos';
import { index as productosAdmin } from '@/routes/admin/productos';
import { index as roles } from '@/routes/admin/roles';
import { index as servicios } from '@/routes/admin/servicios';
import { index as usuarios } from '@/routes/admin/usuarios';
import { inicio as inicioCliente } from '@/routes/cliente';
import { index as estadisticasCliente } from '@/routes/cliente/estadisticas';
import {
    contratacion,
    inicio as fidelidadInicio,
    mostrador,
} from '@/routes/cliente/fidelidad';
import { index as campanas } from '@/routes/cliente/fidelidad/campanas';
import { index as consumidores } from '@/routes/cliente/fidelidad/consumidores';
import { index as personal } from '@/routes/cliente/fidelidad/personal';
import { index as productosCliente } from '@/routes/cliente/productos';
import { usePermisos } from '@/composables/usePermisos';
import type { NavItem } from '@/types';

const page = usePage();
const permisos = computed(() => page.props.auth.permisos);
const negocio = computed(() => page.props.auth.user.negocio);

const { puede } = usePermisos();

const secciones = computed<{ titulo: string; items: NavItem[] }[]>(() => {
    // Cada entrada exige su permiso; las secciones vacías no se muestran.
    const filtrar = (items: (NavItem & { permiso: string })[]): NavItem[] =>
        items.filter((i) => puede(i.permiso));

    if (permisos.value.administrar) {
        return [
            {
                titulo: 'General',
                items: filtrar([
                    {
                        title: 'Panel',
                        href: panel(),
                        icon: LayoutDashboard,
                        exact: true,
                        permiso: 'panel.ver',
                    },
                    {
                        title: 'Estadísticas',
                        href: estadisticasAdmin(),
                        icon: ChartColumnBig,
                        permiso: 'estadisticas.ver',
                    },
                ]),
            },
            {
                titulo: 'Gestión',
                items: filtrar([
                    {
                        title: 'Negocios',
                        href: negocios(),
                        icon: Building2,
                        permiso: 'negocios.ver',
                    },
                    {
                        title: 'Productos',
                        href: productosAdmin(),
                        icon: Nfc,
                        permiso: 'productos.ver',
                    },
                    {
                        title: 'Redirecciones',
                        href: enlaces(),
                        icon: Link2,
                        permiso: 'redirecciones.ver',
                    },
                ]),
            },
            {
                titulo: 'Servicios',
                items: filtrar([
                    {
                        title: 'Contrataciones',
                        href: servicios(),
                        icon: HeartHandshake,
                        permiso: 'servicios.ver',
                    },
                    {
                        title: 'Pagos',
                        href: pagos(),
                        icon: CreditCard,
                        permiso: 'pagos.ver',
                    },
                ]),
            },
            {
                titulo: 'Administración',
                items: filtrar([
                    {
                        title: 'Usuarios y accesos',
                        href: usuarios(),
                        icon: UsersRound,
                        permiso: 'usuarios.ver',
                    },
                    {
                        title: 'Roles y permisos',
                        href: roles(),
                        icon: ShieldCheck,
                        permiso: 'roles.ver',
                    },
                    {
                        title: 'Auditoría',
                        href: auditoria(),
                        icon: History,
                        permiso: 'auditoria.ver',
                    },
                    {
                        title: 'Configuración',
                        href: configuracion(),
                        icon: Settings2,
                        permiso: 'configuracion.ver',
                    },
                    {
                        title: 'Apariencia',
                        href: apariencia(),
                        icon: Palette,
                        permiso: 'personalizacion.ver',
                    },
                ]),
            },
        ].filter((s) => s.items.length > 0);
    }

    const items = filtrar([
        {
            title: 'Inicio',
            href: inicioCliente(),
            icon: House,
            exact: true,
            permiso: 'panel.ver',
        },
        {
            title: 'Mis productos',
            href: productosCliente(),
            icon: Nfc,
            permiso: 'productos.ver',
        },
    ]);

    if (permisos.value.ver_estadisticas) {
        items.push({
            title: 'Estadísticas',
            href: estadisticasCliente(),
            icon: ChartColumnBig,
        });
    }

    const secciones = [{ titulo: 'Redirecciones', items }];
    const fidelidad = page.props.servicios?.fidelidad;

    if (fidelidad && (fidelidad.activo || fidelidad.solo_lectura)) {
        secciones.push({
            titulo: 'Fidelidad',
            items: filtrar([
                {
                    title: 'Resumen',
                    href: fidelidadInicio(),
                    icon: HeartHandshake,
                    exact: true,
                    permiso: 'fidelidad.ver',
                },
                {
                    title: 'Registrar visita',
                    href: mostrador(),
                    icon: Stamp,
                    permiso: 'fidelidad.visitas',
                },
                {
                    title: 'Consumidores',
                    href: consumidores(),
                    icon: UsersRound,
                    permiso: 'fidelidad.consumidores',
                },
                {
                    title: 'Campañas',
                    href: campanas(),
                    icon: Gift,
                    permiso: 'fidelidad.campanas',
                },
                {
                    title: 'Personal',
                    href: personal(),
                    icon: UserCog,
                    permiso: 'fidelidad.personal',
                },
            ]),
        });
    } else if (puede('servicios.contratar')) {
        secciones.push({
            titulo: 'Servicios',
            items: [
                {
                    title: 'Contratar Fidelidad',
                    href: contratacion(),
                    icon: HeartHandshake,
                },
            ],
        });
    }

    return secciones.filter((s) => s.items.length > 0);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-sidebar-accent"
                    >
                        <Link :href="inicio()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <!-- Contexto: dónde estoy y con qué rol (se oculta al colapsar). -->
            <div
                class="mx-2 mt-2 rounded-lg border border-sidebar-border bg-sidebar-accent/50 px-3 py-2 group-data-[collapsible=icon]:hidden"
            >
                <p
                    class="truncate text-xs font-medium text-sidebar-accent-foreground"
                >
                    {{
                        permisos.administrar
                            ? 'Administración DASTI'
                            : (negocio?.nombre_comercial ?? 'Mi negocio')
                    }}
                </p>
                <p class="truncate text-[11px] text-sidebar-foreground">
                    {{ page.props.auth.user.rol_nombre ?? 'Sin rol asignado' }}
                </p>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="seccion in secciones"
                :key="seccion.titulo"
                :titulo="seccion.titulo"
                :items="seccion.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
