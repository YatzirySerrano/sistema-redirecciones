<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    campo: string;
    orden: string;
    direccion: string;
}>();

const emit = defineEmits<{ ordenar: [campo: string] }>();

const activo = computed(() => props.orden === props.campo);
const ariaSort = computed(() =>
    activo.value
        ? props.direccion === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none',
);
</script>

<template>
    <th
        scope="col"
        :aria-sort="ariaSort"
        class="h-10 px-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground"
    >
        <button
            type="button"
            class="-ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
            @click="emit('ordenar', campo)"
        >
            <slot />
            <ArrowUp
                v-if="activo && direccion === 'asc'"
                class="size-3.5"
                aria-hidden="true"
            />
            <ArrowDown v-else-if="activo" class="size-3.5" aria-hidden="true" />
            <ArrowUpDown
                v-else
                class="size-3.5 opacity-40"
                aria-hidden="true"
            />
        </button>
    </th>
</template>
