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
    Palette,
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
import { index as productosAdmin } from '@/routes/admin/productos';
import { index as roles } from '@/routes/admin/roles';
import { index as usuarios } from '@/routes/admin/usuarios';
import { inicio as inicioCliente } from '@/routes/cliente';
import { index as estadisticasCliente } from '@/routes/cliente/estadisticas';
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

    return [{ titulo: negocio.value?.nombre_comercial ?? 'Mi negocio', items }];
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
