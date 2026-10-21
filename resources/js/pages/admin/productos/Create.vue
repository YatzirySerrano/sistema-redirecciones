<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info, Link2 } from '@lucide/vue';
import { computed, toRef } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorTipoProducto from '@/components/admin-productos/SelectorTipoProducto.vue';
import EstadoValidacionDestino from '@/components/productos/EstadoValidacionDestino.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useValidacionDestino } from '@/composables/useValidacionDestino';
import { create, index, store } from '@/routes/admin/productos';
import type { Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: index() },
            { title: 'Registrar producto', href: create() },
        ],
    },
});

const props = defineProps<{
    negocio_id: number | null;
    url_publica_base: string;
    negocios: Opcion[];
    tipos: Opcion[];
    preparaciones: Opcion[];
}>();

const form = useForm({
    negocio_id: props.negocio_id !== null ? String(props.negocio_id) : '',
    nombre: '',
    tipo: 'nfc',
    cantidad: 1,
    url_destino: '',
    estado_preparacion: 'pendiente',
    fecha_entrega: '',
    referencia_comercial: '',
    notas_internas: '',
});

const { resultado } = useValidacionDestino(toRef(form, 'url_destino'));
const cantidad = computed(() =>
    Math.max(1, Math.min(50, Number(form.cantidad) || 1)),
);
const nombreEjemplo = computed(
    () => form.nombre.trim() || 'Tarjeta NFC mostrador',
);

const guardar = () => {
    form.transform((datos) => ({
        ...datos,
        cantidad: cantidad.value,
        url_destino: datos.url_destino.trim() || null,
        fecha_entrega: datos.fecha_entrega || null,
        referencia_comercial: datos.referencia_comercial.trim() || null,
        notas_internas: datos.notas_internas.trim() || null,
    })).post(store.url());
};
</script>

<template>
    <Head title="Registrar producto" />

    <EncabezadoPagina
        titulo="Registrar producto"
        descripcion="Cada producto obtiene un enlace permanente único para imprimir en el QR o grabar en la tarjeta NFC."
    />

    <form
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
        novalidate
        @submit.prevent="guardar"
    >
        <div class="space-y-6">
            <Panel
                titulo="Producto"
                descripcion="Datos que verán DASTI y el cliente."
            >
                <div class="grid gap-5">
                    <CampoFormulario
                        etiqueta="Negocio propietario"
                        para="negocio_id"
                        :error="form.errors.negocio_id"
                    >
                        <SelectNativo
                            id="negocio_id"
                            v-model="form.negocio_id"
                            :opciones="negocios"
                            vacio="Selecciona un negocio"
                            :invalido="!!form.errors.negocio_id"
                        />
                    </CampoFormulario>

                    <div
                        class="grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_140px]"
                    >
                        <CampoFormulario
                            etiqueta="Nombre descriptivo"
                            para="nombre"
                            :error="form.errors.nombre"
                            ayuda="Ej.: Tarjeta NFC mostrador, QR reseñas de Google."
                        >
                            <Input
                                id="nombre"
                                v-model="form.nombre"
                                maxlength="110"
                                required
                                :aria-invalid="
                                    !!form.errors.nombre || undefined
                                "
                            />
                        </CampoFormulario>
                        <CampoFormulario
                            etiqueta="Cantidad"
                            para="cantidad"
                            :error="form.errors.cantidad"
                        >
                            <Input
                                id="cantidad"
                                v-model.number="form.cantidad"
                                type="number"
                                min="1"
                                max="50"
                                inputmode="numeric"
                                :aria-invalid="
                                    !!form.errors.cantidad || undefined
                                "
                            />
                        </CampoFormulario>
                    </div>
                    <p
                        v-if="cantidad > 1"
                        class="-mt-2 flex items-start gap-2 text-xs text-muted-foreground"
                    >
                        <Info class="mt-0.5 size-3.5 shrink-0" />
                        Se crearán {{ cantidad }} productos numerados («{{
                            nombreEjemplo
                        }}
                        1» … «{{ nombreEjemplo }} {{ cantidad }}»), cada uno con
                        su propio enlace permanente.
                    </p>

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
                titulo="Destino inicial"
                descripcion="Opcional. El cliente podrá cambiarlo después desde su panel."
            >
                <CampoFormulario
                    etiqueta="URL de destino"
                    para="url_destino"
                    :error="form.errors.url_destino"
                    opcional
                >
                    <Input
                        id="url_destino"
                        v-model="form.url_destino"
                        type="url"
                        inputmode="url"
                        spellcheck="false"
                        maxlength="2048"
                        placeholder="https://www.instagram.com/negocio"
                        class="font-mono text-sm"
                        :aria-invalid="!!form.errors.url_destino || undefined"
                        aria-describedby="ayuda-url-destino"
                    />
                </CampoFormulario>
                <EstadoValidacionDestino
                    id="ayuda-url-destino"
                    class="mt-2"
                    :resultado="resultado"
                    texto-vacio="Sin destino, el enlace mostrará «no disponible» hasta configurarlo."
                />
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
                            placeholder="PED-2026-0012"
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
                            rows="3"
                            maxlength="5000"
                        />
                    </CampoFormulario>
                </div>
            </Panel>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            <Panel titulo="Enlace permanente">
                <div class="space-y-3 text-sm">
                    <div
                        class="flex items-center gap-2 rounded-lg border bg-muted/50 px-3 py-2 font-mono text-[13px]"
                    >
                        <Link2
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 truncate"
                            >{{ url_publica_base
                            }}<span class="text-muted-foreground"
                                >••••••••••</span
                            ></span
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        El código aleatorio se genera al guardar. Es el enlace
                        que se imprime en el QR o se graba en el NFC, y nunca
                        cambia aunque cambie el destino.
                    </p>
                </div>
            </Panel>
            <div class="flex flex-col gap-2">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full"
                >
                    <Spinner v-if="form.processing" />
                    {{
                        cantidad > 1
                            ? `Registrar ${cantidad} productos`
                            : 'Registrar producto'
                    }}
                </Button>
                <Button variant="ghost" as-child class="w-full">
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </aside>
    </form>
</template>
