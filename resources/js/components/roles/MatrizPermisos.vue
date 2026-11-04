<script setup lang="ts">
import { Building2, Lock, Search, ShieldCheck, Store, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import type { ModuloPermisos, PermisoCatalogo } from '@/types';

/**
 * Matriz de permisos con TODOS los módulos del sistema, agrupados en
 * «Administración de la plataforma» (solo personal DASTI) y «Servicios y
 * operación de los negocios». Los permisos que no aplican al tipo de cuenta
 * del rol se muestran como referencia (no se pueden marcar), para que se vea
 * el catálogo completo. Incluye búsqueda, «seleccionar todo» por módulo y
 * secciones dentro de los módulos grandes. Los permisos bloqueados se ven
 * pero no se pueden cambiar; el servidor vuelve a validarlo.
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

const busqueda = ref('');
const soloElegidos = ref(false);

const normalizar = (t: string) =>
    t
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

const aplica = (p: PermisoCatalogo) => p.ambitos.includes(props.ambito);
const elegido = (permiso: string) => seleccion.value.includes(permiso);
const editable = (p: PermisoCatalogo) =>
    aplica(p) && !props.soloLectura && !props.bloqueado(p.nombre);

const coincide = (m: ModuloPermisos, p: PermisoCatalogo) => {
    const q = normalizar(busqueda.value.trim());

    if (soloElegidos.value && !elegido(p.nombre)) {
        return false;
    }

    return (
        q === '' ||
        normalizar(
            `${m.etiqueta} ${m.descripcion} ${p.etiqueta} ${p.descripcion} ${p.seccion ?? ''} ${p.nombre}`,
        ).includes(q)
    );
};

type Vista = ModuloPermisos & {
    aplicables: PermisoCatalogo[];
    otros: PermisoCatalogo[];
    secciones: { nombre: string | null; permisos: PermisoCatalogo[] }[];
};

const vistas = computed<Vista[]>(() =>
    props.matriz
        .map((m) => {
            const visibles = m.permisos.filter((p) => coincide(m, p));
            const aplicables = visibles.filter(aplica);
            const secciones: Vista['secciones'] = [];

            for (const p of aplicables) {
                const nombre = p.seccion ?? null;
                const actual = secciones.find((s) => s.nombre === nombre);

                if (actual) {
                    actual.permisos.push(p);
                } else {
                    secciones.push({ nombre, permisos: [p] });
                }
            }

            return {
                ...m,
                aplicables,
                otros: visibles.filter((p) => !aplica(p)),
                secciones,
            };
        })
        .filter((m) => m.aplicables.length > 0 || m.otros.length > 0),
);

const GRUPOS = computed(() => [
    {
        clave: 'plataforma',
        titulo: 'Administración de la plataforma',
        descripcion:
            'Funciones internas de DASTI. Nunca se otorgan a clientes.',
        icono: ShieldCheck,
        modulos: vistas.value.filter((m) => m.grupo === 'plataforma'),
    },
    {
        clave: 'negocio',
        titulo: 'Servicios y operación de los negocios',
        descripcion:
            props.ambito === 'plataforma'
                ? 'Para el personal DASTI aplican la supervisión y el diseño para negocios. Los demás se asignan en los roles de cliente.'
                : 'Lo que el usuario puede hacer dentro de SU negocio. Los servicios adicionales además requieren contratación.',
        icono: props.ambito === 'plataforma' ? Building2 : Store,
        modulos: vistas.value.filter((m) => m.grupo !== 'plataforma'),
    },
]);

const estadoModulo = (m: Vista) => {
    const todos = m.permisos.filter(aplica);
    const total = todos.filter((p) => elegido(p.nombre)).length;

    return {
        total,
        disponibles: todos.length,
        todos: todos.length > 0 && total === todos.length,
        alguno: total > 0 && total < todos.length,
    };
};

const alternar = (p: PermisoCatalogo, valor: boolean) => {
    if (!editable(p)) {
        return;
    }

    seleccion.value = valor
        ? [...new Set([...seleccion.value, p.nombre])]
        : seleccion.value.filter((n) => n !== p.nombre);
};

const alternarModulo = (m: Vista, valor: boolean) => {
    const editables = m.permisos.filter(editable).map((p) => p.nombre);

    seleccion.value = valor
        ? [...new Set([...seleccion.value, ...editables])]
        : seleccion.value.filter((p) => !editables.includes(p));
};

const totalVisibles = computed(() =>
    vistas.value.reduce((n, m) => n + m.aplicables.length + m.otros.length, 0),
);
const etiquetaOtroAmbito = computed(() =>
    props.ambito === 'plataforma'
        ? 'Solo cuentas de cliente'
        : 'Solo personal DASTI',
);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Buscar módulos o permisos</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    v-model="busqueda"
                    type="search"
                    class="h-10 w-full rounded-lg border bg-background pr-9 pl-9 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40"
                    placeholder="Buscar: diseño, reembolso, campañas…"
                />
                <button
                    v-if="busqueda"
                    type="button"
                    class="absolute top-1/2 right-1.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                    aria-label="Limpiar búsqueda"
                    @click="busqueda = ''"
                >
                    <X class="size-4" />
                </button>
            </label>
            <label
                class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm"
            >
                <input
                    v-model="soloElegidos"
                    type="checkbox"
                    class="size-4 rounded accent-[var(--primary)]"
                />
                Solo los marcados
            </label>
        </div>

        <p
            v-if="totalVisibles === 0"
            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            Ningún permiso coincide con la búsqueda.
        </p>

        <section
            v-for="g in GRUPOS.filter((g) => g.modulos.length > 0)"
            :key="g.clave"
            class="space-y-3"
            :aria-labelledby="`grupo-${g.clave}`"
        >
            <header class="flex items-start gap-2.5">
                <component
                    :is="g.icono"
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <h3 :id="`grupo-${g.clave}`" class="text-sm font-semibold">
                        {{ g.titulo }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ g.descripcion }}
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <fieldset
                    v-for="m in g.modulos"
                    :key="m.clave"
                    class="min-w-0 rounded-xl border bg-background"
                >
                    <legend class="sr-only">{{ m.etiqueta }}</legend>
                    <div
                        class="flex items-center justify-between gap-3 border-b bg-muted/30 px-4 py-2.5"
                    >
                        <div class="flex min-w-0 items-center gap-1.5">
                            <label
                                class="flex min-w-0 cursor-pointer items-center gap-2.5 text-sm font-semibold"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 shrink-0 rounded accent-[var(--primary)]"
                                    :checked="estadoModulo(m).todos"
                                    :indeterminate="estadoModulo(m).alguno"
                                    :disabled="
                                        soloLectura ||
                                        m.permisos.every((p) => !editable(p))
                                    "
                                    :aria-label="`Seleccionar todos los permisos de ${m.etiqueta}`"
                                    @change="
                                        alternarModulo(
                                            m,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span class="truncate">{{ m.etiqueta }}</span>
                            </label>
                            <Ayuda
                                :texto="m.descripcion"
                                :etiqueta="m.etiqueta"
                            />
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums"
                            :class="
                                estadoModulo(m).total > 0
                                    ? 'bg-accent text-accent-foreground'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ estadoModulo(m).total }}/{{
                                estadoModulo(m).disponibles
                            }}</span
                        >
                    </div>

                    <template v-for="s in m.secciones" :key="s.nombre ?? '-'">
                        <p
                            v-if="s.nombre && m.secciones.length > 1"
                            class="border-b bg-muted/10 px-4 pt-2.5 pb-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ s.nombre }}
                        </p>
                        <ul class="divide-y">
                            <li v-for="p in s.permisos" :key="p.nombre">
                                <label
                                    class="flex items-start gap-3 px-4 py-2.5 text-sm"
                                    :class="
                                        editable(p)
                                            ? 'cursor-pointer hover:bg-muted/30'
                                            : 'cursor-not-allowed opacity-70'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 size-4 shrink-0 rounded accent-[var(--primary)]"
                                        :checked="elegido(p.nombre)"
                                        :disabled="!editable(p)"
                                        :value="p.nombre"
                                        @change="
                                            alternar(
                                                p,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="flex items-center gap-1.5 font-medium"
                                            >{{ p.etiqueta }}
                                            <Lock
                                                v-if="
                                                    !soloLectura &&
                                                    bloqueado(p.nombre)
                                                "
                                                class="size-3 text-muted-foreground"
                                                aria-label="No puedes cambiar este permiso porque tú no lo tienes"
                                        /></span>
                                        <span
                                            class="block text-xs text-muted-foreground"
                                            >{{ p.descripcion }}</span
                                        >
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </template>

                    <details v-if="m.otros.length > 0" class="group border-t">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2 text-xs text-muted-foreground hover:bg-muted/30"
                        >
                            <span
                                >{{ m.otros.length }}
                                {{
                                    m.otros.length === 1
                                        ? 'permiso no aplica'
                                        : 'permisos no aplican'
                                }}
                                a este tipo de cuenta</span
                            >
                            <span class="group-open:hidden">Ver</span>
                            <span class="hidden group-open:inline"
                                >Ocultar</span
                            >
                        </summary>
                        <ul class="divide-y border-t bg-muted/10">
                            <li
                                v-for="p in m.otros"
                                :key="p.nombre"
                                class="flex items-start justify-between gap-3 px-4 py-2 text-xs"
                            >
                                <span class="min-w-0">
                                    <span class="font-medium">{{
                                        p.etiqueta
                                    }}</span>
                                    <span class="block text-muted-foreground">{{
                                        p.descripcion
                                    }}</span>
                                </span>
                                <span
                                    class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                                    >{{ etiquetaOtroAmbito }}</span
                                >
                            </li>
                        </ul>
                    </details>
                </fieldset>
            </div>
        </section>
    </div>
</template>
