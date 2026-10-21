<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { LinkIcon } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Activa tu acceso',
        description: 'Define la contraseña con la que entrarás al panel.',
    },
});

defineProps<{
    valida: boolean;
    nombre?: string;
    email?: string;
    /** URL firmada a la que se envía el formulario. */
    accion?: string;
    passwordRules?: string;
}>();
</script>

<template>
    <Head title="Activar acceso" />

    <div v-if="!valida" class="space-y-4 text-center" role="alert">
        <div
            class="mx-auto flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
        >
            <LinkIcon class="size-5" aria-hidden="true" />
        </div>
        <div class="space-y-1">
            <h2 class="font-semibold">Este enlace ya no es válido</h2>
            <p class="text-sm text-muted-foreground">
                Caducó, ya se utilizó o fue reemplazado por uno más reciente.
                Pide a DASTI que te envíe un enlace nuevo.
            </p>
        </div>
        <Button as-child variant="outline" class="w-full">
            <Link :href="login()">Ir a iniciar sesión</Link>
        </Button>
    </div>

    <Form
        v-else
        :action="accion!"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <p class="text-sm text-muted-foreground">
                Hola,
                <span class="font-medium text-foreground">{{ nombre }}</span
                >. El enlace funciona una sola vez.
            </p>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="email"
                    autocomplete="username"
                    readonly
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="Nueva contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Confirma la contraseña</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Repite la contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Activar mi acceso
            </Button>
        </div>
    </Form>
</template>
