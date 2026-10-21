<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-w-0 overflow-x-clip bg-background"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <main
                id="contenido"
                class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
            >
                <div class="mx-auto w-full max-w-7xl space-y-6">
                    <slot />
                </div>
            </main>
        </AppContent>
        <Toaster rich-colors close-button position="top-right" />
    </AppShell>
</template>
