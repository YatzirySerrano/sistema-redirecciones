<script setup lang="ts">
import { CircleCheck, Send } from '@lucide/vue';
import { reactive, ref } from 'vue';
import type { Bloque } from './tipos';

/**
 * Formulario de contacto de la página pública (sin cookies): campo trampa,
 * marca de tiempo firmada y límites en el servidor.
 */
const props = defineProps<{
    bloque: Bloque;
    modo: 'publico' | 'vista_previa';
    contacto?: { accion: string; marca: string };
}>();

const datos = reactive({
    nombre: '',
    email: '',
    telefono: '',
    mensaje: '',
    sitio_web: '',
});
const errores = ref<Record<string, string>>({});
const general = ref<string | null>(null);
const enviando = ref(false);
const enviado = ref<string | null>(null);
const id = (campo: string) => `pe-${campo}-${props.bloque.id}`;

const enviar = async () => {
    if (props.modo !== 'publico' || !props.contacto || enviando.value) {
        return;
    }

    enviando.value = true;
    errores.value = {};
    general.value = null;

    try {
        const respuesta = await fetch(props.contacto.accion, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                ...datos,
                bloque: props.bloque.id,
                marca: props.contacto.marca,
            }),
            credentials: 'omit',
        });
        const cuerpo = (await respuesta.json().catch(() => ({}))) as {
            mensaje?: string;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (respuesta.ok) {
            enviado.value = cuerpo.mensaje ?? props.bloque.exito ?? '¡Gracias!';

            return;
        }

        errores.value = Object.fromEntries(
            Object.entries(cuerpo.errors ?? {}).map(([k, v]) => [k, v[0]]),
        );
        general.value =
            cuerpo.message ??
            'No pudimos enviar tu mensaje. Inténtalo de nuevo.';
    } catch {
        general.value =
            'No hay conexión. Revisa tu internet e inténtalo de nuevo.';
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <section class="pe-tarjeta p-5 @md:p-6">
        <h2 v-if="bloque.titulo" class="pe-titulo text-[1.15em]">
            {{ bloque.titulo }}
        </h2>
        <p v-if="bloque.descripcion" class="mt-1 text-[0.92em] opacity-80">
            {{ bloque.descripcion }}
        </p>

        <div
            v-if="enviado"
            class="mt-4 flex items-start gap-3 rounded-[calc(var(--pe-radio)*0.7)] border border-(--pe-borde) p-4"
            role="status"
        >
            <CircleCheck class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>{{ enviado }}</p>
        </div>

        <form
            v-else
            class="mt-4 grid grid-cols-1 gap-3"
            novalidate
            @submit.prevent="enviar"
        >
            <div class="grid grid-cols-1 gap-1.5">
                <label :for="id('nombre')" class="text-[0.85em] font-medium"
                    >Nombre</label
                >
                <input
                    :id="id('nombre')"
                    v-model="datos.nombre"
                    class="pe-campo"
                    autocomplete="name"
                    maxlength="100"
                    required
                    :aria-invalid="!!errores.nombre || undefined"
                />
                <p v-if="errores.nombre" class="pe-error">
                    {{ errores.nombre }}
                </p>
            </div>
            <div class="grid grid-cols-1 gap-1.5">
                <label :for="id('email')" class="text-[0.85em] font-medium"
                    >Correo electrónico</label
                >
                <input
                    :id="id('email')"
                    v-model="datos.email"
                    class="pe-campo"
                    type="email"
                    autocomplete="email"
                    inputmode="email"
                    maxlength="190"
                    required
                    :aria-invalid="!!errores.email || undefined"
                />
                <p v-if="errores.email" class="pe-error">{{ errores.email }}</p>
            </div>
            <div v-if="bloque.pedir_telefono" class="grid grid-cols-1 gap-1.5">
                <label :for="id('telefono')" class="text-[0.85em] font-medium"
                    >Teléfono <span class="opacity-70">(opcional)</span></label
                >
                <input
                    :id="id('telefono')"
                    v-model="datos.telefono"
                    class="pe-campo"
                    type="tel"
                    autocomplete="tel"
                    inputmode="tel"
                    maxlength="30"
                />
                <p v-if="errores.telefono" class="pe-error">
                    {{ errores.telefono }}
                </p>
            </div>
            <div class="grid grid-cols-1 gap-1.5">
                <label :for="id('mensaje')" class="text-[0.85em] font-medium"
                    >Mensaje</label
                >
                <textarea
                    :id="id('mensaje')"
                    v-model="datos.mensaje"
                    class="pe-campo min-h-28 py-2.5"
                    maxlength="2000"
                    rows="4"
                    required
                    :aria-invalid="!!errores.mensaje || undefined"
                />
                <p v-if="errores.mensaje" class="pe-error">
                    {{ errores.mensaje }}
                </p>
            </div>
            <!-- Campo trampa: las personas no lo ven ni lo llenan. -->
            <div class="pe-trampa" aria-hidden="true">
                <label :for="id('sitio')">No llenar</label>
                <input
                    :id="id('sitio')"
                    v-model="datos.sitio_web"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <p class="text-[0.78em] leading-relaxed opacity-75">
                {{ bloque.aviso }}
            </p>
            <p v-if="general" class="pe-error" role="alert">{{ general }}</p>

            <button
                type="submit"
                class="pe-boton justify-center"
                :data-estilo="'solido'"
                :disabled="enviando || modo !== 'publico'"
            >
                <Send class="size-4" aria-hidden="true" />
                <span class="font-semibold">{{
                    enviando ? 'Enviando…' : bloque.boton || 'Enviar mensaje'
                }}</span>
            </button>
            <p
                v-if="modo !== 'publico'"
                class="text-center text-[0.78em] opacity-70"
            >
                Vista previa: el formulario se envía desde la página publicada.
            </p>
        </form>
    </section>
</template>
