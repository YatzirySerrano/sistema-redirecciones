<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Save, X } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/admin/servicios';
import type { ServicioAdmin } from './tipos';

/**
 * Configuración comercial de un servicio adicional: precio final, impuestos,
 * vigencia (vacío = permanente), disponibilidad y presentación comercial.
 * Los importes no se inventan: sin precio no se puede contratar en línea.
 */
const props = defineProps<{ servicio: ServicioAdmin; editable: boolean }>();

const form = useForm({
    precio: props.servicio.precio_centavos
        ? (props.servicio.precio_centavos / 100).toFixed(2)
        : '',
    impuestos_incluidos: props.servicio.impuestos_incluidos,
    vigencia_meses: (props.servicio.vigencia_meses ?? undefined) as
        | number
        | undefined,
    contratable_en_linea: props.servicio.contratable_en_linea,
    activo: props.servicio.activo,
    descripcion: props.servicio.descripcion ?? '',
    beneficios: [...props.servicio.beneficios],
});

const guardar = () =>
    form
        .transform((d) => ({
            ...d,
            precio: d.precio === '' ? null : d.precio,
            vigencia_meses: d.vigencia_meses || null,
            beneficios: d.beneficios.map((b) => b.trim()).filter(Boolean),
        }))
        .put(update.url(props.servicio.id), {
            preserveScroll: true,
            onSuccess: () => form.defaults(),
        });

const errorBeneficio = (i: number) =>
    (form.errors as Record<string, string | undefined>)[`beneficios.${i}`];
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="guardar">
        <div class="grid grid-cols-2 gap-3">
            <CampoFormulario
                etiqueta="Precio final (MXN)"
                :para="`precio-${servicio.id}`"
                :error="form.errors.precio"
            >
                <Input
                    :id="`precio-${servicio.id}`"
                    v-model="form.precio"
                    inputmode="decimal"
                    placeholder="Sin precio"
                    :disabled="!editable"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Vigencia (meses)"
                :para="`vigencia-${servicio.id}`"
                :error="form.errors.vigencia_meses"
            >
                <Input
                    :id="`vigencia-${servicio.id}`"
                    v-model.number="form.vigencia_meses"
                    type="number"
                    min="1"
                    max="120"
                    placeholder="Permanente"
                    :disabled="!editable"
                />
            </CampoFormulario>
        </div>
        <p class="-mt-2 text-xs text-muted-foreground">
            Vigencia vacía = permanente (pago único, sin renovación).
        </p>
        <label class="flex items-center justify-between gap-3 text-sm">
            <span>
                El precio incluye IVA
                <span class="block text-xs text-muted-foreground"
                    >Se muestra al cliente junto al importe.</span
                >
            </span>
            <Switch v-model="form.impuestos_incluidos" :disabled="!editable" />
        </label>
        <label class="flex items-center justify-between gap-3 text-sm">
            Permitir contratación en línea (Openpay)
            <Switch v-model="form.contratable_en_linea" :disabled="!editable" />
        </label>
        <label class="flex items-center justify-between gap-3 text-sm">
            <span>
                Servicio disponible
                <span class="block text-xs text-muted-foreground"
                    >Si lo ocultas, quienes ya lo tienen lo conservan.</span
                >
            </span>
            <Switch v-model="form.activo" :disabled="!editable" />
        </label>

        <CampoFormulario
            etiqueta="Descripción para el cliente"
            :para="`descripcion-${servicio.id}`"
            :error="form.errors.descripcion"
        >
            <Textarea
                :id="`descripcion-${servicio.id}`"
                v-model="form.descripcion"
                rows="2"
                maxlength="255"
                :disabled="!editable"
            />
        </CampoFormulario>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Beneficios</legend>
            <p class="text-xs text-muted-foreground">
                Se muestran en la presentación comercial (máximo 8).
            </p>
            <div v-for="(_, i) in form.beneficios" :key="i" class="space-y-1">
                <div class="flex gap-2">
                    <Input
                        v-model="form.beneficios[i]"
                        maxlength="140"
                        :aria-label="`Beneficio ${i + 1}`"
                        :disabled="!editable"
                    />
                    <Button
                        v-if="editable"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Quitar beneficio ${i + 1}`"
                        @click="form.beneficios.splice(i, 1)"
                        ><X
                    /></Button>
                </div>
                <p v-if="errorBeneficio(i)" class="text-xs text-destructive">
                    {{ errorBeneficio(i) }}
                </p>
            </div>
            <Button
                v-if="editable && form.beneficios.length < 8"
                type="button"
                variant="outline"
                size="sm"
                @click="form.beneficios.push('')"
                ><Plus /> Agregar beneficio</Button
            >
        </fieldset>

        <Button
            v-if="editable"
            type="submit"
            class="w-full"
            :disabled="form.processing || !form.isDirty"
            ><Save /> Guardar {{ servicio.nombre }}</Button
        >
    </form>
</template>
