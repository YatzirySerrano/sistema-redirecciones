<script setup lang="ts">
import { Eye, EyeOff, ShieldAlert, Wand2 } from '@lucide/vue';
import { ref } from 'vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

/**
 * Contraseña temporal generada en el navegador (crypto.getRandomValues).
 * El servidor solo recibe el valor para guardarlo con hash: nunca la
 * devuelve ni la almacena en texto plano.
 */
const modelo = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        id?: string;
        error?: string;
        reglas?: string;
    }>(),
    { id: 'password-temporal' },
);

const visible = ref(false);

const generar = () => {
    const grupos = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%*-_+?',
    ];
    const todos = grupos.join('');
    const aleatorio = (max: number) => {
        const buffer = new Uint32Array(1);
        const limite = Math.floor(0x100000000 / max) * max;

        do {
            crypto.getRandomValues(buffer);
        } while (buffer[0] >= limite);

        return buffer[0] % max;
    };

    // Al menos un carácter de cada grupo y 16 en total.
    const caracteres = grupos.map((g) => g[aleatorio(g.length)]);

    while (caracteres.length < 16) {
        caracteres.push(todos[aleatorio(todos.length)]);
    }

    for (let i = caracteres.length - 1; i > 0; i--) {
        const j = aleatorio(i + 1);
        [caracteres[i], caracteres[j]] = [caracteres[j], caracteres[i]];
    }

    modelo.value = caracteres.join('');
    visible.value = true;
};
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">Contraseña temporal</Label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <input
                    :id="id"
                    v-model="modelo"
                    :type="visible ? 'text' : 'password'"
                    autocomplete="new-password"
                    spellcheck="false"
                    :aria-invalid="!!error || undefined"
                    class="h-9 w-full rounded-lg border border-input bg-background pr-10 pl-3 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 aria-invalid:border-destructive"
                />
                <button
                    type="button"
                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                    :aria-label="
                        visible ? 'Ocultar contraseña' : 'Mostrar contraseña'
                    "
                    @click="visible = !visible"
                >
                    <EyeOff v-if="visible" class="size-4" />
                    <Eye v-else class="size-4" />
                </button>
            </div>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="h-9"
                    @click="generar"
                >
                    <Wand2 /> Generar
                </Button>
                <BotonCopiar
                    v-if="modelo"
                    :texto="modelo"
                    etiqueta="Copiar"
                    mensaje="Contraseña copiada"
                />
            </div>
        </div>
        <p v-if="reglas" class="text-xs text-muted-foreground">
            Requisitos: {{ reglas }}
        </p>
        <p class="flex items-start gap-1.5 text-xs text-muted-foreground">
            <ShieldAlert class="mt-0.5 size-3.5 shrink-0 text-warning" />
            Compártela por un canal seguro. No se volverá a mostrar y el usuario
            deberá cambiarla en su primer inicio de sesión.
        </p>
        <InputError :message="error" />
    </div>
</template>
