<script setup lang="ts">
/**
 * Tratamiento tipográfico de la plataforma. No es el logotipo oficial de
 * DASTI (no se ha proporcionado): solo texto, sin figuras ni iniciales
 * encerradas. Usa tokens de color para respetar la personalización.
 */
withDefaults(defineProps<{ invertido?: boolean }>(), { invertido: false });
</script>

<template>
    <div class="grid min-w-0 flex-1 text-left leading-none">
        <span
            class="flex items-baseline gap-1 truncate text-[15px] font-extrabold tracking-[0.24em]"
            :class="
                invertido ? 'text-foreground' : 'text-sidebar-accent-foreground'
            "
        >
            DASTI<span
                class="size-1.5 shrink-0 rounded-full"
                :class="invertido ? 'bg-primary' : 'bg-sidebar-primary'"
                aria-hidden="true"
            />
        </span>
        <span
            class="mt-1 truncate text-[11px] font-medium tracking-wide group-data-[collapsible=icon]:hidden"
            :class="
                invertido ? 'text-muted-foreground' : 'text-sidebar-foreground'
            "
            >Redirecciones QR · NFC</span
        >
    </div>
</template>
