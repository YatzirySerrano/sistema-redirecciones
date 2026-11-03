<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CircleAlert,
    CirclePause,
    CirclePlay,
    CreditCard,
    Layers,
    LayoutList,
    Lock,
    LockOpen,
    Plus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import FormularioServicio from '@/components/servicios/FormularioServicio.vue';
import type { ServicioAdmin } from '@/components/servicios/tipos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFiltros } from '@/composables/useFiltros';
import { fechaCorta, numero, pesos } from '@/lib/formato';
import {
    estado as estadoContratacion,
    store as activarContratacion,
} from '@/routes/admin/contrataciones';
import { show as verNegocio } from '@/routes/admin/negocios';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { index as pagos } from '@/routes/admin/pagos';
import { index } from '@/routes/admin/servicios';
import type { MetaPaginacion, Opcion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Servicios contratados', href: index() }],
    },
});

type Contratacion = {
    id: number;
    negocio: { id: number; nombre_comercial: string };
    servicio: string;
    servicio_clave: string;
    creado_por: string | null;
    estado: string;
    estado_etiqueta: string;
    vencida: boolean;
    origen: string;
    inicia_en: string | null;
    vence_en: string | null;
    motivo: string | null;
    restriccion_manual: boolean;
    pago: {
        folio: string | null;
        estado: string;
        importe_centavos: number;
        moneda: string;
    } | null;
};

const props = defineProps<{
    servicios: ServicioAdmin[];
    contrataciones: { data: Contratacion[]; meta: MetaPaginacion };
    filtros: { estado: string; negocio_id: string; servicio: string };
    negocio_filtro: Opcion | null;
    estados: Opcion[];
    resumen: {
        negocios_con_fidelidad: number;
        contrataciones_pendientes: number;
        pagos_confirmados: number;
        pagos_confirmados_centavos: number;
        pagos_pendientes: number;
        incidencias: number;
    };
    pasarela: {
        nombre: string;
        configurado: boolean;
        modo_real: boolean;
        notificaciones: boolean;
    };
    puede_gestionar: boolean;
}>();

const { filtros } = useFiltros(index.url(), { ...props.filtros });

// Servicios adicionales (contratación independiente).
const adicionales = computed(() => props.servicios.filter((s) => !s.es_base));
const opcionesServicio = computed(() =>
    adicionales.value.map((s) => ({ value: s.clave, label: s.nombre })),
);
const configurando = ref(adicionales.value[0]?.clave ?? '');
const servicioConfigurado = computed(() =>
    adicionales.value.find((s) => s.clave === configurando.value),
);
const vigentes = (clave: string) =>
    props.servicios.find((s) => s.clave === clave)?.contrataciones_activas ?? 0;

// Activación manual / pago fuera de línea
const dialogoActivar = ref(false);
const formActivar = useForm({
    negocio_id: '',
    servicio: adicionales.value[0]?.clave ?? '',
    origen: 'manual',
    importe: '',
    referencia: '',
    motivo: '',
});
const activar = () =>
    formActivar.post(activarContratacion.url(), {
        preserveScroll: true,
        onSuccess: () => {
            dialogoActivar.value = false;
            formActivar.reset();
        },
    });

// Cambios de estado con motivo
const accionPendiente = ref<{
    contratacion: Contratacion;
    accion: string;
    titulo: string;
} | null>(null);
const formEstado = useForm({ accion: '', motivo: '' });
const pedirAccion = (
    contratacion: Contratacion,
    accion: string,
    titulo: string,
) => {
    formEstado.reset();
    formEstado.accion = accion;
    accionPendiente.value = { contratacion, accion, titulo };
};
const confirmarAccion = () =>
    accionPendiente.value &&
    formEstado.patch(
        estadoContratacion.url(accionPendiente.value.contratacion.id),
        {
            preserveScroll: true,
            onSuccess: () => (accionPendiente.value = null),
        },
    );

const insignia = (c: Contratacion) =>
    c.vencida
        ? 'pendiente'
        : c.estado === 'activa'
          ? 'activo'
          : c.estado === 'restringida' || c.estado === 'pendiente'
            ? 'pendiente'
            : 'suspendido';
void router;
</script>

<template>
    <Head title="Servicios contratados" />

    <EncabezadoPagina
        titulo="Servicios contratados"
        descripcion="Redirecciones es el servicio base. Fidelidad y Página de enlaces son servicios adicionales independientes, de pago único, que se activan por pago en línea o manualmente."
        ayuda="Los permisos de un usuario no bastan: su negocio también debe tener el servicio activo. Activar un servicio no activa el otro. Nada de lo que hagas aquí afecta las redirecciones QR/NFC."
        :icono="Layers"
    >
        <template #acciones>
            <Button variant="outline" as-child
                ><Link :href="pagos()"><CreditCard /> Pagos</Link></Button
            >
            <Button v-if="puede_gestionar" @click="dialogoActivar = true"
                ><Plus /> Activar servicio</Button
            >
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <TarjetaIndicador
            etiqueta="Con Fidelidad"
            :valor="numero(vigentes('fidelidad'))"
            :icono="BadgeCheck"
        />
        <TarjetaIndicador
            etiqueta="Con Página de enlaces"
            :valor="numero(vigentes('pagina_enlaces'))"
            :icono="LayoutList"
        />
        <TarjetaIndicador
            etiqueta="Pagos confirmados"
            :valor="numero(resumen.pagos_confirmados)"
            :detalle="pesos(resumen.pagos_confirmados_centavos)"
        />
        <TarjetaIndicador
            etiqueta="Pagos pendientes"
            :valor="numero(resumen.pagos_pendientes)"
        />
        <TarjetaIndicador
            etiqueta="Incidencias"
            :valor="numero(resumen.incidencias)"
            detalle="Reembolsos parciales, disputas y revisiones"
            :tono="resumen.incidencias > 0 ? 'alerta' : 'normal'"
            :href="pagos({ query: { revision: 1 } })"
            class="col-span-2 lg:col-span-1"
        />
    </div>

    <div
        class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]"
    >
        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-col gap-2 border-b p-4 sm:flex-row">
                <SelectorBuscable
                    v-model="filtros.negocio_id"
                    :url="opcionesNegocios.url()"
                    :seleccion-inicial="negocio_filtro"
                    vacio="Todos los negocios"
                    etiqueta="Filtrar por negocio"
                    class="sm:w-64"
                />
                <SelectNativo
                    v-model="filtros.servicio"
                    :opciones="opcionesServicio"
                    vacio="Todos los servicios"
                    etiqueta="Filtrar por servicio"
                    class="sm:w-52"
                />
                <SelectNativo
                    v-model="filtros.estado"
                    :opciones="estados"
                    vacio="Todos los estados"
                    etiqueta="Filtrar por estado"
                    class="sm:w-52"
                />
            </div>
            <div v-if="contrataciones.data.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Contrataciones
                    </caption>
                    <thead
                        class="bg-muted/40 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="h-9 px-4 font-medium">Negocio</th>
                            <th class="h-9 px-3 font-medium">Estado</th>
                            <th
                                class="hidden h-9 px-3 font-medium md:table-cell"
                            >
                                Periodo
                            </th>
                            <th
                                class="hidden h-9 px-3 font-medium lg:table-cell"
                            >
                                Origen
                            </th>
                            <th class="h-9 px-3 text-right font-medium">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in contrataciones.data"
                            :key="c.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="verNegocio(c.negocio.id)"
                                    class="font-medium hover:text-enlace hover:underline"
                                    >{{ c.negocio.nombre_comercial }}</Link
                                >
                                <p class="text-xs font-medium text-primary">
                                    {{ c.servicio }}
                                </p>
                                <p
                                    v-if="c.motivo"
                                    class="max-w-xs truncate text-xs text-muted-foreground"
                                    :title="c.motivo"
                                >
                                    {{ c.motivo }}
                                </p>
                            </td>
                            <td class="px-3 py-2.5">
                                <InsigniaEstado
                                    :estado="insignia(c)"
                                    :etiqueta="
                                        c.vencida
                                            ? 'Vencida'
                                            : c.estado_etiqueta
                                    "
                                />
                            </td>
                            <td
                                class="hidden px-3 py-2.5 whitespace-nowrap text-muted-foreground md:table-cell"
                            >
                                {{ fechaCorta(c.inicia_en) }} –
                                {{
                                    c.vence_en
                                        ? fechaCorta(c.vence_en)
                                        : 'indefinida'
                                }}
                            </td>
                            <td
                                class="hidden px-3 py-2.5 text-muted-foreground lg:table-cell"
                            >
                                {{ c.origen
                                }}<template v-if="c.pago">
                                    ·
                                    {{
                                        pesos(c.pago.importe_centavos)
                                    }}</template
                                >
                                <span v-if="c.creado_por" class="block text-xs"
                                    >Por {{ c.creado_por }}</span
                                >
                            </td>
                            <td class="px-2 py-1.5">
                                <div
                                    v-if="puede_gestionar"
                                    class="flex justify-end gap-0.5"
                                >
                                    <Button
                                        v-if="c.estado === 'activa'"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Restringir (solo lectura)"
                                        aria-label="Restringir"
                                        @click="
                                            pedirAccion(
                                                c,
                                                'restringir',
                                                `Restringir ${c.servicio}`,
                                            )
                                        "
                                        ><Lock
                                    /></Button>
                                    <Button
                                        v-if="c.estado === 'restringida'"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Levantar restricción"
                                        aria-label="Levantar restricción"
                                        @click="
                                            pedirAccion(
                                                c,
                                                'levantar',
                                                'Levantar restricción',
                                            )
                                        "
                                        ><LockOpen
                                    /></Button>
                                    <Button
                                        v-if="
                                            c.estado === 'activa' ||
                                            c.estado === 'restringida'
                                        "
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-destructive hover:text-destructive"
                                        title="Suspender"
                                        aria-label="Suspender"
                                        @click="
                                            pedirAccion(
                                                c,
                                                'suspender',
                                                `Suspender ${c.servicio}`,
                                            )
                                        "
                                        ><CirclePause
                                    /></Button>
                                    <Button
                                        v-if="
                                            c.estado === 'suspendida' &&
                                            !c.vencida
                                        "
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Reactivar"
                                        aria-label="Reactivar"
                                        @click="
                                            pedirAccion(
                                                c,
                                                'reactivar',
                                                `Reactivar ${c.servicio}`,
                                            )
                                        "
                                        ><CirclePlay
                                    /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EstadoVacio
                v-else
                :icono="Layers"
                titulo="Sin contrataciones"
                descripcion="Activa un servicio para un negocio o espera su pago en línea."
            />
            <Paginacion :meta="contrataciones.meta" nombre="contrataciones" />
        </section>

        <Panel
            titulo="Catálogo y precios"
            descripcion="Se aplica a las contrataciones nuevas. Sin precio no se puede contratar en línea."
        >
            <div
                v-if="!pasarela.configurado || !pasarela.notificaciones"
                class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-xs text-warning"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                <span>
                    {{
                        !pasarela.configurado
                            ? 'Faltan las credenciales de Openpay (OPENPAY_MERCHANT_ID y OPENPAY_PRIVATE_KEY): el pago en línea está deshabilitado.'
                            : 'Faltan el usuario y la contraseña de las notificaciones de Openpay (OPENPAY_WEBHOOK_USER y OPENPAY_WEBHOOK_PASSWORD): los pagos solo se confirmarán al volver del formulario o con la conciliación programada.'
                    }}
                    Puedes activar manualmente o registrar pagos fuera de línea.
                </span>
            </div>
            <p v-else class="mb-4 text-xs text-muted-foreground">
                Openpay en ambiente
                {{
                    pasarela.modo_real
                        ? 'de PRODUCCIÓN (cargos reales)'
                        : 'de pruebas (sandbox)'
                }}.
            </p>
            <SelectNativo
                v-model="configurando"
                :opciones="opcionesServicio"
                etiqueta="Servicio a configurar"
                class="mb-4"
            />
            <FormularioServicio
                v-if="servicioConfigurado"
                :key="servicioConfigurado.id"
                :servicio="servicioConfigurado"
                :editable="puede_gestionar"
            />
        </Panel>
    </div>

    <DialogoConfirmacion
        v-model:open="dialogoActivar"
        titulo="Activar servicio"
        descripcion="Activa un servicio para un negocio sin pago en línea. Solo se activa el servicio elegido y queda registrado con tu nombre, la fecha y el motivo."
        confirmar="Activar"
        :procesando="formActivar.processing"
        :deshabilitado="
            !formActivar.negocio_id || formActivar.motivo.trim().length < 5
        "
        @confirmar="activar"
    >
        <div class="space-y-3">
            <CampoFormulario
                etiqueta="Negocio"
                para="negocio-activar"
                :error="formActivar.errors.negocio_id"
            >
                <SelectorBuscable
                    id="negocio-activar"
                    v-model="formActivar.negocio_id"
                    :url="opcionesNegocios.url()"
                    :parametros="{ solo_activos: true }"
                    etiqueta="Negocio"
                    placeholder="Busca un negocio"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Servicio"
                para="servicio-activar"
                :error="formActivar.errors.servicio"
            >
                <SelectNativo
                    id="servicio-activar"
                    v-model="formActivar.servicio"
                    :opciones="opcionesServicio"
                />
            </CampoFormulario>
            <CampoFormulario etiqueta="Tipo" para="origen">
                <SelectNativo
                    id="origen"
                    v-model="formActivar.origen"
                    :opciones="[
                        {
                            value: 'manual',
                            label: 'Activación manual (cortesía o acuerdo)',
                        },
                        {
                            value: 'fuera_de_linea',
                            label: 'Pago recibido fuera de línea',
                        },
                    ]"
                />
            </CampoFormulario>
            <div
                v-if="formActivar.origen === 'fuera_de_linea'"
                class="grid grid-cols-2 gap-3"
            >
                <CampoFormulario
                    etiqueta="Importe (MXN)"
                    para="importe"
                    :error="formActivar.errors.importe"
                >
                    <Input
                        id="importe"
                        v-model="formActivar.importe"
                        inputmode="decimal"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Referencia"
                    para="referencia"
                    :error="formActivar.errors.referencia"
                >
                    <Input
                        id="referencia"
                        v-model="formActivar.referencia"
                        maxlength="120"
                        placeholder="Folio, SPEI…"
                    />
                </CampoFormulario>
            </div>
            <CampoFormulario
                etiqueta="Motivo"
                para="motivo-activar"
                :error="formActivar.errors.motivo"
            >
                <Textarea
                    id="motivo-activar"
                    v-model="formActivar.motivo"
                    rows="2"
                    maxlength="255"
                />
            </CampoFormulario>
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="accionPendiente !== null"
        :titulo="accionPendiente?.titulo ?? ''"
        :descripcion="
            accionPendiente
                ? `${accionPendiente.contratacion.servicio} de ${accionPendiente.contratacion.negocio.nombre_comercial}. Los datos se conservan, los demás servicios no cambian y las redirecciones no se afectan.`
                : ''
        "
        confirmar="Confirmar"
        :destructivo="accionPendiente?.accion === 'suspender'"
        :procesando="formEstado.processing"
        :deshabilitado="formEstado.motivo.trim().length < 5"
        @update:open="(v: boolean) => !v && (accionPendiente = null)"
        @confirmar="confirmarAccion"
    >
        <CampoFormulario
            etiqueta="Motivo"
            para="motivo-estado"
            :error="
                formEstado.errors.motivo ||
                (formEstado.errors as Record<string, string>).contratacion
            "
        >
            <Textarea
                id="motivo-estado"
                v-model="formEstado.motivo"
                rows="2"
                maxlength="255"
            />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
