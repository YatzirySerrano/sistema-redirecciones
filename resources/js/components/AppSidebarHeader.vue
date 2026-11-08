<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, CircleHelp, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { temaSegunUrl } from '@/components/guia/contenido';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { guia } from '@/routes';
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

// Ayuda contextual: abre la guía en el tema de la pantalla actual.
const enlaceAyuda = computed(() => {
    const tema = temaSegunUrl(page.url, page.props.auth.permisos.administrar);

    return tema ? `${guia.url()}#${tema}` : guia.url();
});
const enGuia = computed(() => page.url.startsWith(guia.url()));
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
        <Tooltip v-if="!enGuia">
            <TooltipTrigger as-child>
                <Link
                    :href="enlaceAyuda"
                    class="group inline-flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors duration-150 hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    aria-label="Ayuda: abrir la guía de uso de esta pantalla"
                >
                    <CircleHelp
                        class="size-[18px] transition-transform duration-300 ease-out group-hover:rotate-12"
                        aria-hidden="true"
                    />
                </Link>
            </TooltipTrigger>
            <TooltipContent side="bottom"
                >Ayuda de esta pantalla</TooltipContent
            >
        </Tooltip>
    </header>
</template>
