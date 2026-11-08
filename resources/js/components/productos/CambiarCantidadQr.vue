<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { QrCode, TriangleAlert } from "@lucide/vue";
import { computed, watch } from "vue";
import CampoFormulario from "@/components/comun/CampoFormulario.vue";
import DialogoConfirmacion from "@/components/comun/DialogoConfirmacion.vue";
import SelectorCantidadQr from "@/components/productos/SelectorCantidadQr.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import type { Producto } from "@/types";

/**
 * Ampliar un producto a 2 QR o reducirlo a 1. La usan el administrador y el
 * cliente con el permiso «productos.cambiar_cantidad_qr» (cada uno con su
 * ruta, misma acción en el servidor).
 */
const props = withDefaults(
    defineProps<{
        producto: Producto;
        /** URL PATCH de la ruta (admin o cliente). */
        accion: string;
        /** El administrador puede indicar el destino inicial del QR 2. */
        conDestino?: boolean;
        /** Hubo un QR 2 que se archivó al reducir (se reactivará). */
        qr2Archivado?: { codigo: string } | null;
    }>(),
    { conDestino: false, qr2Archivado: null },
);

const abierto = defineModel<boolean>("open", { default: false });
const actual = computed(() => props.producto.cantidad_qr ?? 1);

const form = useForm({
    cantidad_qr: actual.value === 2 ? 1 : 2,
    motivo: "",
    url_destino_qr2: "",
});

watch(abierto, (valor) => {
    if (valor) {
        form.reset();
        form.clearErrors();
        form.cantidad_qr = actual.value === 2 ? 1 : 2;
    }
});

const ampliar = computed(() => form.cantidad_qr === 2);
const sinCambio = computed(() => form.cantidad_qr === actual.value);
const combinadoCompartido = computed(
    () =>
        props.producto.tipo === "combinado" &&
        props.producto.modalidad_destino === "compartido",
);

const confirmar = () => {
    form.transform((d) => ({
        cantidad_qr: d.cantidad_qr,
        motivo: d.motivo.trim() || null,
        url_destino_qr2:
            ampliar.value && props.conDestino
                ? d.url_destino_qr2.trim() || null
                : null,
    })).patch(props.accion, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    });
};
</script>

<template>
    <DialogoConfirmacion
        v-model:open="abierto"
        titulo="¿Cuántos QR lleva impresos?"
        :descripcion="`«${producto.nombre}» lleva hoy ${actual === 2 ? '2 QR' : '1 QR'}. Cada QR tiene su propio enlace, destino, diseño y estadísticas.`"
        :confirmar="ampliar ? 'Ampliar a 2 QR' : 'Reducir a 1 QR'"
        :destructivo="!ampliar"
        :deshabilitado="sinCambio"
        :procesando="form.processing"
        @confirmar="confirmar"
    >
        <template #disparador>
            <Button variant="outline" size="sm">
                <QrCode /> {{ actual === 2 ? "2 QR" : "1 QR" }} · Cambiar
            </Button>
        </template>
        <div class="space-y-4">
            <SelectorCantidadQr
                v-model="form.cantidad_qr"
                :con-nfc="producto.tipo === 'combinado'"
                nombre="cantidad_qr_cambio"
            />
            <p v-if="form.errors.cantidad_qr" class="text-sm text-destructive">
                {{ form.errors.cantidad_qr }}
            </p>

            <div
                v-if="!sinCambio"
                class="flex gap-2 rounded-lg border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground"
            >
                <TriangleAlert
                    v-if="!ampliar"
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <p v-if="ampliar && qr2Archivado">
                    Se reactivará el QR 2 anterior (/r/{{
                        qr2Archivado.codigo
                    }}) con su destino e historial.
                </p>
                <p v-else-if="ampliar && combinadoCompartido">
                    El enlace actual queda para el NFC (no hay que regrabar el
                    chip). Se crean el QR 1, con el mismo destino y diseño, y el
                    QR 2. Hay que imprimir los dos QR nuevos.
                </p>
                <p v-else-if="ampliar">
                    El QR que ya está impreso conserva su enlace y pasa a ser el
                    QR 1. Se crea el QR 2 con un enlace nuevo para imprimir.
                </p>
                <p v-else>
                    El QR 2 dejará de funcionar (mostrará «no disponible») y se
                    archivará. Su historial y estadísticas se conservan, y se
                    reactiva si vuelves a elegir 2 QR.
                </p>
            </div>

            <CampoFormulario
                v-if="ampliar && conDestino && !qr2Archivado && !sinCambio"
                etiqueta="Destino del QR 2"
                para="url_destino_qr2_cambio"
                :error="form.errors.url_destino_qr2"
                opcional
            >
                <Input
                    id="url_destino_qr2_cambio"
                    v-model="form.url_destino_qr2"
                    type="url"
                    inputmode="url"
                    spellcheck="false"
                    maxlength="2048"
                    placeholder="https://www.tiktok.com/@negocio"
                    class="font-mono text-sm"
                />
            </CampoFormulario>

            <CampoFormulario
                v-if="!sinCambio"
                etiqueta="Motivo"
                para="motivo_cantidad_qr"
                :error="form.errors.motivo"
                opcional
                ayuda="Queda registrado en la auditoría."
            >
                <Textarea
                    id="motivo_cantidad_qr"
                    v-model="form.motivo"
                    rows="2"
                    maxlength="255"
                />
            </CampoFormulario>
        </div>
    </DialogoConfirmacion>
</template>
