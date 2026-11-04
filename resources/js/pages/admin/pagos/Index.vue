<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleAlert,
    CircleCheck,
    CreditCard,
    Layers,
    RefreshCw,
    Undo2,
} from '@lucide/vue';
import { ref } from 'vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFiltros } from '@/composables/useFiltros';
import { fechaHora, pesos, relativo } from '@/lib/formato';
import { consulta, index, reembolso, revision } from '@/routes/admin/pagos';
import { index as servicios } from '@/routes/admin/servicios';
import type { MetaPaginacion, Opcion } from '@/types';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pagos', href: index() }] } });

type Intento = {
    id: number;
    orden: string;
    transaccion: string | null;
    estado: string;
    estado_etiqueta: string;
    estado_externo: string | null;
    mensaje_error: string | null;
    tarjeta: string | null;
    autorizacion: string | null;
    livemode: boolean;
    consultado_en: string | null;
    created_at: string | null;
};

type Pago = {
    id: number;
    folio: string | null;
    negocio: { id: number; nombre_comercial: string };
    servicio: string;
    proveedor: string;
    en_linea: boolean;
    estado: string;
    estado_etiqueta: string;
    importe_centavos: number;
    reembolsado_centavos: number;
    moneda: string;
    referencia: string | null;
    motivo: string | null;
    requiere_revision: boolean;
    livemode: boolean;
    iniciado_por: string | null;
    pagado_en: string | null;
    created_at: string | null;
    reembolsable: boolean;
    consultable: boolean;
    intentos: Intento[];
    ajustes: {
        id: number;
        etiqueta: string;
        estado: string;
        importe_centavos: number;
        detectado_en: string;
        resuelto_en: string | null;
    }[];
};

const props = defineProps<{
    pagos: { data: Pago[]; meta: MetaPaginacion };
    filtros: {
        estado: string;
        revision: string;
        servicio: string;
        buscar: string;
    };
    estados: Opcion[];
    pendientes_revision: number;
    openpay: {
        configurado: boolean;
        modo_real: boolean;
        notificaciones: boolean;
        url_notificaciones: string;
        codigo_verificacion: string | null;
        verificacion_recibida_en: string | null;
        ultima_notificacion_en: string | null;
        abiertos: number;
    };
    puede_gestionar: boolean;
    puede_reembolsar: boolean;
}>();

const { filtros } = useFiltros(index.url(), { ...props.filtros });

const abierto = ref<number | null>(null);
const alternar = (id: number) =>
    (abierto.value = abierto.value === id ? null : id);

// Revisión de incidencias
const revisando = ref<Pago | null>(null);
const form = useForm({ nota: '' });
const confirmar = () =>
    revisando.value &&
    form.patch(revision.url(revisando.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            revisando.value = null;
            form.reset();
        },
    });

// Consulta del estado vigente en Openpay
const consultando = ref<number | null>(null);
const formConsulta = useForm({});
const consultar = (p: Pago) => {
    consultando.value = p.id;
    formConsulta.post(consulta.url(p.id), {
        preserveScroll: true,
        onFinish: () => (consultando.value = null),
    });
};

// Reembolso solicitado a Openpay
const reembolsando = ref<Pago | null>(null);
const formReembolso = useForm({
    tipo: 'total' as 'total' | 'parcial',
    importe: '',
    motivo: '',
});
const pedirReembolso = (p: Pago) => {
    formReembolso.reset();
    formReembolso.clearErrors();
    reembolsando.value = p;
};
const confirmarReembolso = () =>
    reembolsando.value &&
    formReembolso.post(reembolso.url(reembolsando.value.id), {
        preserveScroll: true,
        onSuccess: () => (reembolsando.value = null),
    });

const tono = (e: string) =>
    e === 'pagado'
        ? 'activo'
        : e === 'pendiente' || e === 'reembolso_parcial' || e === 'en_disputa'
          ? 'pendiente'
          : 'suspendido';
</script>

<template>
    <Head title="Pagos" />

    <EncabezadoPagina
        titulo="Pagos"
        descripcion="Órdenes de pago en línea (Openpay) y pagos registrados fuera de línea, con sus intentos de cobro, reembolsos y contracargos. Nunca se guardan datos de tarjeta."
        :icono="CreditCard"
    >
        <template #acciones>
            <Button variant="outline" as-child
                ><Link :href="servicios()"
                    ><Layers /> Contrataciones</Link
                ></Button
            >
        </template>
    </EncabezadoPagina>

    <section
        class="grid grid-cols-1 gap-4 rounded-xl border bg-card p-4 shadow-xs md:grid-cols-2"
        aria-labelledby="estado-openpay"
    >
        <div class="min-w-0 space-y-2">
            <h2 id="estado-openpay" class="text-sm font-semibold">Openpay</h2>
            <ul class="space-y-1.5 text-sm">
                <li class="flex items-start gap-2">
                    <component
                        :is="openpay.configurado ? CircleCheck : CircleAlert"
                        class="mt-0.5 size-4 shrink-0"
                        :class="
                            openpay.configurado
                                ? 'text-success'
                                : 'text-warning'
                        "
                        aria-hidden="true"
                    />
                    {{
                        openpay.configurado
                            ? `Credenciales configuradas · ambiente ${openpay.modo_real ? 'de PRODUCCIÓN' : 'de pruebas (sandbox)'}`
                            : 'Sin credenciales: el pago en línea está deshabilitado'
                    }}
                </li>
                <li class="flex items-start gap-2">
                    <component
                        :is="openpay.notificaciones ? CircleCheck : CircleAlert"
                        class="mt-0.5 size-4 shrink-0"
                        :class="
                            openpay.notificaciones
                                ? 'text-success'
                                : 'text-warning'
                        "
                        aria-hidden="true"
                    />
                    {{
                        openpay.notificaciones
                            ? 'Notificaciones protegidas con usuario y contraseña'
                            : 'Notificaciones sin configurar (se rechazan)'
                    }}
                </li>
                <li class="text-muted-foreground">
                    Última notificación:
                    {{
                        openpay.ultima_notificacion_en
                            ? relativo(openpay.ultima_notificacion_en)
                            : 'ninguna'
                    }}
                    · Cobros en espera: {{ openpay.abiertos }}
                </li>
            </ul>
        </div>
        <div class="min-w-0 space-y-2 text-sm">
            <p class="text-xs text-muted-foreground">
                Dirección de notificaciones para el panel de Openpay
            </p>
            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded-md bg-muted px-2 py-1.5 text-xs"
                    >{{ openpay.url_notificaciones }}</code
                >
                <BotonCopiar
                    :texto="openpay.url_notificaciones"
                    solo-icono
                    etiqueta="Copiar dirección"
                />
            </div>
            <p
                v-if="openpay.codigo_verificacion"
                class="text-xs text-muted-foreground"
            >
                Código de verificación recibido
                {{ relativo(openpay.verificacion_recibida_en) }}:
                <code
                    class="rounded bg-muted px-1.5 py-0.5 font-semibold text-foreground"
                    >{{ openpay.codigo_verificacion }}</code
                >
                — introdúcelo en Openpay › Webhooks › Verificar.
            </p>
        </div>
    </section>

    <div
        v-if="pendientes_revision > 0"
        class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning"
        role="status"
    >
        <CircleAlert class="mt-0.5 size-4 shrink-0" />
        {{ pendientes_revision }} pago(s) requieren revisión (reembolsos
        parciales, contracargos o datos que no coinciden con Openpay).
    </div>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div class="flex flex-col gap-2 border-b p-4 lg:flex-row">
            <CampoBusqueda
                v-model="filtros.buscar"
                placeholder="Folio o negocio"
                etiqueta="Buscar por folio o negocio"
            />
            <SelectNativo
                v-model="filtros.servicio"
                :opciones="[
                    { value: 'fidelidad', label: 'Fidelidad' },
                    { value: 'pagina_enlaces', label: 'Página de enlaces' },
                ]"
                vacio="Todos los servicios"
                etiqueta="Filtrar por servicio"
                class="lg:w-48"
            />
            <SelectNativo
                v-model="filtros.estado"
                :opciones="estados"
                vacio="Todos los estados"
                etiqueta="Filtrar por estado"
                class="lg:w-48"
            />
            <SelectNativo
                v-model="filtros.revision"
                :opciones="[
                    { value: '1', label: 'Solo con revisión pendiente' },
                ]"
                vacio="Con y sin revisión"
                etiqueta="Revisión"
                class="lg:w-56"
            />
        </div>

        <ul v-if="pagos.data.length" class="divide-y">
            <li v-for="p in pagos.data" :key="p.id">
                <button
                    type="button"
                    class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 text-left text-sm transition hover:bg-muted/40 md:grid-cols-[minmax(0,1.6fr)_8rem_10rem_minmax(0,1fr)_1.5rem]"
                    :aria-expanded="abierto === p.id"
                    :aria-controls="`pago-${p.id}`"
                    @click="alternar(p.id)"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{
                            p.negocio.nombre_comercial
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ p.servicio }} ·
                            {{ p.folio ?? `#${p.id}` }}</span
                        >
                    </span>
                    <span class="text-right whitespace-nowrap tabular-nums">
                        {{ pesos(p.importe_centavos) }}
                        <span
                            v-if="p.reembolsado_centavos"
                            class="block text-xs text-destructive"
                            >−{{ pesos(p.reembolsado_centavos) }}</span
                        >
                    </span>
                    <span class="col-span-2 md:col-span-1">
                        <InsigniaEstado
                            :estado="tono(p.estado)"
                            :etiqueta="p.estado_etiqueta"
                        />
                        <span
                            v-if="p.requiere_revision"
                            class="ml-2 text-xs font-medium text-warning md:mt-1 md:ml-0 md:block"
                            >Requiere revisión</span
                        >
                    </span>
                    <span
                        class="hidden truncate text-muted-foreground md:block"
                    >
                        {{ p.proveedor }}
                        <span class="block text-xs">{{
                            fechaHora(p.pagado_en ?? p.created_at)
                        }}</span>
                    </span>
                    <ChevronDown
                        class="hidden size-4 text-muted-foreground transition md:block"
                        :class="{ 'rotate-180': abierto === p.id }"
                        aria-hidden="true"
                    />
                </button>

                <div
                    v-if="abierto === p.id"
                    :id="`pago-${p.id}`"
                    class="space-y-4 border-t bg-muted/20 px-4 py-4 text-sm"
                >
                    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">Medio</dt>
                            <dd>
                                {{ p.proveedor
                                }}<template v-if="p.en_linea">
                                    ·
                                    {{
                                        p.livemode ? 'Producción' : 'Pruebas'
                                    }}</template
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Iniciado por
                            </dt>
                            <dd>{{ p.iniciado_por ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Referencia
                            </dt>
                            <dd class="break-all">
                                {{ p.referencia ?? '—' }}
                            </dd>
                        </div>
                        <div v-if="p.motivo" class="sm:col-span-3">
                            <dt class="text-xs text-muted-foreground">
                                Detalle
                            </dt>
                            <dd>{{ p.motivo }}</dd>
                        </div>
                    </dl>

                    <div v-if="p.intentos.length">
                        <h3 class="mb-2 text-xs font-semibold">
                            Intentos de cobro
                        </h3>
                        <ul class="space-y-2">
                            <li
                                v-for="i in p.intentos"
                                :key="i.id"
                                class="rounded-lg border bg-card px-3 py-2"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span class="font-medium">{{
                                        i.estado_etiqueta
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ fechaHora(i.created_at) }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 text-xs break-all text-muted-foreground"
                                >
                                    Orden {{ i.orden }}
                                    <template v-if="i.transaccion">
                                        · Transacción
                                        {{ i.transaccion }}</template
                                    >
                                    <template v-if="i.tarjeta">
                                        · {{ i.tarjeta }}</template
                                    >
                                    <template v-if="i.autorizacion">
                                        · Autorización
                                        {{ i.autorizacion }}</template
                                    >
                                </p>
                                <p
                                    v-if="i.mensaje_error"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ i.mensaje_error }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div v-if="p.ajustes.length">
                        <h3 class="mb-2 text-xs font-semibold">
                            Reembolsos y contracargos
                        </h3>
                        <ul class="space-y-1">
                            <li
                                v-for="a in p.ajustes"
                                :key="a.id"
                                class="flex flex-wrap justify-between gap-2"
                            >
                                <span>{{ a.etiqueta }}</span>
                                <span class="text-muted-foreground tabular-nums"
                                    >{{ pesos(a.importe_centavos) }} ·
                                    {{ fechaHora(a.detectado_en) }}</span
                                >
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="puede_gestionar || puede_reembolsar"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="puede_gestionar && p.consultable"
                            variant="outline"
                            size="sm"
                            :disabled="consultando === p.id"
                            @click="consultar(p)"
                        >
                            <RefreshCw
                                :class="{
                                    'animate-spin': consultando === p.id,
                                }"
                            />
                            Consultar en Openpay
                        </Button>
                        <Button
                            v-if="puede_reembolsar && p.reembolsable"
                            variant="outline"
                            size="sm"
                            @click="pedirReembolso(p)"
                            ><Undo2 /> Reembolsar</Button
                        >
                        <Button
                            v-if="puede_gestionar && p.requiere_revision"
                            size="sm"
                            @click="revisando = p"
                            >Marcar revisado</Button
                        >
                    </div>
                </div>
            </li>
        </ul>
        <EstadoVacio
            v-else
            :icono="CreditCard"
            titulo="Sin pagos"
            descripcion="Cuando un negocio contrate un servicio, su orden de pago aparecerá aquí."
        />
        <Paginacion :meta="pagos.meta" nombre="pagos" />
    </section>

    <DialogoConfirmacion
        :open="revisando !== null"
        titulo="Marcar incidencia como revisada"
        descripcion="Quedará registrado quién la revisó y cuándo. Si debes cambiar el servicio, hazlo desde Contrataciones."
        confirmar="Marcar revisado"
        :procesando="form.processing"
        @update:open="(v: boolean) => !v && (revisando = null)"
        @confirmar="confirmar"
    >
        <CampoFormulario
            etiqueta="Nota"
            para="nota"
            opcional
            ayuda="Puedes anotar qué decidiste (por ejemplo, mantener o suspender el servicio)."
            :error="form.errors.nota"
        >
            <Textarea id="nota" v-model="form.nota" rows="2" maxlength="255" />
        </CampoFormulario>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="reembolsando !== null"
        titulo="Reembolsar pago"
        :descripcion="
            reembolsando
                ? `${reembolsando.negocio.nombre_comercial} · ${reembolsando.folio}. Openpay devolverá el dinero a la tarjeta. Un reembolso total suspende el servicio (si no hay otro periodo vigente); uno parcial queda en revisión.`
                : ''
        "
        confirmar="Solicitar reembolso"
        destructivo
        :procesando="formReembolso.processing"
        :deshabilitado="
            formReembolso.motivo.trim().length < 5 ||
            (formReembolso.tipo === 'parcial' && !formReembolso.importe)
        "
        @update:open="(v: boolean) => !v && (reembolsando = null)"
        @confirmar="confirmarReembolso"
    >
        <div class="space-y-3">
            <CampoFormulario etiqueta="Tipo" para="tipo-reembolso">
                <SelectNativo
                    id="tipo-reembolso"
                    v-model="formReembolso.tipo"
                    :opciones="[
                        { value: 'total', label: 'Total' },
                        { value: 'parcial', label: 'Parcial' },
                    ]"
                />
            </CampoFormulario>
            <CampoFormulario
                v-if="formReembolso.tipo === 'parcial'"
                etiqueta="Importe a devolver (MXN)"
                para="importe-reembolso"
                :error="formReembolso.errors.importe"
            >
                <Input
                    id="importe-reembolso"
                    v-model="formReembolso.importe"
                    inputmode="decimal"
                />
            </CampoFormulario>
            <p
                v-else-if="formReembolso.errors.importe"
                class="text-sm text-destructive"
            >
                {{ formReembolso.errors.importe }}
            </p>
            <CampoFormulario
                etiqueta="Motivo"
                para="motivo-reembolso"
                ayuda="Obligatorio (mínimo 5 caracteres): un reembolso devuelve dinero y debe quedar justificado para conciliación y control antifraude."
                :error="formReembolso.errors.motivo"
            >
                <Textarea
                    id="motivo-reembolso"
                    v-model="formReembolso.motivo"
                    rows="2"
                    maxlength="200"
                />
            </CampoFormulario>
        </div>
    </DialogoConfirmacion>
</template>
