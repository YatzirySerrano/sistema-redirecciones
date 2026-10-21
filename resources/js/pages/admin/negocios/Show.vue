<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    Ban,
    CircleCheck,
    ImagePlus,
    Mail,
    Nfc,
    Package,
    Pencil,
    Phone,
    Plus,
    QrCode,
    Trash2,
    UserRound,
    UserRoundPlus,
    UsersRound,
} from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import InputError from '@/components/InputError.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    coberturaVisitantes,
    dominio,
    fechaCorta,
    fechaHora,
    numero,
    pendienteDeAcceso,
    relativo,
    visitantes,
} from '@/lib/formato';
import { edit, estado, index } from '@/routes/admin/negocios';
import {
    destroy as eliminarLogo,
    store as subirLogo,
} from '@/routes/admin/negocios/logo';
import {
    create as crearProducto,
    show as verProducto,
} from '@/routes/admin/productos';
import {
    create as crearUsuario,
    edit as editarUsuario,
} from '@/routes/admin/usuarios';
import type {
    CambioDestino,
    Coleccion,
    Negocio,
    Producto,
    PuntoSerie,
    Recurso,
    RegistroAuditoria,
    TotalesEstadisticas,
    Usuario,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Negocios', href: index() },
            { title: 'Ficha del negocio', href: '#' },
        ],
    },
});

const props = defineProps<{
    negocio: Recurso<Negocio>;
    usuarios: Coleccion<Usuario>;
    productos: Coleccion<Producto>;
    estadisticas: { totales: TotalesEstadisticas; serie: PuntoSerie[] };
    cambios: Coleccion<CambioDestino>;
    auditoria: Coleccion<RegistroAuditoria>;
}>();

const n = computed(() => props.negocio.data);
const suspendido = computed(() => n.value.estado === 'suspendido');

// Suspensión / reactivación
const dialogoEstado = ref(false);
const motivo = ref('');
const errorEstado = ref<string | undefined>();
const cambiandoEstado = ref(false);

const cambiarEstado = () => {
    router.patch(
        estado.url(n.value.id),
        {
            estado: suspendido.value ? 'activo' : 'suspendido',
            motivo: motivo.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => {
                cambiandoEstado.value = true;
                errorEstado.value = undefined;
            },
            onFinish: () => (cambiandoEstado.value = false),
            onError: (errores) =>
                (errorEstado.value =
                    errores.motivo ??
                    errores.estado ??
                    Object.values(errores)[0]),
            onSuccess: () => {
                dialogoEstado.value = false;
                motivo.value = '';
            },
        },
    );
};

// Logotipo
const archivoLogo = useTemplateRef<HTMLInputElement>('archivoLogo');
const subiendoLogo = ref(false);
const errorLogo = ref<string | undefined>();
const dialogoLogo = ref(false);
const eliminandoLogo = ref(false);

const alElegirLogo = (evento: Event) => {
    const archivo = (evento.target as HTMLInputElement).files?.[0];

    if (!archivo) {
        return;
    }

    errorLogo.value = undefined;
    router.post(
        subirLogo.url(n.value.id),
        { logo: archivo },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (subiendoLogo.value = true),
            onFinish: () => {
                subiendoLogo.value = false;

                if (archivoLogo.value) {
                    archivoLogo.value.value = '';
                }
            },
            onError: (errores) => (errorLogo.value = errores.logo),
        },
    );
};

const quitarLogo = () => {
    router.delete(eliminarLogo.url(n.value.id), {
        preserveScroll: true,
        onStart: () => (eliminandoLogo.value = true),
        onFinish: () => (eliminandoLogo.value = false),
        onSuccess: () => (dialogoLogo.value = false),
    });
};
</script>

<template>
    <Head :title="n.nombre_comercial" />

    <EncabezadoPagina :titulo="n.nombre_comercial">
        <template #detalle>
            <div
                class="flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground"
            >
                <InsigniaEstado
                    :estado="n.estado"
                    :etiqueta="n.estado_etiqueta"
                />
                <span>Cliente desde {{ fechaCorta(n.created_at) }}</span>
                <span v-if="n.suspendido_en"
                    >· Suspendido {{ relativo(n.suspendido_en) }}</span
                >
            </div>
        </template>
        <template #acciones>
            <Button variant="outline" as-child>
                <Link :href="edit(n.id)"><Pencil /> Editar</Link>
            </Button>
            <Button
                :variant="suspendido ? 'default' : 'outline'"
                :class="
                    !suspendido && 'text-destructive hover:text-destructive'
                "
                @click="dialogoEstado = true"
            >
                <CircleCheck v-if="suspendido" />
                <Ban v-else />
                {{ suspendido ? 'Reactivar' : 'Suspender' }}
            </Button>
        </template>
    </EncabezadoPagina>

    <div
        v-if="suspendido"
        class="flex items-start gap-3 rounded-xl border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive"
        role="status"
    >
        <Ban class="mt-0.5 size-4 shrink-0" />
        <p>
            Este negocio está suspendido: sus enlaces muestran «no disponible» y
            sus usuarios no pueden iniciar sesión.
        </p>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <TarjetaIndicador
            etiqueta="Productos"
            :valor="numero(n.productos_count)"
            :icono="Package"
        />
        <TarjetaIndicador
            etiqueta="Con QR"
            :valor="numero(n.productos_qr_count)"
            :icono="QrCode"
            detalle="Incluye combinados"
        />
        <TarjetaIndicador
            etiqueta="Con NFC"
            :valor="numero(n.productos_nfc_count)"
            :icono="Nfc"
            detalle="Incluye combinados"
        />
        <TarjetaIndicador
            etiqueta="Activos"
            :valor="numero(n.productos_activos_count)"
            :icono="CircleCheck"
        />
        <TarjetaIndicador
            etiqueta="Accesos (30 días)"
            :valor="numero(estadisticas.totales.total)"
            :icono="Activity"
        />
        <TarjetaIndicador
            etiqueta="Visitantes estimados"
            :valor="visitantes(estadisticas.totales)"
            :icono="UsersRound"
            :detalle="
                coberturaVisitantes(estadisticas.totales) ??
                'Únicos por día, aproximado'
            "
        />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Panel
            titulo="Accesos por día"
            descripcion="Últimos 30 días, sin bots identificados."
            class="lg:col-span-2"
        >
            <GraficaArea
                :serie="estadisticas.serie"
                etiqueta="Accesos"
                :alto="220"
            />
        </Panel>

        <div class="space-y-6">
            <Panel titulo="Contacto">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <UserRound
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div>
                            <dt class="sr-only">Responsable</dt>
                            <dd>
                                {{
                                    n.responsable ??
                                    'Sin responsable registrado'
                                }}
                            </dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Mail
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <dt class="sr-only">Correo</dt>
                            <dd>
                                <a
                                    v-if="n.email_contacto"
                                    :href="`mailto:${n.email_contacto}`"
                                    class="break-all hover:text-primary hover:underline"
                                    >{{ n.email_contacto }}</a
                                >
                                <span v-else class="text-muted-foreground"
                                    >Sin correo</span
                                >
                            </dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Phone
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div>
                            <dt class="sr-only">Teléfono</dt>
                            <dd>
                                <a
                                    v-if="n.telefono"
                                    :href="`tel:${n.telefono.replace(/\s/g, '')}`"
                                    class="hover:text-primary hover:underline"
                                    >{{ n.telefono }}</a
                                >
                                <span v-else class="text-muted-foreground"
                                    >Sin teléfono</span
                                >
                            </dd>
                        </div>
                    </div>
                </dl>
                <div class="mt-4 border-t pt-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        Notas internas
                    </p>
                    <p class="mt-1 text-sm whitespace-pre-line">
                        {{ n.notas_internas || 'Sin notas.' }}
                    </p>
                </div>
            </Panel>

            <Panel
                titulo="Logotipo del negocio"
                descripcion="Opcional, para colocarlo al centro de sus QR."
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-[repeating-conic-gradient(var(--muted)_0%_25%,transparent_0%_50%)] bg-[length:12px_12px]"
                    >
                        <img
                            v-if="n.logo_url"
                            :src="n.logo_url"
                            :alt="`Logotipo de ${n.nombre_comercial}`"
                            class="size-full object-contain"
                        />
                        <ImagePlus
                            v-else
                            class="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="space-y-2">
                        <input
                            ref="archivoLogo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="sr-only"
                            :id="`logo-${n.id}`"
                            @change="alElegirLogo"
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="subiendoLogo"
                            @click="archivoLogo?.click()"
                        >
                            <Spinner v-if="subiendoLogo" />
                            <ImagePlus v-else />
                            {{ n.tiene_logo ? 'Reemplazar' : 'Subir logotipo' }}
                        </Button>
                        <Button
                            v-if="n.tiene_logo"
                            size="sm"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            @click="dialogoLogo = true"
                        >
                            <Trash2 /> Quitar
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            PNG, JPG o WEBP, máx. 1 MB.
                        </p>
                    </div>
                </div>
                <InputError class="mt-2" :message="errorLogo" />
            </Panel>
        </div>
    </div>

    <Panel
        titulo="Productos asignados"
        :descripcion="`${productos.data.length} productos con enlace permanente propio.`"
        sin-relleno
    >
        <template #acciones>
            <Button size="sm" as-child>
                <Link :href="crearProducto({ query: { negocio_id: n.id } })"
                    ><Plus /> Registrar productos</Link
                >
            </Button>
        </template>
        <div v-if="productos.data.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Productos del negocio
                </caption>
                <thead class="bg-muted/40 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="h-10 px-4 font-medium">
                            Producto
                        </th>
                        <th
                            scope="col"
                            class="hidden h-10 px-3 font-medium md:table-cell"
                        >
                            Enlace permanente
                        </th>
                        <th
                            scope="col"
                            class="hidden h-10 px-3 font-medium lg:table-cell"
                        >
                            Destino
                        </th>
                        <th scope="col" class="h-10 px-3 font-medium">
                            Estado
                        </th>
                        <th
                            scope="col"
                            class="h-10 px-4 text-right font-medium"
                        >
                            Accesos
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in productos.data"
                        :key="p.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="verProducto(p.id)"
                                class="font-medium hover:text-primary hover:underline"
                                >{{ p.nombre }}</Link
                            >
                            <div class="mt-1">
                                <InsigniaTipo
                                    :tipo="p.tipo"
                                    :etiqueta="p.tipo_etiqueta"
                                />
                            </div>
                        </td>
                        <td class="hidden px-3 py-3 md:table-cell">
                            <div class="flex items-center gap-1">
                                <code class="font-mono text-xs"
                                    >/r/{{ p.codigo }}</code
                                >
                                <BotonCopiar
                                    :texto="p.url_permanente"
                                    etiqueta="Copiar enlace permanente"
                                    mensaje="Enlace permanente copiado"
                                    solo-icono
                                    variante="ghost"
                                />
                            </div>
                        </td>
                        <td class="hidden max-w-56 px-3 py-3 lg:table-cell">
                            <a
                                v-if="p.url_destino"
                                :href="p.url_destino"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="block truncate hover:text-primary hover:underline"
                                :title="p.url_destino"
                                >{{ dominio(p.url_destino) }}</a
                            >
                            <span v-else class="text-muted-foreground"
                                >Sin destino</span
                            >
                        </td>
                        <td class="px-3 py-3">
                            <InsigniaEstado
                                :estado="p.estado"
                                :etiqueta="p.estado_etiqueta"
                            />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ numero(p.total_accesos) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EstadoVacio
            v-else
            :icono="Package"
            titulo="Sin productos asignados"
            descripcion="Registra las tarjetas NFC o códigos QR que compró este negocio."
        >
            <Button as-child
                ><Link :href="crearProducto({ query: { negocio_id: n.id } })"
                    ><Plus /> Registrar productos</Link
                ></Button
            >
        </EstadoVacio>
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Usuarios con acceso"
            descripcion="Personas que administran los enlaces de este negocio."
        >
            <template #acciones>
                <Button size="sm" variant="outline" as-child>
                    <Link :href="crearUsuario({ query: { negocio_id: n.id } })"
                        ><UserRoundPlus /> Agregar acceso</Link
                    >
                </Button>
            </template>
            <ul v-if="usuarios.data.length > 0" class="divide-y" role="list">
                <li
                    v-for="u in usuarios.data"
                    :key="u.id"
                    class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <div class="min-w-0">
                        <Link
                            :href="editarUsuario(u.id)"
                            class="font-medium hover:text-primary hover:underline"
                            >{{ u.name }}</Link
                        >
                        <p class="truncate text-xs text-muted-foreground">
                            {{ u.email }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Último acceso:
                            <span :title="fechaHora(u.ultimo_acceso_en)">{{
                                relativo(u.ultimo_acceso_en)
                            }}</span>
                            <template v-if="pendienteDeAcceso(u)">
                                ·
                                <span
                                    :class="
                                        pendienteDeAcceso(u)?.error
                                            ? 'text-destructive'
                                            : 'text-warning'
                                    "
                                    >{{ pendienteDeAcceso(u)?.texto }}</span
                                ></template
                            >
                        </p>
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
                titulo="Sin accesos"
                descripcion="El negocio todavía no puede entrar al panel."
            >
                <Button size="sm" as-child
                    ><Link :href="crearUsuario({ query: { negocio_id: n.id } })"
                        ><UserRoundPlus /> Crear acceso</Link
                    ></Button
                >
            </EstadoVacio>
        </Panel>

        <Panel titulo="Cambios de destino recientes">
            <HistorialDestinos :cambios="cambios.data" mostrar-producto />
        </Panel>
    </div>

    <Panel
        titulo="Historial de actividad"
        descripcion="Operaciones auditadas relacionadas con este negocio."
    >
        <ListaAuditoria :registros="auditoria.data" />
    </Panel>

    <DialogoConfirmacion
        v-model:open="dialogoEstado"
        :titulo="
            suspendido
                ? `¿Reactivar ${n.nombre_comercial}?`
                : `¿Suspender ${n.nombre_comercial}?`
        "
        :descripcion="
            suspendido
                ? 'Sus enlaces volverán a redirigir y sus usuarios activos podrán iniciar sesión.'
                : 'Todos sus enlaces mostrarán «no disponible» y sus usuarios perderán el acceso hasta que lo reactives.'
        "
        :confirmar="suspendido ? 'Reactivar negocio' : 'Suspender negocio'"
        :destructivo="!suspendido"
        :procesando="cambiandoEstado"
        @confirmar="cambiarEstado"
    >
        <div class="grid gap-2">
            <Label for="motivo-estado"
                >Motivo
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                ></Label
            >
            <Textarea
                id="motivo-estado"
                v-model="motivo"
                rows="2"
                maxlength="255"
                placeholder="Se guardará en la auditoría"
            />
            <InputError :message="errorEstado" />
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        v-model:open="dialogoLogo"
        titulo="¿Quitar el logotipo?"
        descripcion="Los QR que lo incluían se generarán sin logotipo a partir de ahora."
        confirmar="Quitar logotipo"
        destructivo
        :procesando="eliminandoLogo"
        @confirmar="quitarLogo"
    />
</template>
