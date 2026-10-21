<script setup lang="ts">
import { Search, X } from '@lucide/vue';

const modelo = defineModel<string>({ required: true });
withDefaults(defineProps<{ placeholder?: string; etiqueta?: string }>(), {
    placeholder: 'Buscar…',
    etiqueta: 'Buscar',
});
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <input
            v-model="modelo"
            type="search"
            :aria-label="etiqueta"
            :placeholder="placeholder"
            class="h-9 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 [&::-webkit-search-cancel-button]:hidden"
        />
        <button
            v-if="modelo"
            type="button"
            class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
            aria-label="Limpiar búsqueda"
            @click="modelo = ''"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
