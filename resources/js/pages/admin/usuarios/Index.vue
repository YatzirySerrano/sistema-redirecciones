<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Eye,
    KeyRound,
    Pencil,
    Plus,
    SearchX,
    ShieldCheck,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AccionFila from '@/components/comun/AccionFila.vue';
import CambiarEstadoRegistro from '@/components/comun/CambiarEstadoRegistro.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorVista from '@/components/comun/SelectorVista.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { useVistaListado } from '@/composables/useVistaListado';
import { fechaHora, pendienteDeAcceso, relativo } from '@/lib/formato';
import { show as verNegocio } from '@/routes/admin/negocios';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { create, edit, estado, index } from '@/routes/admin/usuarios';
import type { Opcion, Paginado, Usuario } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios y accesos', href: index() }],
    },
});

const props = defineProps<{
    usuarios: Paginado<Usuario>;
    filtros: {
        buscar: string;
        rol: string;
        rol_id: string;
        estado: string;
        negocio_id: string;
    };
    negocio_filtro: Opcion | null;
    tipos: Opcion[];
    roles: Opcion[];
    estados: Opcion[];
}>();

const { filtros, limpiar } = useFiltros(index.url(), { ...props.filtros });
const { vista } = useVistaListado('usuarios');
const { puede } = usePermisos();

const hayFiltros = computed(() => Object.values(filtros).some((v) => v !== ''));

const puedeEditar = computed(() => puede('usuarios.editar'));
const puedeGestionar = (u: Usuario) =>
    puede('usuarios.gestionar_accesos') && !u.es_actual;
</script>

<template>
    <Head title="Usuarios y accesos" />

    <EncabezadoPagina
        titulo="Usuarios y accesos"
        descripcion="Personal de DASTI y usuarios de los negocios. No existe registro público: todos los accesos se crean aquí."
    >
        <template #acciones>
            <Button v-if="puede('usuarios.crear')" as-child
                ><Link :href="create()"><Plus /> Nuevo acceso</Link></Button
            >
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div class="space-y-3 border-b p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <CampoBusqueda
                    v-model="filtros.buscar"
                    placeholder="Buscar por nombre o correo…"
                    etiqueta="Buscar usuarios"
                />
                <div class="grid flex-1 grid-cols-2 gap-2 md:grid-cols-4">
                    <SelectNativo
                        v-model="filtros.rol"
                        :opciones="tipos"
                        vacio="Todos los tipos"
                        etiqueta="Filtrar por tipo de cuenta"
                    />
                    <SelectorBuscable
                        v-model="filtros.rol_id"
                        :opciones="roles"
                        vacio="Todos los roles"
                        etiqueta="Filtrar por rol"
                        placeholder-busqueda="Buscar rol…"
                    />
                    <SelectNativo
                        v-model="filtros.estado"
                        :opciones="estados"
                        vacio="Todos los estados"
                        etiqueta="Filtrar por estado"
                    />
                    <SelectorBuscable
                        v-model="filtros.negocio_id"
                        :url="opcionesNegocios.url()"
                        :seleccion-inicial="negocio_filtro"
                        vacio="Todos los negocios"
                        etiqueta="Filtrar por negocio"
                        placeholder-busqueda="Buscar negocio…"
                    />
                </div>
            </div>
            <div class="flex items-center justify-end gap-2">
                <Button
                    v-if="hayFiltros"
                    variant="ghost"
                    size="sm"
                    @click="limpiar"
                    >Limpiar filtros</Button
                >
                <SelectorVista v-model="vista" />
            </div>
        </div>

        <template v-if="usuarios.data.length > 0">
            <div v-if="vista === 'tabla'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Listado de usuarios
                    </caption>
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="h-10 px-4 font-medium">
                                Usuario
                            </th>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Rol
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium md:table-cell"
                            >
                                Negocio
                            </th>
                            <th scope="col" class="h-10 px-3 font-medium">
                                Estado
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium xl:table-cell"
                            >
                                Seguridad
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium lg:table-cell"
                            >
                                Último acceso
                            </th>
                            <th
                                scope="col"
                                class="h-10 px-3 text-right font-medium"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in usuarios.data"
                            :key="u.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="edit(u.id)"
                                    class="font-medium hover:text-enlace hover:underline"
                                    >{{ u.name }}</Link
                                >
                                <span
                                    v-if="u.es_actual"
                                    class="ml-1.5 text-xs text-muted-foreground"
                                    >(tú)</span
                                >
                                <p
                                    class="text-xs break-all text-muted-foreground"
                                >
                                    {{ u.email }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium"
                                    :class="
                                        u.rol === 'admin'
                                            ? 'text-primary'
                                            : 'text-foreground/80'
                                    "
                                >
                                    <ShieldCheck
                                        v-if="u.rol === 'admin'"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ u.rol_asignado?.nombre ?? 'Sin rol' }}
                                </span>
                                <p class="text-xs text-muted-foreground">
                                    {{ u.rol_etiqueta }}
                                </p>
                            </td>
                            <td class="hidden px-3 py-3 md:table-cell">
                                <Link
                                    v-if="u.negocio && puede('negocios.ver')"
                                    :href="verNegocio(u.negocio.id)"
                                    class="hover:text-enlace hover:underline"
                                    >{{ u.negocio.nombre_comercial }}</Link
                                >
                                <span v-else-if="u.negocio">{{
                                    u.negocio.nombre_comercial
                                }}</span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <InsigniaEstado
                                    :estado="u.estado"
                                    :etiqueta="u.estado_etiqueta"
                                />
                            </td>
                            <td class="hidden px-3 py-3 text-xs xl:table-cell">
                                <p
                                    :class="
                                        u.two_factor
                                            ? 'text-success'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    2FA
                                    {{
                                        u.two_factor
                                            ? 'activado'
                                            : 'no activado'
                                    }}
                                </p>
                                <p
                                    v-if="pendienteDeAcceso(u)"
                                    class="mt-0.5 inline-flex items-center gap-1"
                                    :class="
                                        pendienteDeAcceso(u)?.error
                                            ? 'text-destructive'
                                            : 'text-warning'
                                    "
                                >
                                    <KeyRound
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                    {{ pendienteDeAcceso(u)?.texto }}
                                </p>
                            </td>
                            <td
                                class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground lg:table-cell"
                            >
                                <span :title="fechaHora(u.ultimo_acceso_en)">{{
                                    relativo(u.ultimo_acceso_en)
                                }}</span>
                            </td>
                            <td class="px-2 py-2">
                                <div
                                    class="flex flex-wrap items-center justify-end gap-0.5"
                                >
                                    <AccionFila
                                        :etiqueta="
                                            puedeEditar ? 'Editar' : 'Ver'
                                        "
                                        :icono="puedeEditar ? Pencil : Eye"
                                        :href="edit(u.id)"
                                        compacta
                                    />
                                    <CambiarEstadoRegistro
                                        v-if="puedeGestionar(u)"
                                        :url="estado.url(u.id)"
                                        :estado="u.estado"
                                        :nombre="u.name"
                                        tipo="usuario"
                                        compacto
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul
                v-else
                class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3"
                aria-label="Listado de usuarios"
            >
                <li
                    v-for="u in usuarios.data"
                    :key="u.id"
                    class="flex flex-col rounded-xl border bg-background p-4 shadow-xs transition hover:border-primary/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link
                                :href="edit(u.id)"
                                class="block truncate font-semibold hover:text-enlace hover:underline"
                                >{{ u.name
                                }}<span
                                    v-if="u.es_actual"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >(tú)</span
                                ></Link
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                {{ u.email }}
                            </p>
                        </div>
                        <InsigniaEstado
                            :estado="u.estado"
                            :etiqueta="u.estado_etiqueta"
                        />
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-muted-foreground">Rol</dt>
                            <dd class="truncate font-medium">
                                {{ u.rol_asignado?.nombre ?? 'Sin rol' }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-muted-foreground">Negocio</dt>
                            <dd class="truncate font-medium">
                                {{ u.negocio?.nombre_comercial ?? 'DASTI' }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-muted-foreground">
                        2FA {{ u.two_factor ? 'activado' : 'no activado' }} ·
                        Último acceso {{ relativo(u.ultimo_acceso_en) }}
                    </p>
                    <p
                        v-if="pendienteDeAcceso(u)"
                        class="mt-1 inline-flex items-center gap-1 text-xs"
                        :class="
                            pendienteDeAcceso(u)?.error
                                ? 'text-destructive'
                                : 'text-warning'
                        "
                    >
                        <KeyRound class="size-3" aria-hidden="true" />
                        {{ pendienteDeAcceso(u)?.texto }}
                    </p>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-0.5 border-t pt-2"
                    >
                        <AccionFila
                            :etiqueta="puedeEditar ? 'Editar' : 'Ver'"
                            :icono="puedeEditar ? Pencil : Eye"
                            :href="edit(u.id)"
                        />
                        <CambiarEstadoRegistro
                            v-if="puedeGestionar(u)"
                            :url="estado.url(u.id)"
                            :estado="u.estado"
                            :nombre="u.name"
                            tipo="usuario"
                            compacto
                        />
                    </div>
                </li>
            </ul>
        </template>

        <EstadoVacio
            v-else-if="hayFiltros"
            :icono="SearchX"
            titulo="Sin resultados"
            descripcion="Ningún usuario coincide con los filtros aplicados."
        >
            <Button variant="outline" size="sm" @click="limpiar"
                >Limpiar filtros</Button
            >
        </EstadoVacio>
        <EstadoVacio
            v-else
            :icono="UsersRound"
            titulo="Aún no hay usuarios"
            descripcion="Crea el primer acceso para un negocio o para el equipo de DASTI."
        />

        <Paginacion :meta="usuarios.meta" nombre="usuarios" />
    </section>
</template>
