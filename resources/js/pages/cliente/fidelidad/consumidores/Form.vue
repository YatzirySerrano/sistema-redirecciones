<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save, ShieldCheck } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    ConsumidorFicha,
    EstadoServicio,
} from '@/components/fidelidad/tipos';
import CampoCorreo from '@/components/formularios/CampoCorreo.vue';
import CampoTelefono from '@/components/formularios/CampoTelefono.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { inicio } from '@/routes/cliente/fidelidad';
import {
    index,
    show,
    store,
    update,
} from '@/routes/cliente/fidelidad/consumidores';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Consumidores', href: index() },
            { title: 'Datos del consumidor', href: '#' },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    consumidor: ConsumidorFicha | null;
    negocio: string;
    version_aviso: string;
}>();

const form = useForm({
    nombre: props.consumidor?.nombre ?? '',
    alias: props.consumidor?.alias ?? '',
    telefono: props.consumidor?.telefono ?? '',
    email: props.consumidor?.email ?? '',
    consentimiento: false,
});

const guardar = () =>
    props.consumidor
        ? form.put(update.url(props.consumidor.id))
        : form.post(store.url());
</script>

<template>
    <Head :title="consumidor ? 'Editar consumidor' : 'Registrar consumidor'" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        :titulo="consumidor ? 'Editar consumidor' : 'Registrar consumidor'"
        descripcion="Nombre y al menos un medio de contacto: teléfono o correo."
        ayuda="El teléfono sirve para encontrar al cliente; el correo, una vez confirmado, le permite recuperar su enlace por sí mismo."
    />

    <form class="max-w-2xl space-y-6" novalidate @submit.prevent="guardar">
        <Panel titulo="Datos">
            <div class="grid gap-5 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Nombre completo"
                    para="nombre"
                    :error="form.errors.nombre"
                    class="sm:col-span-2"
                >
                    <Input
                        id="nombre"
                        v-model="form.nombre"
                        maxlength="120"
                        autocomplete="off"
                        required
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Alias"
                    para="alias"
                    :error="form.errors.alias"
                    opcional
                    ayuda="Cómo se le saluda en su página de progreso."
                >
                    <Input
                        id="alias"
                        v-model="form.alias"
                        maxlength="60"
                        autocomplete="off"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Teléfono" para="telefono">
                    <CampoTelefono
                        id="telefono"
                        v-model="form.telefono"
                        :error="form.errors.telefono"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Correo electrónico"
                    para="email"
                    class="sm:col-span-2"
                >
                    <CampoCorreo
                        id="email"
                        v-model="form.email"
                        :error="form.errors.email"
                        autocomplete="off"
                    />
                </CampoFormulario>
            </div>
            <p
                v-if="
                    consumidor &&
                    consumidor.email &&
                    !consumidor.email_verificado
                "
                class="mt-3 text-xs text-warning"
            >
                El correo aún no está confirmado.
            </p>
        </Panel>

        <Panel v-if="!consumidor" titulo="Aviso de privacidad">
            <div class="space-y-3 text-sm">
                <p class="text-muted-foreground">
                    Lee o explica al cliente: «{{ negocio }} usará tu nombre y
                    tus datos de contacto solo para administrar tu programa de
                    recompensas (registrar visitas, avisarte de tus recompensas
                    y recuperar tu acceso). No los compartirá con otros
                    negocios. Puedes pedir su corrección o baja en el negocio».
                </p>
                <label class="flex items-start gap-3 rounded-lg border p-3">
                    <input
                        v-model="form.consentimiento"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-[var(--primary)]"
                    />
                    <span>
                        <span class="flex items-center gap-1.5 font-medium"
                            ><ShieldCheck class="size-4 text-primary" /> El
                            cliente acepta el aviso de privacidad</span
                        >
                        <span class="block text-xs text-muted-foreground"
                            >Versión {{ version_aviso }}. Se registra la fecha
                            de aceptación.</span
                        >
                    </span>
                </label>
                <InputError :message="form.errors.consentimiento" />
            </div>
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child>
                <Link :href="consumidor ? show(consumidor.id) : index()"
                    >Cancelar</Link
                >
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Save v-else />
                {{ consumidor ? 'Guardar cambios' : 'Registrar' }}
            </Button>
        </div>
    </form>
</template>
