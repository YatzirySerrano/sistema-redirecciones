<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import { Button } from '@/components/ui/button';

/** URL permanente de solo lectura, con copiar y abrir. */
withDefaults(defineProps<{ url: string; compacto?: boolean }>(), {
    compacto: false,
});
</script>

<template>
    <div
        class="flex min-w-0 items-center gap-2 rounded-lg border bg-muted/50 p-1.5 pl-3"
        :class="compacto ? 'max-w-full' : ''"
    >
        <code
            class="min-w-0 flex-1 truncate font-mono text-[13px] text-foreground"
            :title="url"
        >
            {{ url }}
        </code>
        <BotonCopiar
            :texto="url"
            etiqueta="Copiar enlace"
            mensaje="Enlace permanente copiado"
            :solo-icono="compacto"
            variante="ghost"
        />
        <Button variant="ghost" size="icon" as-child>
            <a
                :href="url"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Probar el enlace en una pestaña nueva"
                title="Probar enlace"
            >
                <ExternalLink />
            </a>
        </Button>
    </div>
</template>
