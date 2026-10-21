<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { update } from '@/routes/contrasena-inicial';

defineOptions({
    layout: {
        title: 'Crea tu contraseña personal',
        description:
            'Por seguridad, reemplaza la contraseña temporal que recibiste antes de continuar.',
    },
});

defineProps<{ passwordRules: string }>();
</script>

<template>
    <Head title="Crear contraseña" />

    <div
        class="mb-6 flex gap-3 rounded-lg border border-primary/20 bg-accent p-3 text-sm text-accent-foreground"
    >
        <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <p>Solo tú conocerás la nueva contraseña. DASTI nunca te la pedirá.</p>
    </div>

    <Form
        v-bind="update.form()"
        :reset-on-error="[
            'current_password',
            'password',
            'password_confirmation',
        ]"
        v-slot="{ errors, processing }"
        class="grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="current_password">Contraseña temporal</Label>
            <PasswordInput
                id="current_password"
                name="current_password"
                required
                autocomplete="current-password"
                autofocus
            />
            <InputError :message="errors.current_password" />
        </div>
        <div class="grid gap-2">
            <Label for="password">Nueva contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>
        <div class="grid gap-2">
            <Label for="password_confirmation"
                >Confirma la nueva contraseña</Label
            >
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>
        <Button type="submit" class="mt-2 h-10 w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Guardar y continuar
        </Button>
        <Link
            :href="logout()"
            as="button"
            class="text-center text-sm text-muted-foreground hover:text-foreground"
        >
            Cerrar sesión
        </Link>
    </Form>
</template>
