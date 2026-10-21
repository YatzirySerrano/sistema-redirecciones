<script setup lang="ts">
import { Nfc } from '@lucide/vue';
import EnlacePermanente from '@/components/comun/EnlacePermanente.vue';

/**
 * Guía de grabación con NFC Tools. La aplicación no programa ni verifica
 * físicamente las etiquetas: solo indica la URL exacta que debe grabarse.
 */
defineProps<{ url: string }>();
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start gap-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                aria-hidden="true"
            >
                <Nfc class="size-5" />
            </span>
            <div class="text-sm">
                <p class="font-medium">URL que se graba en la tarjeta</p>
                <p class="text-muted-foreground">
                    Siempre la permanente; nunca el destino final.
                </p>
            </div>
        </div>
        <EnlacePermanente :url="url" />
        <ol
            class="list-decimal space-y-1.5 pl-5 text-sm text-muted-foreground marker:font-semibold marker:text-foreground"
        >
            <li>
                Abre <strong class="text-foreground">NFC Tools</strong> en el
                teléfono y entra a <em>Escribir</em>.
            </li>
            <li>
                Elige <em>Agregar un registro</em> → <em>URL / URI</em> y pega
                la URL permanente copiada.
            </li>
            <li>
                Toca <em>Escribir</em> y acerca la tarjeta hasta que confirme la
                grabación.
            </li>
            <li>
                Opcional: en <em>Otros</em> → <em>Bloquear etiqueta</em> para
                evitar que se reescriba (es irreversible).
            </li>
            <li>
                Acerca un teléfono a la tarjeta para comprobar que abre el
                destino configurado.
            </li>
        </ol>
        <p class="text-xs text-muted-foreground">
            El estado «Listo/Entregado» es un registro administrativo: el
            sistema no puede verificar la grabación física.
        </p>
    </div>
</template>
