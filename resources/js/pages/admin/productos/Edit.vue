<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorTipoProducto from '@/components/admin-productos/SelectorTipoProducto.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, show, update } from '@/routes/admin/productos';
import type { Opcion, Producto, Recurso } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: index() },
            { title: 'Editar producto', href: '#' },
        ],
    },
});

const props = defineProps<{
    producto: Recurso<Producto>;
    negocios: Opcion[];
    tipos: Opcion[];
    estados: Opcion[];
    preparaciones: Opcion[];
}>();

const p = props.producto.data;

const form = useForm({
    nombre: p.nombre,
    tipo: p.tipo as string,
    estado_preparacion: p.estado_preparacion ?? 'pendiente',
    fecha_entrega: p.fecha_entrega ?? '',
    referencia_comercial: p.referencia_comercial ?? '',
    notas_internas: p.notas_internas ?? '',
});

const guardar = () => {
    form.transform((datos) => ({
        ...datos,
        fecha_entrega: datos.fecha_entrega || null,
        referencia_comercial: datos.referencia_comercial.trim() || null,
        notas_internas: datos.notas_internas.trim() || null,
    })).put(update.url(p.id));
};
</script>

<template>
    <Head :title="`Editar ${p.nombre}`" />

    <EncabezadoPagina
        :titulo="`Editar «${p.nombre}»`"
        descripcion="Datos administrativos del producto."
    >
        <template #detalle>
            <p class="font-mono text-xs text-muted-foreground">
                {{ p.url_permanente }}
            </p>
        </template>
    </EncabezadoPagina>

    <form
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
        novalidate
        @submit.prevent="guardar"
    >
        <div class="space-y-6">
            <Panel titulo="Producto">
                <div class="grid gap-5">
                    <CampoFormulario
                        etiqueta="Nombre descriptivo"
                        para="nombre"
                        :error="form.errors.nombre"
                    >
                        <Input
                            id="nombre"
                            v-model="form.nombre"
                            maxlength="110"
                            required
                            :aria-invalid="!!form.errors.nombre || undefined"
                        />
                    </CampoFormulario>
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Tipo de producto
                        </legend>
                        <SelectorTipoProducto
                            v-model="form.tipo"
                            :tipos="tipos"
                        />
                        <p
                            v-if="form.errors.tipo"
                            class="text-sm text-red-600 dark:text-red-500"
                        >
                            {{ form.errors.tipo }}
                        </p>
                    </fieldset>
                </div>
            </Panel>

            <Panel
                titulo="Datos internos de DASTI"
                descripcion="No se muestran al cliente."
            >
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <CampoFormulario
                        etiqueta="Estado de preparación"
                        para="estado_preparacion"
                        :error="form.errors.estado_preparacion"
                    >
                        <SelectNativo
                            id="estado_preparacion"
                            v-model="form.estado_preparacion"
                            :opciones="preparaciones"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Fecha de entrega"
                        para="fecha_entrega"
                        :error="form.errors.fecha_entrega"
                        opcional
                    >
                        <Input
                            id="fecha_entrega"
                            v-model="form.fecha_entrega"
                            type="date"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Referencia comercial o lote"
                        para="referencia_comercial"
                        :error="form.errors.referencia_comercial"
                        opcional
                        class="sm:col-span-2"
                    >
                        <Input
                            id="referencia_comercial"
                            v-model="form.referencia_comercial"
                            maxlength="80"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Notas internas"
                        para="notas_internas"
                        :error="form.errors.notas_internas"
                        opcional
                        class="sm:col-span-2"
                    >
                        <Textarea
                            id="notas_internas"
                            v-model="form.notas_internas"
                            rows="4"
                            maxlength="5000"
                        />
                    </CampoFormulario>
                </div>
            </Panel>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            <Panel titulo="¿Buscas otra cosa?">
                <ul class="space-y-3 text-sm text-muted-foreground">
                    <li class="flex gap-2">
                        <Info class="mt-0.5 size-4 shrink-0 text-primary" />
                        <span
                            >El
                            <strong class="text-foreground">destino</strong> se
                            cambia en la pestaña «Enlace y destino» del
                            producto.</span
                        >
                    </li>
                    <li class="flex gap-2">
                        <Info class="mt-0.5 size-4 shrink-0 text-primary" />
                        <span
                            >El
                            <strong class="text-foreground"
                                >negocio propietario</strong
                            >
                            solo cambia mediante «Transferir», que queda
                            auditado.</span
                        >
                    </li>
                    <li class="flex gap-2">
                        <Info class="mt-0.5 size-4 shrink-0 text-primary" />
                        <span
                            >El
                            <strong class="text-foreground"
                                >código del enlace permanente</strong
                            >
                            no puede modificarse: está impreso o grabado en el
                            producto físico.</span
                        >
                    </li>
                </ul>
            </Panel>
            <div class="flex flex-col gap-2">
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="w-full"
                >
                    <Spinner v-if="form.processing" />
                    Guardar cambios
                </Button>
                <Button variant="ghost" as-child class="w-full">
                    <Link :href="show(p.id)">Cancelar</Link>
                </Button>
            </div>
        </aside>
    </form>
</template>
