<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import CamposNegocio from '@/components/negocios/CamposNegocio.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import CampoContrasenaTemporal from '@/components/usuarios/CampoContrasenaTemporal.vue';
import SelectorMetodoAcceso from '@/components/usuarios/SelectorMetodoAcceso.vue';
import { create, index, store } from '@/routes/admin/negocios';
import type { Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Negocios', href: index() },
            { title: 'Nuevo negocio', href: create() },
        ],
    },
});

defineProps<{
    estados: Opcion[];
    passwordRules: string;
}>();

const form = useForm({
    nombre_comercial: '',
    responsable: '',
    email_contacto: '',
    telefono: '',
    notas_internas: '',
    estado: 'activo',
    crear_acceso: true,
    acceso_nombre: '',
    acceso_email: '',
    acceso_metodo: 'invitacion',
    acceso_password: '',
});

// Sugerir datos del acceso a partir del contacto mientras estén vacíos.
const sugerir = () => {
    if (form.acceso_nombre === '') {
        form.acceso_nombre = form.responsable;
    }

    if (form.acceso_email === '') {
        form.acceso_email = form.email_contacto;
    }
};

const guardar = () => {
    form.transform((datos) => ({
        ...datos,
        acceso_password:
            datos.crear_acceso && datos.acceso_metodo === 'temporal'
                ? datos.acceso_password
                : '',
    })).post(store.url());
};
</script>

<template>
    <Head title="Nuevo negocio" />

    <EncabezadoPagina
        titulo="Nuevo negocio"
        descripcion="Registra al cliente y, si lo deseas, crea desde ahora su acceso al panel."
    />

    <form class="space-y-6" novalidate @submit.prevent="guardar">
        <Panel
            titulo="Datos comerciales"
            descripcion="Información del negocio que compró los productos."
        >
            <CamposNegocio v-model="form" :errores="form.errors" />
            <div class="mt-5 max-w-xs">
                <CampoFormulario
                    etiqueta="Estado inicial"
                    para="estado"
                    :error="form.errors.estado"
                >
                    <SelectNativo
                        id="estado"
                        v-model="form.estado"
                        :opciones="estados"
                    />
                </CampoFormulario>
            </div>
        </Panel>

        <Panel
            titulo="Acceso del cliente"
            descripcion="Usuario principal con el que el negocio administrará sus enlaces."
        >
            <template #acciones>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Switch
                        v-model="form.crear_acceso"
                        @update:model-value="(v: boolean) => v && sugerir()"
                    />
                    Crear acceso ahora
                </label>
            </template>

            <div v-if="form.crear_acceso" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <CampoFormulario
                        etiqueta="Nombre del usuario"
                        para="acceso_nombre"
                        :error="form.errors.acceso_nombre"
                    >
                        <Input
                            id="acceso_nombre"
                            v-model="form.acceso_nombre"
                            autocomplete="off"
                            maxlength="255"
                            @focus="sugerir"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Correo del usuario principal"
                        para="acceso_email"
                        :error="form.errors.acceso_email"
                        ayuda="Será su usuario para iniciar sesión."
                    >
                        <Input
                            id="acceso_email"
                            v-model="form.acceso_email"
                            type="email"
                            autocomplete="off"
                            maxlength="255"
                            @focus="sugerir"
                        />
                    </CampoFormulario>
                </div>
                <SelectorMetodoAcceso
                    v-model="form.acceso_metodo"
                    nombre="acceso_metodo"
                />
                <CampoContrasenaTemporal
                    v-if="form.acceso_metodo === 'temporal'"
                    id="acceso_password"
                    v-model="form.acceso_password"
                    :reglas="passwordRules"
                    :error="form.errors.acceso_password"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Podrás crear el acceso más tarde desde la ficha del negocio.
            </p>
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child
                ><Link :href="index()">Cancelar</Link></Button
            >
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Save v-else />
                Registrar negocio
            </Button>
        </div>
    </form>
</template>
