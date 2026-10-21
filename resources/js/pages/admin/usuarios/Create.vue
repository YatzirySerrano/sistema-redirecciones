<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, Save } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import CampoContrasenaTemporal from '@/components/usuarios/CampoContrasenaTemporal.vue';
import SelectorMetodoAcceso from '@/components/usuarios/SelectorMetodoAcceso.vue';
import { create, index, store } from '@/routes/admin/usuarios';
import type { Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios y accesos', href: index() },
            { title: 'Nuevo acceso', href: create() },
        ],
    },
});

const props = defineProps<{
    negocios: Opcion[];
    negocio_id: number | null;
    roles: Opcion[];
    passwordRules: string;
}>();

const form = useForm({
    name: '',
    email: '',
    rol: 'cliente',
    negocio_id: props.negocio_id !== null ? String(props.negocio_id) : '',
    metodo: 'invitacion',
    password: '',
});

const guardar = () => {
    form.transform((d) => ({
        ...d,
        negocio_id: d.rol === 'cliente' ? d.negocio_id : null,
        password: d.metodo === 'temporal' ? d.password : '',
    })).post(store.url());
};
</script>

<template>
    <Head title="Nuevo acceso" />

    <EncabezadoPagina
        titulo="Nuevo acceso"
        descripcion="Crea un usuario para un negocio cliente o un administrador de DASTI."
    />

    <form class="space-y-6" novalidate @submit.prevent="guardar">
        <Panel titulo="Datos del usuario">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Nombre completo"
                    para="name"
                    :error="form.errors.name"
                >
                    <Input
                        id="name"
                        v-model="form.name"
                        maxlength="255"
                        autocomplete="off"
                        required
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Correo electrónico"
                    para="email"
                    :error="form.errors.email"
                    ayuda="Será su usuario para iniciar sesión."
                >
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        autocomplete="off"
                        required
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Rol"
                    para="rol"
                    :error="form.errors.rol"
                >
                    <SelectNativo
                        id="rol"
                        v-model="form.rol"
                        :opciones="roles"
                    />
                </CampoFormulario>
                <CampoFormulario
                    v-if="form.rol === 'cliente'"
                    etiqueta="Negocio"
                    para="negocio_id"
                    :error="form.errors.negocio_id"
                >
                    <SelectNativo
                        id="negocio_id"
                        v-model="form.negocio_id"
                        :opciones="negocios"
                        vacio="Selecciona un negocio activo"
                        :invalido="!!form.errors.negocio_id"
                    />
                </CampoFormulario>
            </div>
            <p
                v-if="form.rol === 'admin'"
                class="mt-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                Un administrador tiene control total de la plataforma: negocios,
                productos, accesos y configuración.
            </p>
        </Panel>

        <Panel titulo="Método de acceso">
            <div class="space-y-5">
                <SelectorMetodoAcceso v-model="form.metodo" nombre="metodo" />
                <CampoContrasenaTemporal
                    v-if="form.metodo === 'temporal'"
                    id="password"
                    v-model="form.password"
                    :reglas="passwordRules"
                    :error="form.errors.password"
                />
            </div>
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child
                ><Link :href="index()">Cancelar</Link></Button
            >
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Save v-else />
                Crear acceso
            </Button>
        </div>
    </form>
</template>
