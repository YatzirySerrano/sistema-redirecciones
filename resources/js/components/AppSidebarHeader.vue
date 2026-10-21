<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Building2, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const usuario = computed(() => page.props.auth.user);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background/85 px-4 backdrop-blur sm:px-6 lg:px-8"
    >
        <a
            href="#contenido"
            class="sr-only rounded-md bg-primary px-3 py-1 text-primary-foreground focus:not-sr-only"
            >Saltar al contenido</a
        >
        <SidebarTrigger
            class="-ml-1 text-muted-foreground"
            aria-label="Mostrar u ocultar menú"
        />
        <div class="min-w-0 flex-1">
            <Breadcrumbs
                v-if="breadcrumbs.length > 0"
                :breadcrumbs="breadcrumbs"
            />
        </div>
        <span
            v-if="usuario.rol === 'admin'"
            class="hidden items-center gap-1.5 rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground sm:inline-flex"
        >
            <ShieldCheck class="size-3.5" /> Administración DASTI
        </span>
        <span
            v-else-if="usuario.negocio"
            class="hidden max-w-60 items-center gap-1.5 truncate rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground sm:inline-flex"
        >
            <Building2 class="size-3.5 shrink-0" />
            <span class="truncate">{{ usuario.negocio.nombre_comercial }}</span>
        </span>
    </header>
</template>
