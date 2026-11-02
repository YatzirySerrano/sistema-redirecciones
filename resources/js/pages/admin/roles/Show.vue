<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LockKeyhole, Pencil, Trash2, UsersRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import MatrizPermisos from '@/components/roles/MatrizPermisos.vue';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/composables/usePermisos';
import { fechaCorta, fechaHora, numero } from '@/lib/formato';
import { destroy, edit, index } from '@/routes/admin/roles';
import { edit as editarUsuario } from '@/routes/admin/usuarios';
import type {
    Coleccion,
    ModuloPermisos,
    Recurso,
    RegistroAuditoria,
    Rol,
    Usuario,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles y permisos', href: index() },
            { title: 'Detalle del rol', href: '#' },
        ],
    },
});

const props = defineProps<{
    rol: Recurso<Rol>;
    matriz: ModuloPermisos[];
    usuarios: Coleccion<Usuario>;
    historial: Coleccion<RegistroAuditoria>;
    puede: { editar: boolean; eliminar: boolean };
}>();

const r = computed(() => props.rol.data);
const permisos = ref<string[]>([...(r.value.permisos ?? [])]);
const { puede: tienePermiso } = usePermisos();

const dialogoEliminar = ref(false);
const eliminando = ref(false);
const eliminar = () =>
    router.delete(destroy.url(r.value.id), {
        onStart: () => (eliminando.value = true),
        onFinish: () => (eliminando.value = false),
    });
</script>

<template>
    <Head :title="r.nombre" />

    <EncabezadoPagina :titulo="r.nombre" :descripcion="r.descripcion ?? ''">
        <template #detalle>
            <div
                class="flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground"
            >
                <span>{{ r.ambito_etiqueta }}</span>
                <span
                    v-if="r.protegido"
                    class="inline-flex items-center gap-1 text-primary"
                    ><LockKeyhole class="size-3.5" aria-hidden="true" />
                    Protegido</span
                >
                <span v-else-if="r.del_sistema">· Rol del sistema</span>
            </div>
        </template>
        <template #acciones>
            <Button v-if="puede.editar" variant="outline" as-child>
                <Link :href="edit(r.id)"><Pencil /> Editar</Link>
            </Button>
            <Button
                v-if="puede.eliminar"
                variant="outline"
                class="text-destructive hover:text-destructive"
                @click="dialogoEliminar = true"
            >
                <Trash2 /> Eliminar
            </Button>
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Usuarios"
            :valor="numero(r.usuarios_count ?? 0)"
        />
        <TarjetaIndicador
            etiqueta="Permisos"
            :valor="numero(r.permisos_count ?? 0)"
        />
        <TarjetaIndicador etiqueta="Creado" :valor="fechaCorta(r.created_at)" />
        <TarjetaIndicador
            etiqueta="Última modificación"
            :valor="fechaCorta(r.updated_at)"
        />
    </div>

    <Panel
        titulo="Permisos asignados"
        :descripcion="
            r.protegido
                ? 'El superadministrador siempre tiene todos los permisos.'
                : 'Para cambiarlos, edita el rol.'
        "
    >
        <MatrizPermisos
            v-model="permisos"
            :matriz="matriz"
            :ambito="r.ambito"
            solo-lectura
        />
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Usuarios con este rol"
            :descripcion="
                (r.usuarios_count ?? 0) > usuarios.data.length
                    ? `Se muestran ${usuarios.data.length} de ${r.usuarios_count}.`
                    : undefined
            "
            sin-relleno
        >
            <ul v-if="usuarios.data.length > 0" class="divide-y">
                <li
                    v-for="u in usuarios.data"
                    :key="u.id"
                    class="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                >
                    <div class="min-w-0">
                        <Link
                            v-if="tienePermiso('usuarios.ver')"
                            :href="editarUsuario(u.id)"
                            class="block truncate font-medium hover:text-enlace hover:underline"
                            >{{ u.name }}</Link
                        >
                        <span v-else class="block truncate font-medium">{{
                            u.name
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ u.email
                            }}<template v-if="u.negocio">
                                · {{ u.negocio.nombre_comercial }}</template
                            ></span
                        >
                    </div>
                    <InsigniaEstado
                        :estado="u.estado"
                        :etiqueta="u.estado_etiqueta"
                    />
                </li>
            </ul>
            <EstadoVacio
                v-else
                :icono="UsersRound"
                titulo="Sin usuarios"
                descripcion="Asigna este rol desde la ficha de cada usuario."
            />
        </Panel>

        <Panel
            titulo="Historial de cambios"
            :descripcion="`Última modificación: ${fechaHora(r.updated_at)}`"
        >
            <ListaAuditoria :registros="historial.data" />
        </Panel>
    </div>

    <DialogoConfirmacion
        v-model:open="dialogoEliminar"
        :titulo="`¿Eliminar el rol «${r.nombre}»?`"
        descripcion="El rol no tiene usuarios asignados. Esta acción queda registrada en la auditoría y no se puede deshacer."
        confirmar="Eliminar rol"
        destructivo
        :procesando="eliminando"
        @confirmar="eliminar"
    />
</template>
