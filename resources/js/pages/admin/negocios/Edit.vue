<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import CamposNegocio from '@/components/negocios/CamposNegocio.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show, update } from '@/routes/admin/negocios';
import type { Negocio, Recurso } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Negocios', href: index() },
            { title: 'Editar negocio', href: '#' },
        ],
    },
});

const props = defineProps<{ negocio: Recurso<Negocio> }>();
const n = props.negocio.data;

const form = useForm({
    nombre_comercial: n.nombre_comercial,
    responsable: n.responsable ?? '',
    email_contacto: n.email_contacto ?? '',
    telefono: n.telefono ?? '',
    notas_internas: n.notas_internas ?? '',
});

const guardar = () => form.put(update.url(n.id));
</script>

<template>
    <Head :title="`Editar ${n.nombre_comercial}`" />

    <EncabezadoPagina
        titulo="Editar negocio"
        :descripcion="n.nombre_comercial"
    />

    <form class="space-y-6" novalidate @submit.prevent="guardar">
        <Panel
            titulo="Datos comerciales"
            descripcion="Los cambios quedan registrados en la auditoría."
        >
            <CamposNegocio v-model="form" :errores="form.errors" />
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child
                ><Link :href="show(n.id)">Cancelar</Link></Button
            >
            <Button type="submit" :disabled="form.processing || !form.isDirty">
                <Spinner v-if="form.processing" />
                <Save v-else />
                Guardar cambios
            </Button>
        </div>
    </form>
</template>
