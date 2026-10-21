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
    Settings2,
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
import { index as productosAdmin } from '@/routes/admin/productos';
import { index as usuarios } from '@/routes/admin/usuarios';
import { inicio as inicioCliente } from '@/routes/cliente';
import { index as estadisticasCliente } from '@/routes/cliente/estadisticas';
import { index as productosCliente } from '@/routes/cliente/productos';
import type { NavItem } from '@/types';

const page = usePage();
const permisos = computed(() => page.props.auth.permisos);
const negocio = computed(() => page.props.auth.user.negocio);

const secciones = computed<{ titulo: string; items: NavItem[] }[]>(() => {
    if (permisos.value.administrar) {
        return [
            {
                titulo: 'General',
                items: [
                    {
                        title: 'Panel',
                        href: panel(),
                        icon: LayoutDashboard,
                        exact: true,
                    },
                    {
                        title: 'Estadísticas',
                        href: estadisticasAdmin(),
                        icon: ChartColumnBig,
                    },
                ],
            },
            {
                titulo: 'Gestión',
                items: [
                    { title: 'Negocios', href: negocios(), icon: Building2 },
                    { title: 'Productos', href: productosAdmin(), icon: Nfc },
                    { title: 'Redirecciones', href: enlaces(), icon: Link2 },
                ],
            },
            {
                titulo: 'Administración',
                items: [
                    {
                        title: 'Usuarios y accesos',
                        href: usuarios(),
                        icon: UsersRound,
                    },
                    { title: 'Auditoría', href: auditoria(), icon: History },
                    {
                        title: 'Configuración',
                        href: configuracion(),
                        icon: Settings2,
                    },
                ],
            },
        ];
    }

    const items: NavItem[] = [
        { title: 'Inicio', href: inicioCliente(), icon: House, exact: true },
        { title: 'Mis productos', href: productosCliente(), icon: Nfc },
    ];

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
