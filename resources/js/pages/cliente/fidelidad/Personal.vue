<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CirclePause, CirclePlay, UserCog, UserPlus } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type { EstadoServicio } from '@/components/fidelidad/tipos';
import CampoCorreo from '@/components/formularios/CampoCorreo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { relativo } from '@/lib/formato';
import { inicio } from '@/routes/cliente/fidelidad';
import { estado, index, rol, store } from '@/routes/cliente/fidelidad/personal';
import type { Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Personal autorizado', href: index() },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    usuarios: {
        id: number;
        name: string;
        email: string;
        estado: string;
        estado_etiqueta: string;
        rol_id: number | null;
        rol: string | null;
        invitacion: string | null;
        ultimo_acceso_en: string | null;
        es_actual: boolean;
    }[];
    roles: (Opcion & { descripcion: string })[];
}>();

const form = useForm({
    name: '',
    email: '',
    rol_id:
        props.roles.find((r) => r.label === 'Empleado')?.value ??
        props.roles[0]?.value ??
        '',
});
const invitar = () =>
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email'),
    });

const cambiarRol = (id: number, valor: string) =>
    router.put(rol.url(id), { rol_id: valor }, { preserveScroll: true });
const cambiarEstado = (id: number, valor: string) =>
    router.patch(estado.url(id), { estado: valor }, { preserveScroll: true });
const puedeAsignar = (rolId: number | null) =>
    rolId === null || props.roles.some((r) => r.value === String(rolId));
</script>

<template>
    <Head title="Personal autorizado" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        titulo="Personal autorizado"
        descripcion="Quién puede registrar visitas y entregar recompensas en tu negocio."
        ayuda="Cada visita y canje queda registrado con el nombre de quien lo autorizó. Revisa «Historial y actividad» para detectar patrones extraños."
        :icono="UserCog"
    />

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]"
    >
        <Panel titulo="Equipo" sin-relleno>
            <ul class="divide-y">
                <li
                    v-for="u in usuarios"
                    :key="u.id"
                    class="flex flex-wrap items-center gap-3 px-5 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ u.name
                            }}<span
                                v-if="u.es_actual"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >(tú)</span
                            >
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ u.email }} ·
                            {{
                                u.invitacion === 'pendiente'
                                    ? 'Invitación pendiente'
                                    : `Último acceso ${relativo(u.ultimo_acceso_en)}`
                            }}
                        </p>
                    </div>
                    <SelectNativo
                        v-if="!u.es_actual && puedeAsignar(u.rol_id)"
                        :model-value="String(u.rol_id ?? '')"
                        :opciones="roles"
                        :etiqueta="`Rol de ${u.name}`"
                        class="w-40"
                        @update:model-value="(v: string) => cambiarRol(u.id, v)"
                    />
                    <span v-else class="text-sm text-muted-foreground">{{
                        u.rol ?? 'Sin rol'
                    }}</span>
                    <InsigniaEstado
                        :estado="u.estado"
                        :etiqueta="u.estado_etiqueta"
                    />
                    <Button
                        v-if="!u.es_actual && puedeAsignar(u.rol_id)"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="
                            u.estado === 'activo'
                                ? `Suspender a ${u.name}`
                                : `Reactivar a ${u.name}`
                        "
                        :title="
                            u.estado === 'activo' ? 'Suspender' : 'Reactivar'
                        "
                        @click="
                            cambiarEstado(
                                u.id,
                                u.estado === 'activo' ? 'suspendido' : 'activo',
                            )
                        "
                    >
                        <CirclePause v-if="u.estado === 'activo'" />
                        <CirclePlay v-else />
                    </Button>
                </li>
            </ul>
        </Panel>

        <Panel
            titulo="Invitar a alguien"
            descripcion="Recibirá un correo para definir su contraseña."
        >
            <form class="space-y-4" novalidate @submit.prevent="invitar">
                <CampoFormulario
                    etiqueta="Nombre"
                    para="name"
                    :error="form.errors.name"
                >
                    <Input
                        id="name"
                        v-model="form.name"
                        maxlength="255"
                        autocomplete="off"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Correo" para="email">
                    <CampoCorreo
                        id="email"
                        v-model="form.email"
                        :error="form.errors.email"
                        autocomplete="off"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Rol"
                    para="rol_id"
                    :error="form.errors.rol_id"
                >
                    <SelectNativo
                        id="rol_id"
                        v-model="form.rol_id"
                        :opciones="roles"
                    />
                </CampoFormulario>
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || !servicio.activo"
                >
                    <Spinner v-if="form.processing" />
                    <UserPlus v-else />
                    Enviar invitación
                </Button>
            </form>
        </Panel>
    </div>
</template>
