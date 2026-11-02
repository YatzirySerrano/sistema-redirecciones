<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import CampoCorreo from '@/components/formularios/CampoCorreo.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import CampoContrasenaTemporal from '@/components/usuarios/CampoContrasenaTemporal.vue';
import SelectorMetodoAcceso from '@/components/usuarios/SelectorMetodoAcceso.vue';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { create, index, store } from '@/routes/admin/usuarios';
import type { Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios y accesos', href: index() },
            { title: 'Nuevo acceso', href: create() },
        ],
    },
});

const props = defineProps<{
    negocio_id: number | null;
    negocio_inicial: Opcion | null;
    tipos: Opcion[];
    /** Roles que puedes asignar, por tipo de cuenta. */
    roles: Record<'admin' | 'cliente', Opcion[]>;
    roles_predeterminados: Record<'admin' | 'cliente', string>;
    passwordRules: string;
}>();

const rolInicial = (tipo: 'admin' | 'cliente') => {
    const predeterminado = props.roles_predeterminados[tipo];

    return props.roles[tipo].some((r) => r.value === predeterminado)
        ? predeterminado
        : (props.roles[tipo][0]?.value ?? '');
};

const form = useForm({
    name: '',
    email: '',
    rol: 'cliente' as 'admin' | 'cliente',
    rol_id: rolInicial('cliente'),
    negocio_id: props.negocio_id !== null ? String(props.negocio_id) : '',
    metodo: 'invitacion',
    password: '',
});

// Al cambiar el tipo de cuenta, el rol debe ser de ese tipo.
watch(
    () => form.rol,
    (tipo) => {
        form.rol_id = rolInicial(tipo);
    },
);

const rolesDisponibles = computed(() => props.roles[form.rol] ?? []);

const guardar = () => {
    form.transform((d) => ({
        ...d,
        negocio_id: d.rol === 'cliente' ? d.negocio_id : null,
        password: d.metodo === 'temporal' ? d.password : '',
    })).post(store.url());
};
</script>

<template>
    <Head title="Nuevo acceso" />

    <EncabezadoPagina
        titulo="Nuevo acceso"
        descripcion="Crea un usuario para un negocio cliente o para el personal de DASTI."
    />

    <form class="space-y-6" novalidate @submit.prevent="guardar">
        <Panel titulo="Datos del usuario">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Nombre completo"
                    para="name"
                    :error="form.errors.name"
                >
                    <Input
                        id="name"
                        v-model="form.name"
                        maxlength="255"
                        autocomplete="off"
                        required
                        :aria-invalid="!!form.errors.name || undefined"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Correo electrónico"
                    para="email"
                    ayuda="Será su usuario para iniciar sesión."
                >
                    <CampoCorreo
                        id="email"
                        v-model="form.email"
                        :error="form.errors.email"
                        autocomplete="off"
                        verificar-duplicado
                        requerido
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Tipo de cuenta"
                    para="rol"
                    :error="form.errors.rol"
                    ayuda="Define el alcance: todos los negocios o solo uno."
                >
                    <SelectNativo
                        id="rol"
                        v-model="form.rol"
                        :opciones="tipos"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Rol"
                    para="rol_id"
                    :error="form.errors.rol_id"
                    ayuda="Define qué acciones podrá realizar."
                >
                    <SelectorBuscable
                        id="rol_id"
                        v-model="form.rol_id"
                        :opciones="rolesDisponibles"
                        etiqueta="Rol"
                        placeholder="Selecciona un rol"
                        placeholder-busqueda="Buscar rol…"
                        sin-resultados="No hay roles que puedas asignar con ese nombre."
                        :invalido="!!form.errors.rol_id"
                    />
                </CampoFormulario>
                <CampoFormulario
                    v-if="form.rol === 'cliente'"
                    etiqueta="Negocio"
                    para="negocio_id"
                    :error="form.errors.negocio_id"
                    class="sm:col-span-2"
                >
                    <SelectorBuscable
                        id="negocio_id"
                        v-model="form.negocio_id"
                        :url="opcionesNegocios.url()"
                        :parametros="{ solo_activos: true }"
                        :seleccion-inicial="negocio_inicial"
                        etiqueta="Negocio"
                        placeholder="Busca y selecciona un negocio activo"
                        placeholder-busqueda="Nombre, responsable o correo…"
                        sin-resultados="Ningún negocio activo coincide con la búsqueda."
                        :invalido="!!form.errors.negocio_id"
                    />
                </CampoFormulario>
            </div>
            <p
                v-if="form.rol === 'admin'"
                class="mt-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                El personal de DASTI opera sobre todos los negocios. Sus
                acciones quedan limitadas a los permisos del rol elegido.
            </p>
            <p
                v-if="rolesDisponibles.length === 0"
                class="mt-4 text-sm text-destructive"
                role="alert"
            >
                No tienes roles que puedas asignar a este tipo de cuenta.
            </p>
        </Panel>

        <Panel titulo="Método de acceso">
            <div class="space-y-5">
                <SelectorMetodoAcceso v-model="form.metodo" nombre="metodo" />
                <CampoContrasenaTemporal
                    v-if="form.metodo === 'temporal'"
                    id="password"
                    v-model="form.password"
                    :reglas="passwordRules"
                    :error="form.errors.password"
                />
            </div>
        </Panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child
                ><Link :href="index()">Cancelar</Link></Button
            >
            <Button
                type="submit"
                :disabled="form.processing || rolesDisponibles.length === 0"
            >
                <Spinner v-if="form.processing" />
                <Save v-else />
                Crear acceso
            </Button>
        </div>
    </form>
</template>
