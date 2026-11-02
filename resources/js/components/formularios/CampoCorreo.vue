<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MensajeValidacion from '@/components/formularios/MensajeValidacion.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { pedir } from '@/lib/peticion';
import { validarCorreo } from '@/lib/validacion';
import { correo as rutaValidacionCorreo } from '@/routes/admin/validaciones';

/**
 * Correo con validación de formato en vivo y, opcionalmente (solo en el
 * panel de DASTI), comprobación de duplicados en el servidor con retardo y
 * cancelación de consultas obsoletas.
 */
const modelo = defineModel<string | null>({ required: true });

/** `true` mientras el servidor confirma que el correo no está registrado. */
const disponible = defineModel<boolean | null>('disponible', {
    default: null,
});

const props = withDefaults(
    defineProps<{
        id?: string;
        /** Nombre del campo para formularios no controlados (<Form>). */
        name?: string;
        error?: string;
        placeholder?: string;
        autocomplete?: string;
        requerido?: boolean;
        /** Consulta si ya existe un usuario con ese correo. */
        verificarDuplicado?: boolean;
        /** Usuario que se está editando (su propio correo no cuenta). */
        ignorarId?: number | null;
    }>(),
    {
        id: 'email',
        placeholder: 'nombre@negocio.com',
        autocomplete: 'email',
        requerido: false,
        verificarDuplicado: false,
        ignorarId: null,
    },
);

const valor = computed({
    get: () => modelo.value ?? '',
    set: (nuevo: string) => (modelo.value = nuevo),
});

const formato = computed(() => validarCorreo(valor.value));
const consultando = ref(false);
const resultadoServidor = ref<{
    correo: string;
    mensaje: string;
    libre: boolean;
} | null>(null);
let control: AbortController | null = null;

const consultar = useDebounceFn(async (correo: string) => {
    control?.abort();
    control = new AbortController();
    consultando.value = true;

    try {
        const respuesta = await pedir(rutaValidacionCorreo.url(), {
            metodo: 'POST',
            datos: { email: correo, ignorar_id: props.ignorarId },
            senal: control.signal,
        });
        const cuerpo = (await respuesta.json()) as {
            disponible: boolean;
            mensaje: string;
        };

        // Se descarta la respuesta si el valor cambió mientras tanto.
        if (correo === valor.value.trim().toLowerCase()) {
            resultadoServidor.value = {
                correo,
                mensaje: cuerpo.mensaje,
                libre: cuerpo.disponible,
            };
            disponible.value = cuerpo.disponible;
        }
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            // Sin conexión o sin permiso: el servidor validará al guardar.
            resultadoServidor.value = null;
            disponible.value = null;
        }
    } finally {
        consultando.value = false;
    }
}, 450);

watch(valor, (nuevo) => {
    resultadoServidor.value = null;
    disponible.value = null;

    if (props.verificarDuplicado && validarCorreo(nuevo).valido) {
        void consultar(nuevo.trim().toLowerCase());
    } else {
        control?.abort();
        consultando.value = false;
    }
});

onBeforeUnmount(() => control?.abort());

const estado = computed(() => {
    if (!formato.value.valido || !props.verificarDuplicado) {
        return formato.value;
    }

    if (resultadoServidor.value) {
        return {
            valido: resultadoServidor.value.libre,
            mensaje: resultadoServidor.value.libre
                ? 'El formato del correo es correcto.'
                : resultadoServidor.value.mensaje,
        };
    }

    return formato.value;
});

const idMensaje = computed(() => `${props.id}-estado`);
</script>

<template>
    <div class="grid gap-1.5">
        <div class="relative">
            <Input
                :id="id"
                :name="name"
                v-model="valor"
                type="email"
                inputmode="email"
                maxlength="255"
                spellcheck="false"
                autocapitalize="none"
                :autocomplete="autocomplete"
                :placeholder="placeholder"
                :required="requerido"
                :aria-invalid="
                    !!error ||
                    (resultadoServidor !== null && !resultadoServidor.libre) ||
                    undefined
                "
                :aria-describedby="estado.mensaje ? idMensaje : undefined"
                :class="consultando && 'pr-9'"
            />
            <Spinner
                v-if="consultando"
                class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-label="Comprobando el correo"
            />
        </div>
        <MensajeValidacion
            v-if="!error"
            :id="idMensaje"
            :mensaje="estado.mensaje"
            :valido="estado.valido"
        />
        <InputError :message="error" />
    </div>
</template>
