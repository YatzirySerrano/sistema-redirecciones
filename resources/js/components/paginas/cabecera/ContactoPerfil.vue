<script setup lang="ts">
import { Mail, MapPin, Phone } from '@lucide/vue';

/** Teléfono, correo y dirección del perfil (bajo el encabezado). */
defineProps<{
    telefono: string;
    email: string;
    direccion: string;
    centrado: boolean;
    modo: 'publico' | 'vista_previa';
}>();
</script>

<template>
    <ul
        v-if="telefono || email || direccion"
        class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-[0.85em] text-(--pe-suave)"
        :class="centrado ? 'justify-center' : ''"
    >
        <li v-if="telefono">
            <a
                :href="modo === 'publico' ? `tel:+${telefono}` : undefined"
                class="inline-flex items-center gap-1.5 hover:underline"
            >
                <Phone class="size-3.5" aria-hidden="true" />+{{ telefono }}
            </a>
        </li>
        <li v-if="email">
            <a
                :href="modo === 'publico' ? `mailto:${email}` : undefined"
                class="inline-flex items-center gap-1.5 hover:underline"
            >
                <Mail class="size-3.5" aria-hidden="true" />{{ email }}
            </a>
        </li>
        <li v-if="direccion" class="inline-flex items-center gap-1.5">
            <MapPin class="size-3.5" aria-hidden="true" />{{ direccion }}
        </li>
    </ul>
</template>
