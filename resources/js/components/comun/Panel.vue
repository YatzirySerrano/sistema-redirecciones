<script setup lang="ts">
/** Tarjeta blanca de sección con título opcional y acciones. */
defineProps<{
    titulo?: string;
    descripcion?: string;
    sinRelleno?: boolean;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs"
    >
        <header
            v-if="titulo || $slots.acciones"
            class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4"
        >
            <div class="min-w-0">
                <h2 class="text-sm font-semibold">{{ titulo }}</h2>
                <p
                    v-if="descripcion"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ descripcion }}
                </p>
            </div>
            <div v-if="$slots.acciones" class="flex items-center gap-2">
                <slot name="acciones" />
            </div>
        </header>
        <div :class="sinRelleno ? '' : 'p-5'">
            <slot />
        </div>
    </section>
</template>
