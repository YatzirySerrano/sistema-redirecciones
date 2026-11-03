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

defineProps<{
    titulo: string;
    items: NavItem[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const activo = (item: NavItem) =>
    item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);
</script>

<template>
    <SidebarGroup class="px-3 py-1">
        <SidebarGroupLabel
            class="text-[11px] font-semibold tracking-wider text-sidebar-foreground/55 uppercase"
            >{{ titulo }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="activo(item)"
                    :tooltip="
                        item.insignia
                            ? `${item.title} · ${item.insignia}`
                            : item.title
                    "
                    :class="item.insignia ? 'pr-20' : ''"
                    class="h-9 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-medium data-[active=true]:text-sidebar-primary-foreground"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    v-if="item.insignia"
                    class="rounded-full bg-sidebar-primary/20 px-1.5 text-[10px] font-semibold text-sidebar-primary-foreground"
                    >{{ item.insignia }}</SidebarMenuBadge
                >
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
