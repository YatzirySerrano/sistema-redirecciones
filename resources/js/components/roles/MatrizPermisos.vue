<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import type { ModuloPermisos } from '@/types';

/**
 * Matriz de permisos agrupada por módulo, con «seleccionar todo» por módulo
 * (estado intermedio cuando solo hay algunos). Los permisos bloqueados se
 * muestran pero no se pueden cambiar; el servidor vuelve a validarlo.
 */
const seleccion = defineModel<string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        matriz: ModuloPermisos[];
        ambito: 'plataforma' | 'negocio';
        bloqueado?: (permiso: string) => boolean;
        soloLectura?: boolean;
    }>(),
    { bloqueado: () => false, soloLectura: false },
);

const modulos = computed(() =>
    props.matriz
        .map((m) => ({
            ...m,
            permisos: m.permisos.filter((p) =>
                p.ambitos.includes(props.ambito),
            ),
        }))
        .filter((m) => m.permisos.length > 0),
);

const elegido = (permiso: string) => seleccion.value.includes(permiso);
const editable = (permiso: string) =>
    !props.soloLectura && !props.bloqueado(permiso);

const alternar = (permiso: string, valor: boolean) => {
    if (!editable(permiso)) {
        return;
    }

    seleccion.value = valor
        ? [...new Set([...seleccion.value, permiso])]
        : seleccion.value.filter((p) => p !== permiso);
};

const estadoModulo = (m: ModuloPermisos) => {
    const total = m.permisos.filter((p) => elegido(p.nombre)).length;

    return {
        total,
        todos: total === m.permisos.length,
        alguno: total > 0 && total < m.permisos.length,
    };
};

const alternarModulo = (m: ModuloPermisos, valor: boolean) => {
    const editables = m.permisos
        .map((p) => p.nombre)
        .filter((p) => editable(p));

    seleccion.value = valor
        ? [...new Set([...seleccion.value, ...editables])]
        : seleccion.value.filter((p) => !editables.includes(p));
};
</script>

<template>
    <div class="grid gap-3 md:grid-cols-2">
        <fieldset
            v-for="m in modulos"
            :key="m.clave"
            class="rounded-xl border bg-background"
        >
            <legend class="sr-only">{{ m.etiqueta }}</legend>
            <div
                class="flex items-center justify-between gap-3 border-b bg-muted/30 px-4 py-2.5"
            >
                <label
                    class="flex cursor-pointer items-center gap-2.5 text-sm font-semibold"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded accent-[var(--primary)]"
                        :checked="estadoModulo(m).todos"
                        :indeterminate="estadoModulo(m).alguno"
                        :disabled="
                            soloLectura ||
                            m.permisos.every((p) => !editable(p.nombre))
                        "
                        :aria-label="`Seleccionar todos los permisos de ${m.etiqueta}`"
                        @change="
                            alternarModulo(
                                m,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ m.etiqueta }}
                </label>
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums"
                    :class="
                        estadoModulo(m).total > 0
                            ? 'bg-accent text-accent-foreground'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ estadoModulo(m).total }}/{{ m.permisos.length }}</span
                >
            </div>
            <ul class="divide-y">
                <li v-for="p in m.permisos" :key="p.nombre">
                    <label
                        class="flex items-start gap-3 px-4 py-2.5 text-sm"
                        :class="
                            editable(p.nombre)
                                ? 'cursor-pointer hover:bg-muted/30'
                                : 'cursor-not-allowed opacity-70'
                        "
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 size-4 rounded accent-[var(--primary)]"
                            :checked="elegido(p.nombre)"
                            :disabled="!editable(p.nombre)"
                            :value="p.nombre"
                            @change="
                                alternar(
                                    p.nombre,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5 font-medium"
                                >{{ p.etiqueta }}
                                <Lock
                                    v-if="!soloLectura && bloqueado(p.nombre)"
                                    class="size-3 text-muted-foreground"
                                    aria-label="No puedes cambiar este permiso"
                            /></span>
                            <span class="block text-xs text-muted-foreground">{{
                                p.descripcion
                            }}</span>
                        </span>
                    </label>
                </li>
            </ul>
        </fieldset>
    </div>
</template>
