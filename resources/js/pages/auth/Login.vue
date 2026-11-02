<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, Info, LockKeyhole, Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Bienvenido de nuevo',
        description:
            'Inicia sesión para administrar los destinos de tus códigos QR y tarjetas NFC.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-6 flex gap-2.5 rounded-lg border border-primary/20 bg-accent px-3.5 py-3 text-sm text-accent-foreground"
        role="status"
    >
        <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <p>{{ status }}</p>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Correo electrónico</Label>
            <div class="relative">
                <Mail
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="nombre@negocio.com"
                    class="h-11 pl-9"
                    :aria-invalid="!!errors.email || undefined"
                    aria-describedby="error-email"
                />
            </div>
            <InputError id="error-email" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-2">
                <Label for="password">Contraseña</Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm text-enlace"
                >
                    ¿Olvidaste tu contraseña?
                </TextLink>
            </div>
            <div class="relative">
                <LockKeyhole
                    class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    class="h-11 pl-9"
                    :aria-invalid="!!errors.password || undefined"
                />
            </div>
            <InputError :message="errors.password" />
        </div>

        <Label
            for="remember"
            class="flex w-fit cursor-pointer items-center gap-2.5 font-normal text-muted-foreground"
        >
            <Checkbox id="remember" name="remember" />
            <span>Mantener la sesión iniciada en este equipo</span>
        </Label>

        <Button
            type="submit"
            size="lg"
            class="mt-1 h-11 w-full text-[15px]"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Verificando…' : 'Iniciar sesión' }}
            <ArrowRight v-if="!processing" />
        </Button>

        <p
            class="border-t pt-5 text-center text-xs leading-relaxed text-muted-foreground"
        >
            ¿Aún no tienes acceso? DASTI te lo proporciona al entregar tu
            pedido.
        </p>
    </Form>
</template>
