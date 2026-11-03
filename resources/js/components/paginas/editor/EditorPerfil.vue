<script setup lang="ts">
import { computed } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usarEditor } from './contexto';
import SelectorMedio from './SelectorMedio.vue';

/** Identidad: nombre, descripción, foto, logotipo, portada y contacto. */
const editor = usarEditor();
const perfil = computed(() => editor.documento.value.perfil);
const err = (campo: string) => editor.errores.value[`perfil.${campo}`];
</script>

<template>
    <fieldset class="grid grid-cols-1 gap-4" :disabled="!editor.editable">
        <CampoFormulario
            etiqueta="Nombre público"
            para="perfil-nombre"
            :error="err('nombre')"
        >
            <Input
                id="perfil-nombre"
                v-model="perfil.nombre"
                maxlength="80"
                class="h-11"
            />
        </CampoFormulario>
        <label class="flex min-h-11 items-center justify-between gap-3 text-sm">
            <span class="font-medium">Mostrar el nombre</span>
            <Switch
                v-model="perfil.mostrar_nombre"
                :disabled="!editor.editable"
            />
        </label>
        <CampoFormulario
            etiqueta="Descripción"
            para="perfil-descripcion"
            :error="err('descripcion')"
            opcional
            ayuda="Una o dos frases. Admite **negritas** y _cursivas_."
        >
            <Textarea
                id="perfil-descripcion"
                v-model="perfil.descripcion"
                rows="3"
                maxlength="300"
            />
        </CampoFormulario>
        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
        >
            <SelectorMedio
                v-model="perfil.foto"
                etiqueta="Foto de perfil"
                uso="miniatura"
                :error="err('foto')"
                :deshabilitado="!editor.editable"
            />
            <SelectorMedio
                v-model="perfil.logo"
                etiqueta="Logotipo"
                uso="miniatura"
                :error="err('logo')"
                :deshabilitado="!editor.editable"
            />
        </div>
        <SelectorMedio
            v-model="perfil.portada"
            etiqueta="Imagen de portada"
            uso="fondo"
            :error="err('portada')"
            :deshabilitado="!editor.editable"
            ayuda="Horizontal, de al menos 1200 px de ancho."
        />
        <details class="rounded-lg border p-3">
            <summary class="min-h-8 cursor-pointer text-sm font-medium">
                Datos de contacto visibles
            </summary>
            <div class="mt-3 grid grid-cols-1 gap-3">
                <CampoFormulario
                    etiqueta="Teléfono"
                    para="perfil-telefono"
                    :error="err('contacto.telefono')"
                    opcional
                >
                    <Input
                        id="perfil-telefono"
                        v-model="perfil.contacto.telefono"
                        type="tel"
                        inputmode="tel"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Correo"
                    para="perfil-email"
                    :error="err('contacto.email')"
                    opcional
                >
                    <Input
                        id="perfil-email"
                        v-model="perfil.contacto.email"
                        type="email"
                        inputmode="email"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Dirección"
                    para="perfil-direccion"
                    opcional
                >
                    <Input
                        id="perfil-direccion"
                        v-model="perfil.contacto.direccion"
                        maxlength="200"
                        class="h-11"
                    />
                </CampoFormulario>
            </div>
        </details>
    </fieldset>
</template>
