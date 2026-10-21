<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { inicio, login } from '@/routes';

const props = defineProps<{ estado: number }>();
const page = usePage();

const MENSAJES: Record<number, { titulo: string; texto: string }> = {
    403: {
        titulo: 'Acceso no permitido',
        texto: 'Tu cuenta no tiene permiso para realizar esta acción.',
    },
    404: {
        titulo: 'Página no encontrada',
        texto: 'La página que buscas no existe o no está disponible para tu cuenta.',
    },
    429: {
        titulo: 'Demasiadas solicitudes',
        texto: 'Espera un momento antes de volver a intentarlo.',
    },
    500: {
        titulo: 'Ocurrió un error',
        texto: 'No pudimos completar la solicitud. Inténtalo de nuevo en unos minutos.',
    },
    503: {
        titulo: 'En mantenimiento',
        texto: 'Estamos realizando mejoras. Vuelve a intentarlo en unos minutos.',
    },
};

const mensaje = computed(() => MENSAJES[props.estado] ?? MENSAJES[500]);
const autenticado = computed(() => !!page.props.auth?.user);
</script>

<template>
    <Head :title="mensaje.titulo" />
    <main class="flex min-h-svh items-center justify-center bg-background p-6">
        <div
            class="w-full max-w-md rounded-2xl border bg-card p-10 text-center shadow-sm"
        >
            <p class="text-sm font-semibold text-primary tabular-nums">
                Error {{ estado }}
            </p>
            <h1 class="mt-2 text-xl font-semibold tracking-tight">
                {{ mensaje.titulo }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ mensaje.texto }}
            </p>
            <Button as-child class="mt-6">
                <Link :href="autenticado ? inicio() : login()">{{
                    autenticado ? 'Ir al inicio' : 'Iniciar sesión'
                }}</Link>
            </Button>
        </div>
    </main>
</template>
