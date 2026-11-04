<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { transferir } from '@/routes/admin/productos';
import type { Producto } from '@/types';

const props = withDefaults(
    defineProps<{ producto: Producto; sinDisparador?: boolean }>(),
    { sinDisparador: false },
);

/** Abierto: controlable desde fuera (menú de acciones). */
const abierto = defineModel<boolean>('open', { default: false });
const excluir = computed(() =>
    props.producto.negocio ? [String(props.producto.negocio.id)] : [],
);

const form = useForm({ negocio_id: '', motivo: '', conservar_destino: false });

const valido = computed(
    () => form.negocio_id !== '' && form.motivo.trim().length >= 5,
);

const enviar = () => {
    form.post(transferir.url(props.producto.id), {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogTrigger v-if="!sinDisparador" as-child>
            <Button variant="outline"><ArrowRightLeft /> Transferir</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-5" novalidate @submit.prevent="enviar">
                <DialogHeader>
                    <DialogTitle
                        >Transferir «{{ producto.nombre }}»</DialogTitle
                    >
                    <DialogDescription>
                        Cambia el negocio propietario. El enlace permanente no
                        cambia, así que el producto físico sigue funcionando.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="flex gap-3 rounded-lg bg-warning-soft p-3 text-sm text-warning"
                    role="note"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <p>
                        <strong>{{
                            producto.negocio?.nombre_comercial
                        }}</strong>
                        perderá el acceso de inmediato. Su historial y
                        estadísticas previas no serán visibles para el nuevo
                        propietario.
                    </p>
                </div>

                <CampoFormulario
                    etiqueta="Negocio destino"
                    para="transferir-negocio"
                    :error="form.errors.negocio_id"
                >
                    <SelectorBuscable
                        id="transferir-negocio"
                        v-model="form.negocio_id"
                        :url="opcionesNegocios.url()"
                        :excluir="excluir"
                        etiqueta="Negocio destino"
                        placeholder="Busca y selecciona un negocio"
                        placeholder-busqueda="Nombre, responsable o correo…"
                        sin-resultados="Ningún negocio coincide con la búsqueda."
                        :invalido="!!form.errors.negocio_id"
                    />
                </CampoFormulario>

                <CampoFormulario
                    etiqueta="Motivo de la transferencia"
                    para="transferir-motivo"
                    :error="form.errors.motivo"
                    ayuda="Obligatorio (mínimo 5 caracteres): cambiar el negocio propietario de un enlace permanente es una operación sensible y debe quedar justificada en la auditoría."
                >
                    <Textarea
                        id="transferir-motivo"
                        v-model="form.motivo"
                        rows="2"
                        maxlength="255"
                    />
                </CampoFormulario>

                <label
                    class="flex items-start gap-3 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="form.conservar_destino"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-[var(--primary)]"
                    />
                    <span>
                        <span class="font-medium"
                            >Conservar el destino actual</span
                        >
                        <span class="block text-xs text-muted-foreground">
                            Si no se marca, el destino se borra y el enlace
                            mostrará «no disponible» hasta que el nuevo
                            propietario lo configure.
                        </span>
                    </span>
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="form.processing"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="!valido || form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        Transferir producto
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
