<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Acción visible de una fila o tarjeta: icono con texto (el texto se oculta
 * en pantallas medianas dentro de tablas, pero se conserva como nombre
 * accesible y descripción emergente).
 */
const props = withDefaults(
    defineProps<{
        etiqueta: string;
        icono: Component;
        href?: NonNullable<InertiaLinkProps['href']>;
        externo?: boolean;
        destructiva?: boolean;
        compacta?: boolean;
        disabled?: boolean;
    }>(),
    { href: undefined, externo: false, destructiva: false, compacta: false },
);

defineEmits<{ click: [evento: MouseEvent] }>();

const clases = () =>
    cn(
        'h-8 gap-1.5 px-2 text-xs',
        props.destructiva &&
            'text-destructive hover:bg-destructive/10 hover:text-destructive',
    );
</script>

<template>
    <Button
        v-if="href && !externo"
        variant="ghost"
        size="sm"
        :class="clases()"
        :title="etiqueta"
        as-child
    >
        <Link :href="href" :aria-label="etiqueta">
            <component :is="icono" aria-hidden="true" />
            <span :class="compacta && 'sr-only xl:not-sr-only'">{{
                etiqueta
            }}</span>
        </Link>
    </Button>
    <Button
        v-else-if="href && externo"
        variant="ghost"
        size="sm"
        :class="clases()"
        :title="etiqueta"
        as-child
    >
        <a :href="String(href)" :aria-label="etiqueta">
            <component :is="icono" aria-hidden="true" />
            <span :class="compacta && 'sr-only xl:not-sr-only'">{{
                etiqueta
            }}</span>
        </a>
    </Button>
    <Button
        v-else
        type="button"
        variant="ghost"
        size="sm"
        :class="clases()"
        :title="etiqueta"
        :aria-label="etiqueta"
        :disabled="disabled"
        @click="$emit('click', $event)"
    >
        <component :is="icono" aria-hidden="true" />
        <span :class="compacta && 'sr-only xl:not-sr-only'">{{
            etiqueta
        }}</span>
    </Button>
</template>
