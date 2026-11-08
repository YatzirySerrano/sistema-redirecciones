<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    Building2,
    ChartColumnBig,
    History,
    House,
    LayoutDashboard,
    LayoutList,
    Brush,
    Link2,
    Mail,
    Nfc,
    Paintbrush,
    Sparkles,
    CreditCard,
    HeartHandshake,
    Palette,
    Settings2,
    ShieldCheck,
    UsersRound,
} from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { gruposVisibles } from '@/components/fidelidad/secciones';
import MarcaDasti from '@/components/MarcaDasti.vue';
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
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { guia, inicio } from '@/routes';
import { panel } from '@/routes/admin';
import { index as auditoria } from '@/routes/admin/auditoria';
import { edit as configuracion } from '@/routes/admin/configuracion';
import { index as enlaces } from '@/routes/admin/enlaces';
import { index as estadisticasAdmin } from '@/routes/admin/estadisticas';
import { index as negocios } from '@/routes/admin/negocios';
import { edit as apariencia } from '@/routes/admin/personalizacion';
import { index as disenosClientes } from '@/routes/admin/disenos';
import { index as paginasAdmin } from '@/routes/admin/paginas';
import { index as pagos } from '@/routes/admin/pagos';
import { index as productosAdmin } from '@/routes/admin/productos';
import { index as roles } from '@/routes/admin/roles';
import { index as servicios } from '@/routes/admin/servicios';
import { index as usuarios } from '@/routes/admin/usuarios';
import { inicio as inicioCliente } from '@/routes/cliente';
import { index as estadisticasCliente } from '@/routes/cliente/estadisticas';
import {
    editor as paginaEditor,
    inicio as paginaInicio,
} from '@/routes/cliente/paginas';
import { index as paginaEstadisticas } from '@/routes/cliente/paginas/estadisticas';
import { index as paginaMensajes } from '@/routes/cliente/paginas/mensajes';
import { index as productosCliente } from '@/routes/cliente/productos';
import {
    index as serviciosCliente,
    show as servicioCliente,
} from '@/routes/cliente/servicios';
import { usePermisos } from '@/composables/usePermisos';
import type { NavItem } from '@/types';

const page = usePage();
const permisos = computed(() => page.props.auth.permisos);
const negocio = computed(() => page.props.auth.user.negocio);

const { puede, puedeAlguno } = usePermisos();

const secciones = computed<{ titulo: string; items: NavItem[] }[]>(() => {
    // Cada entrada exige su permiso; las secciones vacías no se muestran.
    // `permiso`: uno o varios (basta con tener alguno).
    const filtrar = (
        items: (NavItem & { permiso: string | string[] })[],
    ): NavItem[] =>
        items.filter((i) =>
            Array.isArray(i.permiso)
                ? puedeAlguno(...i.permiso)
                : puede(i.permiso),
        );

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
                    {
                        title: 'Páginas de enlaces',
                        href: paginasAdmin(),
                        icon: LayoutList,
                        permiso: 'paginas.supervisar',
                    },
                    {
                        title: 'Diseño para clientes',
                        href: disenosClientes(),
                        icon: Brush,
                        permiso: [
                            'fidelidad.disenar_negocios',
                            'paginas.disenar_negocios',
                        ],
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
    const contratados = page.props.servicios;
    const habilitado = (clave: 'fidelidad' | 'pagina_enlaces') =>
        !!contratados?.[clave] &&
        (contratados[clave].activo || contratados[clave].solo_lectura);
    // Servicios sin contratar: se muestran con la indicación «Contratar»
    // y llevan a su presentación comercial (nunca al módulo bloqueado).
    const porContratar: NavItem[] = [];

    if (habilitado('fidelidad')) {
        // Misma organización y nombres que la navegación interna del módulo
        // (components/fidelidad/secciones.ts), en el orden de sus grupos.
        // La contratación vive en «Servicios › Mis servicios».
        secciones.push({
            titulo: 'Fidelidad',
            items: gruposVisibles(puede, true, ['contratacion']).flatMap((g) =>
                g.secciones.map((s) => ({
                    title: s.titulo,
                    href: s.href,
                    icon: s.icono,
                    exact: s.exacta,
                })),
            ),
        });
    } else if (puede('servicios.ver') && puede('servicios.contratar')) {
        porContratar.push({
            title: 'Fidelidad',
            href: servicioCliente('fidelidad'),
            icon: HeartHandshake,
            insignia: 'Contratar',
        });
    }

    if (habilitado('pagina_enlaces')) {
        secciones.push({
            titulo: 'Página de enlaces',
            items: filtrar([
                {
                    title: 'Mi página',
                    href: paginaInicio(),
                    icon: LayoutList,
                    exact: true,
                    permiso: 'paginas.ver',
                },
                {
                    title: 'Editor',
                    href: paginaEditor(),
                    icon: Paintbrush,
                    permiso: 'paginas.editar',
                },
                {
                    title: 'Estadísticas',
                    href: paginaEstadisticas(),
                    icon: ChartColumnBig,
                    permiso: 'paginas.estadisticas',
                },
                {
                    title: 'Mensajes',
                    href: paginaMensajes(),
                    icon: Mail,
                    permiso: 'paginas.mensajes',
                },
            ]),
        });
    } else if (puede('servicios.ver') && puede('servicios.contratar')) {
        porContratar.push({
            title: 'Página de enlaces',
            href: servicioCliente('pagina_enlaces'),
            icon: LayoutList,
            insignia: 'Contratar',
        });
    }

    // «Mis servicios» depende de servicios.ver (el servidor también lo exige).
    if (puede('servicios.ver')) {
        secciones.push({
            titulo: 'Servicios',
            items: [
                {
                    title: 'Mis servicios',
                    href: serviciosCliente(),
                    icon: Sparkles,
                    exact: true,
                },
                ...porContratar,
            ],
        });
    }

    return secciones.filter((s) => s.items.length > 0);
});

// Posición de inicio de cada sección en el menú (entrada escalonada).
const inicios = computed(() => {
    let cuenta = 0;

    return secciones.value.map((s) => {
        const desde = cuenta;
        cuenta += s.items.length;

        return desde;
    });
});

/*
 * Indicador deslizante del elemento activo: una sola píldora que se mueve
 * hasta el botón activo al navegar o al colapsar la barra. Si hay cero o
 * varios activos, cada botón vuelve a pintar su propio fondo.
 */
const { isMobile, setOpenMobile, state } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();
const contenedor = ref<HTMLElement | null>(null);
const indicador = ref<{
    x: number;
    y: number;
    ancho: number;
    alto: number;
} | null>(null);
const sinTransicion = ref(true);

const medir = () => {
    const raiz = contenedor.value;
    const activos = raiz?.querySelectorAll<HTMLElement>(
        '[data-sidebar="menu-button"][data-active="true"]',
    );

    if (!raiz || !activos || activos.length !== 1) {
        indicador.value = null;

        return;
    }

    const a = activos[0].getBoundingClientRect();
    const b = raiz.getBoundingClientRect();

    if (a.width === 0) {
        indicador.value = null;

        return;
    }

    indicador.value = {
        x: a.left - b.left,
        y: a.top - b.top,
        ancho: a.width,
        alto: a.height,
    };

    // La primera colocación es inmediata; las siguientes se deslizan.
    if (sinTransicion.value) {
        requestAnimationFrame(() => (sinTransicion.value = false));
    }
};

const medirTrasRender = () => void nextTick(medir);

onMounted(medirTrasRender);
watch(() => page.url, medirTrasRender);
watch(contenedor, (el) => {
    if (el) {
        sinTransicion.value = true;
        medirTrasRender();
        // En el teléfono los elementos entran con un desplazamiento breve.
        setTimeout(medir, 450);
    }
});
// El ancho cambia durante el colapso (transición de 200 ms).
watch(state, () => setTimeout(medir, 220));
useResizeObserver(contenedor, medir);

// En el teléfono, el menú se cierra al elegir una pantalla.
watch(
    () => page.url,
    () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    },
);

const enGuia = computed(() => isCurrentUrl(guia()));
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="gap-0 pt-4 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="transition-colors duration-200 hover:bg-sidebar-accent/70"
                    >
                        <Link :href="inicio()" aria-label="DASTI · Inicio">
                            <MarcaDasti />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <!-- Contexto: dónde estoy y con qué rol (se oculta al colapsar). -->
            <div
                class="mx-2 mt-3 flex items-center gap-2.5 rounded-lg border border-sidebar-border bg-sidebar-accent/40 px-2.5 py-2 group-data-[collapsible=icon]:hidden"
            >
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-md bg-sidebar-primary/15 text-sidebar-primary"
                    aria-hidden="true"
                >
                    <ShieldCheck v-if="permisos.administrar" class="size-4" />
                    <Building2 v-else class="size-4" />
                </span>
                <span class="min-w-0">
                    <span
                        class="block truncate text-xs font-medium text-sidebar-accent-foreground"
                    >
                        {{
                            permisos.administrar
                                ? 'Administración DASTI'
                                : (negocio?.nombre_comercial ?? 'Mi negocio')
                        }}
                    </span>
                    <span
                        class="block truncate text-[11px] text-sidebar-foreground"
                    >
                        {{
                            page.props.auth.user.rol_nombre ??
                            'Sin rol asignado'
                        }}
                    </span>
                </span>
            </div>
        </SidebarHeader>

        <SidebarContent class="pb-2">
            <div
                ref="contenedor"
                class="relative"
                :data-indicador-listo="indicador ? 'true' : 'false'"
            >
                <!-- Píldora del elemento activo -->
                <div
                    v-if="indicador"
                    class="indicador-navegacion pointer-events-none absolute top-0 left-0 z-0 rounded-md bg-sidebar-primary shadow-[0_6px_18px_-8px_var(--sidebar-primary)]"
                    :class="sinTransicion ? 'transition-none!' : ''"
                    :style="{
                        width: `${indicador.ancho}px`,
                        height: `${indicador.alto}px`,
                        transform: `translate(${indicador.x}px, ${indicador.y}px)`,
                    }"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-y-2 -left-3 w-1 rounded-r-full bg-sidebar-primary group-data-[collapsible=icon]:hidden"
                    />
                </div>
                <NavMain
                    v-for="(seccion, i) in secciones"
                    :key="seccion.titulo"
                    :titulo="seccion.titulo"
                    :items="seccion.items"
                    :desde="inicios[i]"
                />
            </div>
        </SidebarContent>

        <SidebarFooter class="gap-1 border-t border-sidebar-border p-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :is-active="enGuia"
                        tooltip="Guía de uso"
                        class="h-9 text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground"
                    >
                        <Link
                            :href="guia()"
                            :aria-current="enGuia ? 'page' : undefined"
                        >
                            <BookOpenText />
                            <span>Guía de uso</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
    <slot />
</template>
