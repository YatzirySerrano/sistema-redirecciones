<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
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
                    :tooltip="item.title"
                    class="h-9 text-sidebar-foreground hover:bg-sidebar-accent hover:text-white data-[active=true]:bg-sidebar-primary data-[active=true]:font-medium data-[active=true]:text-sidebar-primary-foreground"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
