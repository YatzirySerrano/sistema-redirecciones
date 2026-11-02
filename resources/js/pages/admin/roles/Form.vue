<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, LockKeyhole, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import InputError from '@/components/InputError.vue';
import MatrizPermisos from '@/components/roles/MatrizPermisos.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/roles';
import type { ModuloPermisos, Opcion, Recurso, Rol } from '@/types';

const props = defineProps<{
    rol: Recurso<Rol> | null;
    matriz: ModuloPermisos[];
    ambitos: Opcion[];
    otorgables: string[];
    puede_administrar_permisos: boolean;
    es_superadministrador: boolean;
    rol_propio_id: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles y permisos', href: index() },
            { title: 'Rol', href: '#' },
        ],
    },
});

const r = computed(() => props.rol?.data ?? null);
const editando = computed(() => r.value !== null);

const form = useForm({
    name: r.value?.nombre ?? '',
    descripcion: r.value?.descripcion ?? '',
    ambito: (r.value?.ambito ?? 'plataforma') as 'plataforma' | 'negocio',
    permisos: [...(r.value?.permisos ?? [])],
});

// Al cambiar el tipo de cuenta se descartan los permisos que no admite.
watch(
    () => form.ambito,
    (ambito) => {
        const admitidos = new Set(
            props.matriz
                .flatMap((m) => m.permisos)
                .filter((p) => p.ambitos.includes(ambito))
                .map((p) => p.nombre),
        );
        form.permisos = form.permisos.filter((p) => admitidos.has(p));
    },
);

const esPropio = computed(
    () =>
        r.value !== null &&
        r.value.id === props.rol_propio_id &&
        !props.es_superadministrador,
);

const soloLectura = computed(
    () =>
        !props.puede_administrar_permisos ||
        (r.value?.protegido ?? false) ||
        esPropio.value,
);

// Personal: solo otorga o retira permisos de plataforma que tiene.
const bloqueado = (permiso: string) =>
    form.ambito === 'plataforma' &&
    !props.es_superadministrador &&
    !props.otorgables.includes(permiso);

const guardar = () => {
    form.transform((d) => ({
        name: d.name,
        descripcion: d.descripcion,
        permisos: d.permisos,
        ...(editando.value ? {} : { ambito: d.ambito }),
    }));

    if (r.value) {
        form.put(update.url(r.value.id));
    } else {
        form.post(store.url());
    }
};

const errorPermisos = computed(
    () =>
        form.errors.permisos ??
        Object.entries(form.errors).find(([k]) =>
            k.startsWith('permisos.'),
        )?.[1],
);
</script>

<template>
    <Head :title="r ? `Editar ${r.nombre}` : 'Nuevo rol'" />

    <EncabezadoPagina
        :titulo="r ? `Editar rol «${r.nombre}»` : 'Nuevo rol'"
        descripcion="Elige qué acciones podrán realizar los usuarios con este rol."
    />

    <form class="space-y-6" novalidate @submit.prevent="guardar">
        <Panel titulo="Información del rol">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Nombre del rol"
                    para="name"
                    :error="form.errors.name"
                    :ayuda="
                        r?.del_sistema
                            ? 'Los roles del sistema conservan su nombre.'
                            : 'Por ejemplo: Operador DASTI, Soporte, Supervisor.'
                    "
                >
                    <Input
                        id="name"
                        v-model="form.name"
                        maxlength="60"
                        autocomplete="off"
                        required
                        :disabled="r?.del_sistema"
                        :aria-invalid="!!form.errors.name || undefined"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Tipo de cuenta"
                    para="ambito"
                    :error="form.errors.ambito"
                    :ayuda="
                        editando
                            ? 'No se puede cambiar después de crear el rol.'
                            : 'Un rol de cliente solo opera dentro de su negocio.'
                    "
                >
                    <div
                        id="ambito"
                        role="radiogroup"
                        class="grid grid-cols-2 gap-2"
                    >
                        <label
                            v-for="a in ambitos"
                            :key="a.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-accent has-disabled:cursor-not-allowed has-disabled:opacity-60"
                        >
                            <input
                                v-model="form.ambito"
                                type="radio"
                                name="ambito"
                                :value="a.value"
                                :disabled="editando"
                                class="accent-[var(--primary)]"
                            />
                            {{ a.label }}
                        </label>
                    </div>
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Descripción"
                    para="descripcion"
                    :error="form.errors.descripcion"
                    opcional
                    class="sm:col-span-2"
                >
                    <Textarea
                        id="descripcion"
                        v-model="form.descripcion"
                        rows="2"
                        maxlength="255"
                    />
                </CampoFormulario>
            </div>
        </Panel>

        <Panel
            titulo="Permisos"
            :descripcion="`${form.permisos.length} permisos seleccionados.`"
        >
            <p
                v-if="r?.protegido"
                class="mb-4 flex items-start gap-2 rounded-lg bg-accent p-3 text-sm text-accent-foreground"
            >
                <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                El superadministrador siempre tiene todos los permisos. Está
                protegido para evitar que la plataforma quede sin control.
            </p>
            <p
                v-else-if="esPropio"
                class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                Es tu propio rol: no puedes cambiar sus permisos.
            </p>
            <p
                v-else-if="!puede_administrar_permisos"
                class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                No tienes permiso para administrar los permisos de los roles.
            </p>
            <p
                v-else-if="
                    form.ambito === 'plataforma' && !es_superadministrador
                "
                class="mb-4 text-xs text-muted-foreground"
            >
                Los permisos con candado no los tienes tú, así que no puedes
                otorgarlos ni retirarlos.
            </p>

            <MatrizPermisos
                v-model="form.permisos"
                :matriz="matriz"
                :ambito="form.ambito"
                :bloqueado="bloqueado"
                :solo-lectura="soloLectura"
            />
            <InputError class="mt-3" :message="errorPermisos" />
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child>
                <Link :href="r ? show(r.id) : index()">Cancelar</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Save v-else />
                {{ r ? 'Guardar cambios' : 'Crear rol' }}
            </Button>
        </div>
    </form>
</template>
