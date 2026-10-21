<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi cuenta', href: edit() }],
    },
});

defineProps<{ puedeCambiarEmail: boolean }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Mi cuenta" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Tu nombre y el correo con el que inicias sesión."
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    :name="puedeCambiarEmail ? 'email' : undefined"
                    :default-value="user.email"
                    :readonly="!puedeCambiarEmail"
                    :class="
                        !puedeCambiarEmail && 'bg-muted text-muted-foreground'
                    "
                    autocomplete="username"
                    aria-describedby="ayuda-email"
                />
                <p
                    v-if="!puedeCambiarEmail"
                    id="ayuda-email"
                    class="text-xs text-muted-foreground"
                >
                    Tu correo de acceso lo administra DASTI. Si necesitas
                    cambiarlo, contáctanos.
                </p>
                <InputError :message="errors.email" />
            </div>

            <div
                v-if="user.negocio"
                class="rounded-lg border bg-muted/40 p-3 text-sm"
            >
                <span class="text-muted-foreground">Negocio:</span>
                <span class="font-medium">
                    {{ user.negocio.nombre_comercial }}</span
                >
            </div>

            <Button :disabled="processing" data-test="update-profile-button">
                <Spinner v-if="processing" />
                Guardar cambios
            </Button>
        </Form>
    </div>
</template>
