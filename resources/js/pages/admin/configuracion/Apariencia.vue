<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CircleAlert,
    CircleCheck,
    LayoutDashboard,
    Nfc,
    Palette,
    RotateCcw,
    Save,
    Settings2,
    Undo2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Panel from '@/components/comun/Panel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePermisos } from '@/composables/usePermisos';
import type { Colores } from '@/lib/color';
import { esHex, problemasDeContraste, tokensClaro } from '@/lib/color';
import { edit as configuracion } from '@/routes/admin/configuracion';
import { destroy, update } from '@/routes/admin/personalizacion';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Configuración', href: configuracion() },
            { title: 'Apariencia y personalización', href: '#' },
        ],
    },
});

const props = defineProps<{
    colores: Colores;
    predeterminados: Colores;
    campos: Record<keyof Colores, { etiqueta: string; ayuda: string }>;
    personalizado: boolean;
    puede_editar: boolean;
}>();

const { puede } = usePermisos();
const form = useForm<Colores>({ ...props.colores });

const claves = Object.keys(props.campos) as (keyof Colores)[];

const coloresValidos = computed(() => claves.every((c) => esHex(form[c])));
const problemas = computed(() =>
    coloresValidos.value ? problemasDeContraste({ ...form.data() }) : {},
);
const hayProblemas = computed(() => Object.keys(problemas.value).length > 0);

// Vista previa: variables CSS aplicadas solo a este contenedor, sobre una
// base clara fija (también si el panel está en modo oscuro).
const BASE_CLARA: Record<string, string> = {
    '--foreground': '#17141f',
    '--card': '#ffffff',
    '--card-foreground': '#17141f',
    '--popover': '#ffffff',
    '--muted': '#f3f2f7',
    '--muted-foreground': '#64607a',
    '--border': '#e6e4ee',
    '--input': '#d9d6e3',
    '--destructive': '#dc2626',
    '--success': '#1d7a4f',
    '--success-soft': '#ebf9f1',
    '--warning': '#9a5b0b',
    '--warning-soft': '#fef4e2',
};
const estiloVista = computed(() =>
    coloresValidos.value
        ? { ...BASE_CLARA, ...tokensClaro({ ...form.data() }) }
        : BASE_CLARA,
);

const alEscribirHex = (campo: keyof Colores, valor: string | number) => {
    let texto = String(valor).trim().toLowerCase();

    if (texto !== '' && !texto.startsWith('#')) {
        texto = `#${texto}`;
    }

    form[campo] = texto.slice(0, 7);
};

const usarPredeterminados = () => {
    for (const c of claves) {
        form[c] = props.predeterminados[c];
    }
};

const descartar = () => form.reset();

const guardar = () =>
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => form.defaults({ ...form.data() }),
    });

const dialogoRestablecer = ref(false);
const restableciendo = ref(false);
const restablecer = () =>
    router.delete(destroy.url(), {
        preserveScroll: true,
        onStart: () => (restableciendo.value = true),
        onFinish: () => (restableciendo.value = false),
        onSuccess: () => {
            dialogoRestablecer.value = false;
            for (const c of claves) {
                form[c] = props.predeterminados[c];
            }
            form.defaults({ ...props.predeterminados });
        },
    });

const vistaNavegacion = [
    { titulo: 'Panel', icono: LayoutDashboard, activo: true },
    { titulo: 'Negocios', icono: Building2, activo: false },
    { titulo: 'Productos', icono: Nfc, activo: false },
    { titulo: 'Configuración', icono: Settings2, activo: false },
];
</script>

<template>
    <Head title="Apariencia y personalización" />

    <EncabezadoPagina
        titulo="Apariencia y personalización"
        descripcion="Colores de la interfaz administrativa. Se guardan en la base de datos y se aplican a todos los usuarios sin recompilar. No modifican los colores de los QR de los negocios."
    >
        <template #acciones>
            <Button
                v-if="puede('configuracion.ver')"
                variant="outline"
                as-child
            >
                <Link :href="configuracion()"
                    ><Settings2 /> Ajustes generales</Link
                >
            </Button>
        </template>
    </EncabezadoPagina>

    <form
        class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]"
        novalidate
        @submit.prevent="guardar"
    >
        <Panel
            titulo="Colores"
            :descripcion="
                personalizado
                    ? 'Usando colores personalizados.'
                    : 'Usando la apariencia original de DASTI.'
            "
        >
            <div class="space-y-4">
                <div
                    v-for="campo in claves"
                    :key="campo"
                    class="grid grid-cols-[auto_minmax(0,1fr)_7.5rem] items-center gap-3"
                >
                    <input
                        :id="`color-${campo}`"
                        type="color"
                        :value="esHex(form[campo]) ? form[campo] : '#000000'"
                        :disabled="!puede_editar"
                        class="size-10 cursor-pointer rounded-lg border bg-background p-1 disabled:cursor-not-allowed"
                        :aria-describedby="`ayuda-${campo}`"
                        @input="
                            form[campo] = (
                                $event.target as HTMLInputElement
                            ).value
                        "
                    />
                    <div class="min-w-0">
                        <label
                            :for="`color-${campo}`"
                            class="text-sm font-medium"
                            >{{ campos[campo].etiqueta }}</label
                        >
                        <p
                            :id="`ayuda-${campo}`"
                            class="text-xs text-muted-foreground"
                        >
                            {{ campos[campo].ayuda }}
                        </p>
                        <p
                            v-if="problemas[campo] && !form.errors[campo]"
                            class="mt-1 flex items-start gap-1 text-xs text-warning"
                            role="status"
                        >
                            <CircleAlert
                                class="mt-px size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ problemas[campo] }}
                        </p>
                        <InputError
                            class="mt-1"
                            :message="form.errors[campo]"
                        />
                    </div>
                    <Input
                        :model-value="form[campo]"
                        :aria-label="`${campos[campo].etiqueta} en hexadecimal`"
                        maxlength="7"
                        spellcheck="false"
                        class="font-mono text-xs uppercase"
                        :aria-invalid="
                            !esHex(form[campo]) ||
                            !!form.errors[campo] ||
                            undefined
                        "
                        :disabled="!puede_editar"
                        @update:model-value="alEscribirHex(campo, $event)"
                    />
                </div>
            </div>

            <p
                v-if="!coloresValidos"
                class="mt-4 text-xs text-destructive"
                role="alert"
            >
                Escribe los colores en formato #RRGGBB.
            </p>
            <p
                v-else-if="!hayProblemas"
                class="mt-4 flex items-center gap-1.5 text-xs text-success"
                role="status"
            >
                <CircleCheck class="size-3.5" aria-hidden="true" />
                Los colores tienen contraste suficiente para leerse bien.
            </p>

            <div
                v-if="puede_editar"
                class="mt-6 flex flex-col gap-2 border-t pt-4 sm:flex-row sm:flex-wrap sm:justify-end"
            >
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="!form.isDirty || form.processing"
                    @click="descartar"
                >
                    <Undo2 /> Descartar cambios
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="usarPredeterminados"
                >
                    <Palette /> Cargar colores originales
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    :disabled="!personalizado || form.processing"
                    @click="dialogoRestablecer = true"
                >
                    <RotateCcw /> Restablecer apariencia
                </Button>
                <Button
                    type="submit"
                    :disabled="
                        form.processing ||
                        !form.isDirty ||
                        !coloresValidos ||
                        hayProblemas
                    "
                >
                    <Spinner v-if="form.processing" />
                    <Save v-else />
                    Guardar apariencia
                </Button>
            </div>
            <p v-else class="mt-6 border-t pt-4 text-xs text-muted-foreground">
                Puedes consultar los colores, pero no tienes permiso para
                modificarlos.
            </p>
        </Panel>

        <Panel
            titulo="Vista previa"
            descripcion="Así se verá la interfaz en modo claro. Aún no se guarda."
        >
            <div
                class="overflow-hidden rounded-xl border text-foreground"
                :style="estiloVista"
                aria-label="Vista previa de los colores"
                role="img"
            >
                <div class="flex min-h-80">
                    <!-- Navegación lateral -->
                    <aside
                        class="hidden w-40 shrink-0 flex-col gap-1 bg-sidebar p-3 text-sidebar-foreground sm:flex"
                    >
                        <p
                            class="mb-2 px-2 text-sm font-bold tracking-[0.18em] text-sidebar-accent-foreground"
                        >
                            DASTI
                        </p>
                        <span
                            v-for="item in vistaNavegacion"
                            :key="item.titulo"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs"
                            :class="
                                item.activo
                                    ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
                                    : 'hover:bg-sidebar-accent'
                            "
                        >
                            <component
                                :is="item.icono"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ item.titulo }}
                        </span>
                        <span
                            class="mt-1 flex items-center gap-2 rounded-md bg-sidebar-accent px-2 py-1.5 text-xs text-sidebar-accent-foreground"
                            >Elemento con cursor</span
                        >
                    </aside>

                    <!-- Contenido -->
                    <div class="flex-1 space-y-3 bg-background p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">Productos</p>
                            <span
                                class="rounded-md bg-boton px-2.5 py-1 text-xs font-medium text-boton-foreground"
                                >+ Registrar</span
                            >
                        </div>
                        <div class="flex gap-1 border-b text-xs">
                            <span
                                class="border-b-2 border-primary px-2 pb-1.5 font-medium text-primary"
                                >Tabla</span
                            >
                            <span class="px-2 pb-1.5 text-muted-foreground"
                                >Tarjetas</span
                            >
                        </div>
                        <div
                            class="space-y-3 rounded-xl border bg-card p-3 shadow-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium"
                                    >Tarjeta NFC mostrador</span
                                >
                                <InsigniaEstado estado="activo" />
                            </div>
                            <label class="block text-xs">
                                <span class="text-muted-foreground"
                                    >Destino</span
                                >
                                <span
                                    class="mt-1 flex h-8 items-center rounded-md border border-input bg-background px-2 text-xs ring-2 ring-ring/30"
                                    >https://menu.ejemplo.mx</span
                                >
                            </label>
                            <p class="text-xs">
                                Consulta el
                                <span
                                    class="font-medium text-enlace underline underline-offset-2"
                                    >historial de cambios</span
                                >.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-md bg-boton px-3 py-1.5 text-xs font-medium text-boton-foreground"
                                    >Guardar</span
                                >
                                <span
                                    class="rounded-md bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-foreground"
                                    >Secundario</span
                                >
                                <span
                                    class="rounded-md border bg-background px-3 py-1.5 text-xs font-medium"
                                    >Cancelar</span
                                >
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <InsigniaEstado estado="pendiente" />
                            <InsigniaEstado estado="suspendido" />
                            <span
                                class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                                >Resaltado</span
                            >
                            <span
                                class="rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground"
                                >Principal</span
                            >
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                En modo oscuro se aplican el color principal, el de botones y el
                de enlaces, ajustados automáticamente para conservar el
                contraste; los fondos y la barra lateral mantienen la paleta
                oscura.
            </p>
        </Panel>
    </form>

    <DialogoConfirmacion
        v-model:open="dialogoRestablecer"
        titulo="¿Restablecer la apariencia original?"
        descripcion="Se eliminarán los colores personalizados y toda la plataforma volverá a la apariencia original de DASTI. Queda registrado en la auditoría."
        confirmar="Restablecer"
        destructivo
        :procesando="restableciendo"
        @confirmar="restablecer"
    />
</template>
