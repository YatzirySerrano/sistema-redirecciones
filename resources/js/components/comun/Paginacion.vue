<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { numero } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { MetaPaginacion } from '@/types';

const props = defineProps<{ meta: MetaPaginacion; nombre?: string }>();

const enlaces = computed(() => props.meta.links.slice(1, -1));
const anterior = computed(() => props.meta.links[0]?.url ?? null);
const siguiente = computed(
    () => props.meta.links[props.meta.links.length - 1]?.url ?? null,
);
</script>

<template>
    <nav
        v-if="meta.total > 0"
        class="flex flex-col items-center justify-between gap-3 border-t px-5 py-3 text-sm sm:flex-row"
        aria-label="Paginación"
    >
        <p class="text-muted-foreground">
            Mostrando
            <span class="font-medium text-foreground"
                >{{ numero(meta.from) }}–{{ numero(meta.to) }}</span
            >
            de
            <span class="font-medium text-foreground">{{
                numero(meta.total)
            }}</span>
            {{ nombre ?? 'registros' }}
        </p>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <component
                :is="anterior ? Link : 'span'"
                :href="anterior ?? undefined"
                preserve-scroll
                class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted aria-disabled:pointer-events-none aria-disabled:opacity-40"
                :aria-disabled="!anterior"
                aria-label="Página anterior"
            >
                <ChevronLeft class="size-4" />
            </component>
            <template v-for="(enlace, i) in enlaces" :key="i">
                <span v-if="!enlace.url" class="px-1 text-muted-foreground"
                    >…</span
                >
                <Link
                    v-else
                    :href="enlace.url"
                    preserve-scroll
                    :aria-current="enlace.active ? 'page' : undefined"
                    :class="
                        cn(
                            'hidden h-8 min-w-8 items-center justify-center rounded-md px-2 tabular-nums hover:bg-muted sm:inline-flex',
                            enlace.active &&
                                'bg-primary text-primary-foreground hover:bg-primary',
                        )
                    "
                    >{{ enlace.label }}</Link
                >
            </template>
            <span class="px-2 text-muted-foreground sm:hidden"
                >{{ meta.current_page }} / {{ meta.last_page }}</span
            >
            <component
                :is="siguiente ? Link : 'span'"
                :href="siguiente ?? undefined"
                preserve-scroll
                class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted aria-disabled:pointer-events-none aria-disabled:opacity-40"
                :aria-disabled="!siguiente"
                aria-label="Página siguiente"
            >
                <ChevronRight class="size-4" />
            </component>
        </div>
    </nav>
</template>
