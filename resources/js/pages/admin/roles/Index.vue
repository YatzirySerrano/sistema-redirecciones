<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Eye,
    KeyRound,
    LockKeyhole,
    Pencil,
    Plus,
    SearchX,
    ShieldCheck,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AccionFila from '@/components/comun/AccionFila.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorVista from '@/components/comun/SelectorVista.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { useVistaListado } from '@/composables/useVistaListado';
import { fechaCorta, numero } from '@/lib/formato';
import { create, edit, index, show } from '@/routes/admin/roles';
import { index as usuarios } from '@/routes/admin/usuarios';
import type { Opcion, Paginado, Rol } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles y permisos', href: index() }],
    },
});

const props = defineProps<{
    roles: Paginado<Rol>;
    filtros: { buscar: string; ambito: string };
    ambitos: Opcion[];
    total_permisos: Record<'plataforma' | 'negocio', number>;
}>();

const { filtros, limpiar } = useFiltros(index.url(), { ...props.filtros });
const { vista } = useVistaListado('roles');
const { puede, esSuperadministrador } = usePermisos();

const hayFiltros = computed(
    () => filtros.buscar !== '' || filtros.ambito !== '',
);

const puedeEditar = (rol: Rol) =>
    puede('roles.editar') && (!rol.protegido || esSuperadministrador.value);
</script>

<template>
    <Head title="Roles y permisos" />

    <EncabezadoPagina
        titulo="Roles y permisos"
        descripcion="Define qué puede hacer cada persona. El tipo de cuenta limita el alcance: un rol de cliente nunca accede a otros negocios."
    >
        <template #acciones>
            <Button v-if="puede('roles.crear')" as-child>
                <Link :href="create()"><Plus /> Nuevo rol</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div
            class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
        >
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Buscar por nombre o descripción…"
                etiqueta="Buscar roles"
            />
            <SelectNativo
                v-model="filtros.ambito"
                :opciones="ambitos"
                vacio="Todos los tipos de cuenta"
                etiqueta="Filtrar por tipo de cuenta"
                class="sm:w-64"
            />
            <div class="flex items-center gap-2 sm:ml-auto">
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

        <template v-if="roles.data.length > 0">
            <div v-if="vista === 'tabla'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Listado de roles
                    </caption>
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="h-10 px-4 font-medium">
                                Rol
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium md:table-cell"
                            >
                                Tipo de cuenta
                            </th>
                            <th
                                scope="col"
                                class="h-10 px-3 text-right font-medium"
                            >
                                Usuarios
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 text-right font-medium sm:table-cell"
                            >
                                Permisos
                            </th>
                            <th
                                scope="col"
                                class="hidden h-10 px-3 font-medium lg:table-cell"
                            >
                                Modificado
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
                            v-for="rol in roles.data"
                            :key="rol.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="max-w-md px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="show(rol.id)"
                                        class="font-medium hover:text-enlace hover:underline"
                                        >{{ rol.nombre }}</Link
                                    >
                                    <span
                                        v-if="rol.protegido"
                                        class="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 text-[11px] font-medium text-accent-foreground"
                                        ><LockKeyhole
                                            class="size-3"
                                            aria-hidden="true"
                                        />Protegido</span
                                    >
                                    <span
                                        v-else-if="rol.del_sistema"
                                        class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                        >Del sistema</span
                                    >
                                </div>
                                <p
                                    class="line-clamp-2 text-xs text-muted-foreground"
                                >
                                    {{ rol.descripcion ?? 'Sin descripción' }}
                                </p>
                            </td>
                            <td
                                class="hidden px-3 py-3 text-muted-foreground md:table-cell"
                            >
                                {{ rol.ambito_etiqueta }}
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ numero(rol.usuarios_count ?? 0) }}
                            </td>
                            <td
                                class="hidden px-3 py-3 text-right tabular-nums sm:table-cell"
                            >
                                {{ numero(rol.permisos_count ?? 0) }}
                                <span class="text-muted-foreground"
                                    >/ {{ total_permisos[rol.ambito] }}</span
                                >
                            </td>
                            <td
                                class="hidden px-3 py-3 whitespace-nowrap text-muted-foreground lg:table-cell"
                            >
                                {{ fechaCorta(rol.updated_at) }}
                            </td>
                            <td class="px-2 py-2">
                                <div
                                    class="flex flex-wrap items-center justify-end gap-0.5"
                                >
                                    <AccionFila
                                        etiqueta="Ver"
                                        :icono="Eye"
                                        :href="show(rol.id)"
                                        compacta
                                    />
                                    <AccionFila
                                        v-if="puedeEditar(rol)"
                                        etiqueta="Editar"
                                        :icono="Pencil"
                                        :href="edit(rol.id)"
                                        compacta
                                    />
                                    <AccionFila
                                        v-if="puede('usuarios.ver')"
                                        etiqueta="Usuarios"
                                        :icono="UsersRound"
                                        :href="
                                            usuarios({
                                                query: { rol_id: rol.id },
                                            })
                                        "
                                        compacta
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
                aria-label="Listado de roles"
            >
                <li
                    v-for="rol in roles.data"
                    :key="rol.id"
                    class="flex flex-col rounded-xl border bg-background p-4 shadow-xs transition hover:border-primary/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link
                                :href="show(rol.id)"
                                class="block truncate font-semibold hover:text-enlace hover:underline"
                                >{{ rol.nombre }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ rol.ambito_etiqueta }}
                            </p>
                        </div>
                        <LockKeyhole
                            v-if="rol.protegido"
                            class="size-4 text-primary"
                            aria-label="Rol protegido"
                        />
                        <ShieldCheck
                            v-else-if="rol.del_sistema"
                            class="size-4 text-muted-foreground"
                            aria-label="Rol del sistema"
                        />
                    </div>
                    <p
                        class="mt-2 line-clamp-2 min-h-8 text-xs text-muted-foreground"
                    >
                        {{ rol.descripcion ?? 'Sin descripción' }}
                    </p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt
                                class="flex items-center gap-1 text-muted-foreground"
                            >
                                <UsersRound
                                    class="size-3"
                                    aria-hidden="true"
                                />Usuarios
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ numero(rol.usuarios_count ?? 0) }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt
                                class="flex items-center gap-1 text-muted-foreground"
                            >
                                <KeyRound
                                    class="size-3"
                                    aria-hidden="true"
                                />Permisos
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ numero(rol.permisos_count ?? 0) }} /
                                {{ total_permisos[rol.ambito] }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-0.5 border-t pt-2"
                    >
                        <AccionFila
                            etiqueta="Ver"
                            :icono="Eye"
                            :href="show(rol.id)"
                        />
                        <AccionFila
                            v-if="puedeEditar(rol)"
                            etiqueta="Editar"
                            :icono="Pencil"
                            :href="edit(rol.id)"
                        />
                    </div>
                </li>
            </ul>
        </template>

        <EstadoVacio
            v-else
            :icono="SearchX"
            titulo="Sin resultados"
            descripcion="Ningún rol coincide con los filtros aplicados."
        >
            <Button variant="outline" size="sm" @click="limpiar"
                >Limpiar filtros</Button
            >
        </EstadoVacio>

        <Paginacion :meta="roles.meta" nombre="roles" />
    </section>
</template>
