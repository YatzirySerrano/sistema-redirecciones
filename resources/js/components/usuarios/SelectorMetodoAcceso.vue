<script setup lang="ts">
import { KeyRound, Mail } from '@lucide/vue';
import { cn } from '@/lib/utils';

/** Elección entre invitación por correo y contraseña temporal. */
const metodo = defineModel<string>({ required: true });

withDefaults(defineProps<{ nombre?: string }>(), { nombre: 'metodo' });

const OPCIONES = [
    {
        valor: 'invitacion',
        titulo: 'Enviar invitación por correo',
        texto: 'El usuario recibe un enlace para definir su propia contraseña.',
        icono: Mail,
    },
    {
        valor: 'temporal',
        titulo: 'Asignar contraseña temporal',
        texto: 'Tú la compartes; deberá cambiarla al iniciar sesión.',
        icono: KeyRound,
    },
];
</script>

<template>
    <fieldset class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <legend class="sr-only">Método de acceso</legend>
        <label
            v-for="o in OPCIONES"
            :key="o.valor"
            :class="
                cn(
                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition hover:bg-muted/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/40',
                    metodo === o.valor &&
                        'border-primary bg-accent/60 hover:bg-accent/60',
                )
            "
        >
            <input
                v-model="metodo"
                type="radio"
                :name="nombre"
                :value="o.valor"
                class="sr-only"
            />
            <span
                :class="
                    cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground',
                        metodo === o.valor &&
                            'bg-primary text-primary-foreground',
                    )
                "
                aria-hidden="true"
            >
                <component :is="o.icono" class="size-4" />
            </span>
            <span class="text-sm">
                <span class="block font-medium">{{ o.titulo }}</span>
                <span class="block text-xs text-muted-foreground">{{
                    o.texto
                }}</span>
            </span>
        </label>
    </fieldset>
</template>
