<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, Plus, SearchX, ShieldCheck, UsersRound } from '@lucide/vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { fechaHora, pendienteDeAcceso, relativo } from '@/lib/formato';
import { show as verNegocio } from '@/routes/admin/negocios';
import { create, edit, index } from '@/routes/admin/usuarios';
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
        estado: string;
        negocio_id: string;
    };
    negocios: Opcion[];
    roles: Opcion[];
    estados: Opcion[];
}>();

const { filtros, limpiar } = useFiltros(index.url(), { ...props.filtros });

const hayFiltros = () => Object.values(filtros).some((v) => v !== '');
</script>

<template>
    <Head title="Usuarios y accesos" />

    <EncabezadoPagina
        titulo="Usuarios y accesos"
        descripcion="Administradores de DASTI y usuarios de los negocios. No existe registro público: todos los accesos se crean aquí."
    >
        <template #acciones>
            <Button as-child
                ><Link :href="create()"><Plus /> Nuevo acceso</Link></Button
            >
        </template>
    </EncabezadoPagina>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div
            class="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center"
        >
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Buscar por nombre o correo…"
                etiqueta="Buscar usuarios"
            />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex">
                <SelectNativo
                    v-model="filtros.rol"
                    :opciones="roles"
                    vacio="Todos los roles"
                    etiqueta="Filtrar por rol"
                    class="lg:w-44"
                />
                <SelectNativo
                    v-model="filtros.estado"
                    :opciones="estados"
                    vacio="Todos los estados"
                    etiqueta="Filtrar por estado"
                    class="lg:w-40"
                />
                <SelectNativo
                    v-model="filtros.negocio_id"
                    :opciones="negocios"
                    vacio="Todos los negocios"
                    etiqueta="Filtrar por negocio"
                    class="lg:w-56"
                />
            </div>
            <Button
                v-if="hayFiltros()"
                variant="ghost"
                size="sm"
                class="lg:ml-auto"
                @click="limpiar"
                >Limpiar</Button
            >
        </div>

        <div v-if="usuarios.data.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Listado de usuarios
                </caption>
                <thead class="bg-muted/40 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="h-10 px-4 font-medium">
                            Usuario
                        </th>
                        <th scope="col" class="h-10 px-3 font-medium">Rol</th>
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
                            class="hidden h-10 px-3 font-medium lg:table-cell"
                        >
                            Seguridad
                        </th>
                        <th
                            scope="col"
                            class="hidden h-10 px-4 font-medium sm:table-cell"
                        >
                            Último acceso
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
                                class="font-medium hover:text-primary hover:underline"
                                >{{ u.name }}</Link
                            >
                            <span
                                v-if="u.es_actual"
                                class="ml-1.5 text-xs text-muted-foreground"
                                >(tú)</span
                            >
                            <p class="text-xs break-all text-muted-foreground">
                                {{ u.email }}
                            </p>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
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
                                {{ u.rol_etiqueta }}
                            </span>
                        </td>
                        <td class="hidden px-3 py-3 md:table-cell">
                            <Link
                                v-if="u.negocio"
                                :href="verNegocio(u.negocio.id)"
                                class="hover:text-primary hover:underline"
                                >{{ u.negocio.nombre_comercial }}</Link
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-3">
                            <InsigniaEstado
                                :estado="u.estado"
                                :etiqueta="u.estado_etiqueta"
                            />
                        </td>
                        <td class="hidden px-3 py-3 text-xs lg:table-cell">
                            <p
                                :class="
                                    u.two_factor
                                        ? 'text-success'
                                        : 'text-muted-foreground'
                                "
                            >
                                2FA
                                {{ u.two_factor ? 'activado' : 'no activado' }}
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
                                <KeyRound class="size-3" aria-hidden="true" />
                                {{ pendienteDeAcceso(u)?.texto }}
                            </p>
                        </td>
                        <td
                            class="hidden px-4 py-3 whitespace-nowrap text-muted-foreground sm:table-cell"
                        >
                            <span :title="fechaHora(u.ultimo_acceso_en)">{{
                                relativo(u.ultimo_acceso_en)
                            }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EstadoVacio
            v-else-if="hayFiltros()"
            :icono="SearchX"
            titulo="Sin resultados"
            descripcion="Ningún usuario coincide con los filtros."
        >
            <Button variant="outline" size="sm" @click="limpiar"
                >Limpiar filtros</Button
            >
        </EstadoVacio>
        <EstadoVacio
            v-else
            :icono="UsersRound"
            titulo="Sin usuarios"
            descripcion="Crea el primer acceso para un negocio."
        >
            <Button as-child
                ><Link :href="create()"><Plus /> Nuevo acceso</Link></Button
            >
        </EstadoVacio>

        <Paginacion :meta="usuarios.meta" nombre="usuarios" />
    </section>
</template>
