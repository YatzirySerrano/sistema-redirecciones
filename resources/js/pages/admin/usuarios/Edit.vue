<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    CircleCheck,
    KeyRound,
    Mail,
    Save,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import CampoCorreo from '@/components/formularios/CampoCorreo.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import CampoContrasenaTemporal from '@/components/usuarios/CampoContrasenaTemporal.vue';
import {
    fechaCorta,
    fechaHora,
    pendienteDeAcceso,
    relativo,
} from '@/lib/formato';
import { show as verNegocio } from '@/routes/admin/negocios';
import {
    contrasena,
    estado,
    index,
    invitacion,
    rol as rutaRol,
    update,
} from '@/routes/admin/usuarios';
import type {
    Coleccion,
    Opcion,
    Recurso,
    RegistroAuditoria,
    Usuario,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios y accesos', href: index() },
            { title: 'Editar acceso', href: '#' },
        ],
    },
});

const props = defineProps<{
    usuario: Recurso<Usuario>;
    auditoria: Coleccion<RegistroAuditoria>;
    passwordRules: string;
    rol: {
        actual: {
            id: number;
            nombre: string;
            descripcion: string | null;
        } | null;
        permisos_efectivos: { nombre: string; etiqueta: string }[];
        opciones: Opcion[];
        puede_cambiar: boolean;
    };
    puede: { editar: boolean; gestionar_accesos: boolean };
}>();

const u = computed(() => props.usuario.data);
const suspendido = computed(() => u.value.estado === 'suspendido');

// Datos
const form = useForm({ name: u.value.name, email: u.value.email });
const guardar = () =>
    form.put(update.url(u.value.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults({ name: form.name, email: form.email }),
    });

// Estado
const dialogoEstado = ref(false);
const motivo = ref('');
const procesandoEstado = ref(false);
const errorEstado = ref<string | undefined>();

const cambiarEstado = () => {
    errorEstado.value = undefined;
    router.patch(
        estado.url(u.value.id),
        {
            estado: suspendido.value ? 'activo' : 'suspendido',
            motivo: motivo.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => (procesandoEstado.value = true),
            onFinish: () => (procesandoEstado.value = false),
            onSuccess: () => {
                dialogoEstado.value = false;
                motivo.value = '';
            },
            onError: (e) => {
                errorEstado.value = e.usuario ?? e.estado;
                dialogoEstado.value = false;
            },
        },
    );
};

// Invitación
const dialogoInvitacion = ref(false);
const enviandoInvitacion = ref(false);

const enviarInvitacion = () => {
    router.post(
        invitacion.url(u.value.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (enviandoInvitacion.value = true),
            onFinish: () => {
                enviandoInvitacion.value = false;
                dialogoInvitacion.value = false;
            },
        },
    );
};

// Rol
const formRol = useForm({ rol_id: String(props.rol.actual?.id ?? '') });
const cambiarRol = () =>
    formRol.put(rutaRol.url(u.value.id), {
        preserveScroll: true,
        onSuccess: () => formRol.defaults({ rol_id: formRol.rol_id }),
    });

// Permisos efectivos agrupados por módulo («Productos · Ver»).
const permisosAgrupados = computed(() => {
    const grupos = new Map<string, string[]>();

    for (const permiso of props.rol.permisos_efectivos) {
        const [modulo, accion] = permiso.etiqueta.split(' · ');
        grupos.set(modulo, [...(grupos.get(modulo) ?? []), accion ?? modulo]);
    }

    return [...grupos.entries()];
});

// Contraseña temporal
const formClave = useForm({ password: '' });
const asignarClave = () =>
    formClave.put(contrasena.url(u.value.id), {
        preserveScroll: true,
        onSuccess: () => formClave.reset(),
    });
</script>

<template>
    <Head :title="u.name" />

    <EncabezadoPagina :titulo="u.name" :descripcion="u.email">
        <template #detalle>
            <div
                class="flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground"
            >
                <InsigniaEstado
                    :estado="u.estado"
                    :etiqueta="u.estado_etiqueta"
                />
                <span
                    class="inline-flex items-center gap-1"
                    :class="u.rol === 'admin' && 'text-primary'"
                >
                    <ShieldCheck
                        v-if="u.rol === 'admin'"
                        class="size-3.5"
                        aria-hidden="true"
                    />{{ u.rol_etiqueta }}
                    <template v-if="rol.actual">
                        · {{ rol.actual.nombre }}</template
                    >
                </span>
                <template v-if="u.negocio">
                    ·
                    <Link
                        :href="verNegocio(u.negocio.id)"
                        class="inline-flex items-center gap-1 hover:text-primary hover:underline"
                    >
                        <Building2 class="size-3.5" aria-hidden="true" />{{
                            u.negocio.nombre_comercial
                        }}
                    </Link>
                </template>
            </div>
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <Panel
                titulo="Datos del usuario"
                descripcion="El correo es su usuario de inicio de sesión."
            >
                <form class="space-y-5" novalidate @submit.prevent="guardar">
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
                                :disabled="!puede.editar"
                            />
                        </CampoFormulario>
                        <CampoFormulario
                            etiqueta="Correo electrónico"
                            para="email"
                        >
                            <CampoCorreo
                                v-if="puede.editar"
                                id="email"
                                v-model="form.email"
                                :error="form.errors.email"
                                autocomplete="off"
                                verificar-duplicado
                                :ignorar-id="u.id"
                                requerido
                            />
                            <Input
                                v-else
                                id="email"
                                :model-value="form.email"
                                disabled
                            />
                        </CampoFormulario>
                    </div>
                    <div v-if="puede.editar" class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.isDirty"
                        >
                            <Spinner v-if="form.processing" />
                            <Save v-else />
                            Guardar cambios
                        </Button>
                    </div>
                </form>
            </Panel>

            <Panel
                titulo="Rol y permisos"
                descripcion="El rol define qué acciones puede realizar. El alcance (todos los negocios o solo el suyo) lo define el tipo de cuenta."
            >
                <form
                    v-if="rol.puede_cambiar"
                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    novalidate
                    @submit.prevent="cambiarRol"
                >
                    <CampoFormulario
                        etiqueta="Rol asignado"
                        para="rol_id"
                        :error="formRol.errors.rol_id"
                        class="flex-1"
                    >
                        <SelectorBuscable
                            id="rol_id"
                            v-model="formRol.rol_id"
                            :opciones="rol.opciones"
                            etiqueta="Rol asignado"
                            placeholder="Selecciona un rol"
                            placeholder-busqueda="Buscar rol…"
                            :invalido="!!formRol.errors.rol_id"
                        />
                    </CampoFormulario>
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="formRol.processing || !formRol.isDirty"
                    >
                        <Spinner v-if="formRol.processing" />
                        <ShieldCheck v-else />
                        Cambiar rol
                    </Button>
                </form>
                <div v-else class="text-sm">
                    <p class="font-medium">
                        {{ rol.actual?.nombre ?? 'Sin rol asignado' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            u.es_actual
                                ? 'No puedes cambiar tu propio rol.'
                                : 'No tienes permiso para cambiar el rol de este usuario.'
                        }}
                    </p>
                </div>

                <div class="mt-5 border-t pt-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        Permisos efectivos ({{ rol.permisos_efectivos.length }})
                    </p>
                    <ul
                        v-if="permisosAgrupados.length > 0"
                        class="mt-2 grid gap-2 sm:grid-cols-2"
                    >
                        <li
                            v-for="[modulo, acciones] in permisosAgrupados"
                            :key="modulo"
                            class="rounded-lg bg-muted/40 px-3 py-2 text-sm"
                        >
                            <span class="font-medium">{{ modulo }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                acciones.join(' · ')
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Sin permisos: solo puede administrar su cuenta.
                    </p>
                </div>
            </Panel>

            <Panel
                v-if="puede.gestionar_accesos"
                titulo="Contraseña temporal"
                descripcion="Reemplaza la contraseña actual; el usuario deberá cambiarla al entrar."
            >
                <form
                    class="space-y-4"
                    novalidate
                    @submit.prevent="asignarClave"
                >
                    <CampoContrasenaTemporal
                        id="password-temporal"
                        v-model="formClave.password"
                        :reglas="passwordRules"
                        :error="formClave.errors.password"
                    />
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="
                                formClave.processing ||
                                formClave.password === ''
                            "
                        >
                            <Spinner v-if="formClave.processing" />
                            <KeyRound v-else />
                            Asignar contraseña temporal
                        </Button>
                    </div>
                </form>
            </Panel>
        </div>

        <div class="space-y-6">
            <Panel titulo="Acceso">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Último acceso</dt>
                        <dd :title="fechaHora(u.ultimo_acceso_en)">
                            {{ relativo(u.ultimo_acceso_en) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Alta</dt>
                        <dd>{{ fechaCorta(u.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">
                            Verificación en dos pasos
                        </dt>
                        <dd
                            :class="
                                u.two_factor
                                    ? 'text-success'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ u.two_factor ? 'Activada' : 'No activada' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Contraseña</dt>
                        <dd
                            :class="
                                pendienteDeAcceso(u)?.error
                                    ? 'text-destructive'
                                    : pendienteDeAcceso(u) && 'text-warning'
                            "
                        >
                            {{
                                pendienteDeAcceso(u)?.texto ??
                                'Definida por el usuario'
                            }}
                        </dd>
                    </div>
                </dl>
                <div
                    v-if="puede.gestionar_accesos"
                    class="mt-4 space-y-2 border-t pt-4"
                >
                    <Button
                        variant="outline"
                        class="w-full"
                        :disabled="suspendido"
                        @click="dialogoInvitacion = true"
                    >
                        <Mail /> Enviar enlace de acceso
                    </Button>
                    <p class="text-xs text-muted-foreground">
                        Envía un correo para que defina una nueva contraseña.
                    </p>
                </div>
            </Panel>

            <Panel v-if="puede.gestionar_accesos" titulo="Estado del acceso">
                <p class="text-sm text-muted-foreground">
                    {{
                        suspendido
                            ? 'El usuario no puede iniciar sesión.'
                            : 'Al suspender se cierran sus sesiones activas de inmediato.'
                    }}
                </p>
                <Button
                    class="mt-4 w-full"
                    :variant="suspendido ? 'default' : 'outline'"
                    :class="
                        !suspendido && 'text-destructive hover:text-destructive'
                    "
                    :disabled="u.es_actual"
                    @click="dialogoEstado = true"
                >
                    <CircleCheck v-if="suspendido" />
                    <Ban v-else />
                    {{ suspendido ? 'Reactivar acceso' : 'Suspender acceso' }}
                </Button>
                <p
                    v-if="u.es_actual"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    No puedes suspender tu propio acceso.
                </p>
                <InputError class="mt-2" :message="errorEstado" />
            </Panel>
        </div>
    </div>

    <Panel
        titulo="Actividad"
        descripcion="Operaciones realizadas por este usuario o sobre su acceso."
    >
        <ListaAuditoria :registros="auditoria.data" mostrar-negocio />
    </Panel>

    <DialogoConfirmacion
        v-model:open="dialogoEstado"
        :titulo="suspendido ? '¿Reactivar el acceso?' : '¿Suspender el acceso?'"
        :descripcion="
            suspendido
                ? `${u.email} podrá volver a iniciar sesión.`
                : `${u.email} no podrá iniciar sesión y sus sesiones se cerrarán.`
        "
        :confirmar="suspendido ? 'Reactivar' : 'Suspender'"
        :destructivo="!suspendido"
        :procesando="procesandoEstado"
        @confirmar="cambiarEstado"
    >
        <div class="grid gap-2">
            <Label for="motivo-usuario"
                >Motivo
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                ></Label
            >
            <Textarea
                id="motivo-usuario"
                v-model="motivo"
                rows="2"
                maxlength="255"
            />
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        v-model:open="dialogoInvitacion"
        titulo="¿Enviar enlace de acceso?"
        :descripcion="`Se enviará a ${u.email} un enlace para definir su contraseña. El enlace caduca después de un tiempo.`"
        confirmar="Enviar enlace"
        :procesando="enviandoInvitacion"
        @confirmar="enviarInvitacion"
    />
</template>
