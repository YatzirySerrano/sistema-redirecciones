<script setup lang="ts">
/**
 * Tratamiento tipográfico provisional de la plataforma (no es el logotipo
 * oficial de DASTI, que no se ha proporcionado).
 */
withDefaults(defineProps<{ invertido?: boolean }>(), { invertido: false });
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-[13px] font-bold tracking-tight text-sidebar-primary-foreground"
        aria-hidden="true"
    >
        D
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span
            class="truncate text-sm font-bold tracking-[0.18em]"
            :class="invertido ? 'text-foreground' : 'text-white'"
            >DASTI</span
        >
        <span
            class="truncate text-[11px]"
            :class="
                invertido ? 'text-muted-foreground' : 'text-sidebar-foreground'
            "
            >Redirecciones QR · NFC</span
        >
    </div>
</template>
