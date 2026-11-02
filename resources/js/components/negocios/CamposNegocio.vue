<script setup lang="ts">
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import CampoCorreo from '@/components/formularios/CampoCorreo.vue';
import CampoTelefono from '@/components/formularios/CampoTelefono.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { DatosNegocio } from '@/components/negocios/tipos';

/** Campos comerciales compartidos por alta y edición de negocios. */
const datos = defineModel<DatosNegocio>({ required: true });

defineProps<{ errores: Partial<Record<keyof DatosNegocio, string>> }>();
</script>

<template>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <CampoFormulario
                etiqueta="Nombre comercial"
                para="nombre_comercial"
                :error="errores.nombre_comercial"
            >
                <Input
                    id="nombre_comercial"
                    v-model="datos.nombre_comercial"
                    required
                    maxlength="150"
                    autocomplete="organization"
                    placeholder="Café La Esquina"
                />
            </CampoFormulario>
        </div>
        <CampoFormulario
            etiqueta="Responsable"
            para="responsable"
            opcional
            :error="errores.responsable"
        >
            <Input
                id="responsable"
                v-model="datos.responsable"
                maxlength="150"
                autocomplete="name"
                placeholder="Nombre del contacto principal"
            />
        </CampoFormulario>
        <CampoFormulario
            etiqueta="Teléfono"
            para="telefono"
            opcional
            :error="errores.telefono"
        >
            <CampoTelefono id="telefono" v-model="datos.telefono" />
        </CampoFormulario>
        <div class="sm:col-span-2">
            <CampoFormulario
                etiqueta="Correo de contacto"
                para="email_contacto"
                opcional
                :error="errores.email_contacto"
                ayuda="Correo comercial del negocio. El acceso al panel se configura aparte."
            >
                <CampoCorreo
                    id="email_contacto"
                    v-model="datos.email_contacto"
                    placeholder="contacto@negocio.com"
                />
            </CampoFormulario>
        </div>
        <div class="sm:col-span-2">
            <CampoFormulario
                etiqueta="Notas internas"
                para="notas_internas"
                opcional
                :error="errores.notas_internas"
                ayuda="Solo visibles para DASTI."
            >
                <Textarea
                    id="notas_internas"
                    v-model="datos.notas_internas"
                    rows="3"
                    maxlength="5000"
                    placeholder="Detalles del pedido, acuerdos, etc."
                />
            </CampoFormulario>
        </div>
    </div>
</template>
