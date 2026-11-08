<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

/**
 * Una sección de la barra lateral: encabezado y sus elementos.
 * El fondo del elemento activo lo dibuja el indicador deslizante de
 * AppSidebar; si no está disponible, el propio botón pinta su fondo.
 */
withDefaults(
    defineProps<{
        titulo: string;
        items: NavItem[];
        /** Posición del primer elemento en todo el menú (entrada escalonada). */
        desde?: number;
    }>(),
    { desde: 0 },
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const activo = (item: NavItem) =>
    item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);
</script>

<template>
    <SidebarGroup class="px-3 py-1.5">
        <SidebarGroupLabel
            class="h-7 px-2 text-[11px] font-semibold tracking-wide text-sidebar-foreground/50"
            >{{ titulo }}</SidebarGroupLabel
        >
        <!-- Al colapsar, una línea fina separa las secciones. -->
        <div
            class="mx-auto mb-1 hidden h-px w-5 bg-sidebar-border group-data-[collapsible=icon]:block"
            aria-hidden="true"
        />
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem
                v-for="(item, i) in items"
                :key="item.title"
                :style="{ '--orden': desde + i }"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="activo(item)"
                    :tooltip="
                        item.insignia
                            ? `${item.title} · ${item.insignia}`
                            : item.title
                    "
                    :class="item.insignia ? 'pr-20' : ''"
                    class="relative h-9 text-sidebar-foreground transition-[color,background-color] duration-150 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-medium data-[active=true]:text-sidebar-primary-foreground"
                >
                    <Link
                        :href="item.href"
                        :aria-current="activo(item) ? 'page' : undefined"
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    v-if="item.insignia"
                    class="rounded-full bg-sidebar-primary/20 px-1.5 text-[10px] font-semibold text-sidebar-primary-foreground ring-1 ring-sidebar-primary/30"
                    >{{ item.insignia }}</SidebarMenuBadge
                >
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
