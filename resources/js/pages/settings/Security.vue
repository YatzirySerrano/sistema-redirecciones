<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/security';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} &
    ManageTwoFactorProps;

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contraseña y seguridad',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Contraseña y seguridad" />

    <h1 class="sr-only">Contraseña y seguridad</h1>

    <div
        v-if="page.props.auth.permisos?.requiere_dos_pasos"
        class="mb-6 flex gap-3 rounded-lg border border-warning/30 bg-warning-soft p-4 text-sm text-warning"
        role="alert"
    >
        <ShieldAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <p>
            Para usar el panel de administración debes activar la
            <strong>verificación en dos pasos</strong>. Configúrala abajo con tu
            aplicación de autenticación y guarda los códigos de recuperación.
        </p>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Cambiar contraseña"
            description="Usa una contraseña larga y única que no utilices en otros servicios."
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Contraseña actual</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Contraseña actual"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Nueva contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Nueva contraseña"
                    :passwordrules="props.passwordRules"
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
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Repite la nueva contraseña"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    <Spinner v-if="processing" />
                    Guardar contraseña
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />
</template>
