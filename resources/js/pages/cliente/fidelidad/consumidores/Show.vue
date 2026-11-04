<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Copy,
    Download,
    Gift,
    Link2,
    Mail,
    Merge,
    Nfc,
    Pencil,
    QrCode,
    RefreshCw,
    SlidersHorizontal,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    ConsumidorFicha,
    EstadoServicio,
    ProgresoCampana,
    PuedeFidelidad,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fechaCorta, fechaHora, relativo } from '@/lib/formato';
import { inicio } from '@/routes/cliente/fidelidad';
import {
    edit,
    enlace as rutaEnlace,
    fusion,
    index,
    show,
    verificacion,
} from '@/routes/cliente/fidelidad/consumidores';
import {
    destroy as revocarCredencial,
    qr as qrCredencial,
    reemplazo,
    store as emitirCredencial,
} from '@/routes/cliente/fidelidad/credenciales';
import { anulacion as anularMovimiento } from '@/routes/cliente/fidelidad/movimientos';
import { ajuste } from '@/routes/cliente/fidelidad/participaciones';
import { anulacion as anularRecompensa } from '@/routes/cliente/fidelidad/recompensas';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Consumidores', href: index() },
            { title: 'Ficha', href: '#' },
        ],
    },
});

type Credencial = {
    id: number;
    tipo: 'nfc' | 'qr' | 'digital';
    tipo_etiqueta: string;
    codigo: string;
    url: string;
    estado: string;
    motivo: string | null;
    created_at: string | null;
    revocada_en: string | null;
};

const props = defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    consumidor: ConsumidorFicha;
    fusionado_en: number | null;
    credenciales: Credencial[];
    movimientos: {
        id: number;
        tipo: string;
        tipo_etiqueta: string;
        cantidad: number;
        ciclo: number;
        participacion_id: number;
        motivo: string | null;
        autorizado_por: string | null;
        created_at: string;
    }[];
    historial_recompensas: {
        id: number;
        descripcion: string;
        estado: string;
        estado_etiqueta: string;
        vence_en: string | null;
        canjeada_en: string | null;
        canjeada_por: string | null;
        motivo: string | null;
    }[];
    duplicados: {
        id: number;
        nombre: string;
        telefono: string | null;
        email: string | null;
    }[];
    enlace_nuevo: string | null;
}>();

const c = computed(() => props.consumidor);
const activas = computed(() =>
    props.credenciales.filter((x) => x.estado === 'activa'),
);
const revocadas = computed(() =>
    props.credenciales.filter((x) => x.estado !== 'activa'),
);

// ---- Credenciales ----
const emitir = (tipo: 'nfc' | 'qr') =>
    router.post(
        emitirCredencial.url(c.value.id),
        { tipo },
        { preserveScroll: true },
    );

const reemplazando = ref<Credencial | null>(null);
const formReemplazo = useForm({ tipo: 'nfc', motivo: '' });
const abrirReemplazo = (cred: Credencial) => {
    formReemplazo.reset();
    formReemplazo.tipo = cred.tipo === 'qr' ? 'qr' : 'nfc';
    reemplazando.value = cred;
};
const confirmarReemplazo = () =>
    reemplazando.value &&
    formReemplazo.post(reemplazo.url(reemplazando.value.id), {
        preserveScroll: true,
        onSuccess: () => (reemplazando.value = null),
    });

const revocando = ref<Credencial | null>(null);
const formRevocar = useForm({ motivo: '' });
const confirmarRevocar = () =>
    revocando.value &&
    formRevocar.delete(revocarCredencial.url(revocando.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            revocando.value = null;
            formRevocar.reset();
        },
    });

// ---- Enlace digital (recuperación presencial) ----
const dialogoEnlace = ref(false);
const formEnlace = useForm({ motivo: '', identidad_verificada: false });
const emitirEnlace = () =>
    formEnlace.post(rutaEnlace.url(c.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            dialogoEnlace.value = false;
            formEnlace.reset();
        },
    });

// ---- Ajustes y anulaciones ----
const ajustando = ref<ProgresoCampana | null>(null);
const formAjuste = useForm({ cantidad: 1, motivo: '', operacion_uuid: '' });
const abrirAjuste = (p: ProgresoCampana) => {
    formAjuste.reset();
    formAjuste.operacion_uuid = crypto.randomUUID();
    ajustando.value = p;
};
const confirmarAjuste = () =>
    ajustando.value?.participacion_id &&
    formAjuste.post(ajuste.url(ajustando.value.participacion_id), {
        preserveScroll: true,
        onSuccess: () => (ajustando.value = null),
    });

const anulando = ref<{
    tipo: 'visita' | 'recompensa';
    id: number;
    texto: string;
} | null>(null);
const formAnular = useForm({ motivo: '' });
const confirmarAnulacion = () => {
    if (!anulando.value) {
        return;
    }

    const url =
        anulando.value.tipo === 'visita'
            ? anularMovimiento.url(anulando.value.id)
            : anularRecompensa.url(anulando.value.id);
    formAnular.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            anulando.value = null;
            formAnular.reset();
        },
    });
};

// ---- Fusión ----
const fusionando = ref<{ id: number; nombre: string } | null>(null);
const formFusion = useForm({ destino_id: 0, motivo: '' });
const abrirFusion = (destino: { id: number; nombre: string }) => {
    formFusion.reset();
    formFusion.destino_id = destino.id;
    fusionando.value = destino;
};
const confirmarFusion = () =>
    formFusion.post(fusion.url(c.value.id), {
        onSuccess: () => (fusionando.value = null),
    });

const reenviarVerificacion = () =>
    router.post(verificacion.url(c.value.id), {}, { preserveScroll: true });

const iconoCredencial = (t: string) => (t === 'qr' ? QrCode : Nfc);
</script>

<template>
    <Head :title="c.nombre" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina :titulo="c.nombre" :icono="UserRound">
        <template #detalle>
            <div
                class="flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground"
            >
                <InsigniaEstado
                    :estado="c.estado === 'activo' ? 'activo' : 'suspendido'"
                    :etiqueta="
                        c.estado === 'fusionado' ? 'Fusionada' : undefined
                    "
                />
                <span v-if="c.alias">«{{ c.alias }}»</span>
                <span>{{
                    [c.telefono, c.email].filter(Boolean).join(' · ')
                }}</span>
                <span v-if="c.email && !c.email_verificado" class="text-warning"
                    >· correo sin confirmar</span
                >
                <span
                    >· aviso aceptado
                    {{ fechaCorta(c.consentimiento_en) }}</span
                >
            </div>
        </template>
        <template #acciones>
            <Button
                v-if="puede.consumidores && c.estado === 'activo'"
                variant="outline"
                as-child
            >
                <Link :href="edit(c.id)"><Pencil /> Editar</Link>
            </Button>
            <Button
                v-if="puede.consumidores && c.email && !c.email_verificado"
                variant="outline"
                @click="reenviarVerificacion"
            >
                <Mail /> Reenviar confirmación
            </Button>
        </template>
    </EncabezadoPagina>

    <p
        v-if="fusionado_en"
        class="rounded-lg border bg-muted/40 px-3 py-2 text-sm"
    >
        Esta cuenta se fusionó en otra.
        <Link :href="show(fusionado_en)" class="text-enlace underline"
            >Ver la cuenta vigente</Link
        >.
    </p>

    <div
        v-if="enlace_nuevo"
        class="space-y-2 rounded-xl border border-primary/30 bg-accent p-4 text-accent-foreground"
        role="status"
    >
        <p class="text-sm font-semibold">
            Enlace digital listo · compártelo ahora
        </p>
        <p class="text-xs">
            Por seguridad no se volverá a mostrar. El enlace anterior ya no
            funciona.
        </p>
        <div class="flex items-center gap-2 rounded-lg bg-card p-2">
            <code class="min-w-0 flex-1 truncate text-xs text-foreground">{{
                enlace_nuevo
            }}</code>
            <BotonCopiar
                :texto="enlace_nuevo"
                etiqueta="Copiar enlace"
                mensaje="Enlace copiado"
                solo-icono
                variante="ghost"
            />
        </div>
    </div>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]"
    >
        <div class="space-y-6">
            <Panel
                titulo="Progreso"
                descripcion="Cada participación conserva las condiciones con las que se inscribió."
            >
                <ul class="space-y-3">
                    <li
                        v-for="p in c.progreso"
                        :key="p.campana_id"
                        class="rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ p.campana }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ p.recompensa }} · versión {{ p.version }}
                                    <template v-if="p.ultima_visita_en">
                                        · última visita
                                        {{
                                            relativo(p.ultima_visita_en)
                                        }}</template
                                    >
                                </p>
                            </div>
                            <span class="font-semibold tabular-nums"
                                >{{ p.visitas }}/{{ p.meta }}</span
                            >
                        </div>
                        <div class="mt-3 flex gap-1" aria-hidden="true">
                            <span
                                v-for="n in p.meta"
                                :key="n"
                                class="h-2 flex-1 rounded-full"
                                :class="
                                    n <= p.visitas ? 'bg-primary' : 'bg-muted'
                                "
                            />
                        </div>
                        <Button
                            v-if="puede.ajustes && p.participacion_id"
                            variant="ghost"
                            size="sm"
                            class="mt-2"
                            @click="abrirAjuste(p)"
                        >
                            <SlidersHorizontal /> Ajustar saldo
                        </Button>
                    </li>
                </ul>
                <EstadoVacio
                    v-if="!c.progreso.length"
                    :icono="Gift"
                    titulo="Sin campañas activas"
                />
            </Panel>

            <Panel titulo="Recompensas" sin-relleno>
                <ul v-if="historial_recompensas.length" class="divide-y">
                    <li
                        v-for="r in historial_recompensas"
                        :key="r.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ r.descripcion }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="r.canjeada_en"
                                    >Entregada
                                    {{ fechaHora(r.canjeada_en) }} por
                                    {{ r.canjeada_por ?? '—' }}</template
                                >
                                <template v-else-if="r.vence_en"
                                    >Vence
                                    {{ fechaCorta(r.vence_en) }}</template
                                >
                                <template v-if="r.motivo">
                                    · {{ r.motivo }}</template
                                >
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <InsigniaEstado
                                :estado="
                                    r.estado === 'disponible'
                                        ? 'pendiente'
                                        : r.estado === 'canjeada'
                                          ? 'activo'
                                          : 'suspendido'
                                "
                                :etiqueta="r.estado_etiqueta"
                            />
                            <Button
                                v-if="
                                    puede.ajustes && r.estado === 'disponible'
                                "
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="
                                    anulando = {
                                        tipo: 'recompensa',
                                        id: r.id,
                                        texto: r.descripcion,
                                    }
                                "
                                >Anular</Button
                            >
                        </div>
                    </li>
                </ul>
                <EstadoVacio
                    v-else
                    :icono="Gift"
                    titulo="Aún no ha ganado recompensas"
                />
            </Panel>

            <Panel titulo="Historial de visitas y ajustes" sin-relleno>
                <div v-if="movimientos.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="h-9 px-4 font-medium">Fecha</th>
                                <th class="h-9 px-3 font-medium">Movimiento</th>
                                <th
                                    class="hidden h-9 px-3 font-medium sm:table-cell"
                                >
                                    Autorizó
                                </th>
                                <th class="h-9 px-3">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in movimientos"
                                :key="m.id"
                                class="border-t"
                            >
                                <td
                                    class="px-4 py-2.5 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ fechaHora(m.created_at) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    {{ m.tipo_etiqueta }}
                                    <span
                                        class="font-medium tabular-nums"
                                        :class="
                                            m.cantidad < 0
                                                ? 'text-destructive'
                                                : 'text-success'
                                        "
                                        >{{ m.cantidad > 0 ? '+' : ''
                                        }}{{ m.cantidad }}</span
                                    >
                                    <span
                                        v-if="m.motivo"
                                        class="block text-xs text-muted-foreground"
                                        >{{ m.motivo }}</span
                                    >
                                </td>
                                <td
                                    class="hidden px-3 py-2.5 text-muted-foreground sm:table-cell"
                                >
                                    {{ m.autorizado_por ?? 'Sistema' }}
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <Button
                                        v-if="
                                            puede.ajustes && m.tipo === 'visita'
                                        "
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs text-destructive hover:text-destructive"
                                        @click="
                                            anulando = {
                                                tipo: 'visita',
                                                id: m.id,
                                                texto: fechaHora(m.created_at),
                                            }
                                        "
                                        >Anular</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio
                    v-else
                    :icono="SlidersHorizontal"
                    titulo="Sin movimientos"
                    descripcion="Las visitas confirmadas aparecerán aquí."
                />
            </Panel>
        </div>

        <div class="space-y-6">
            <Panel titulo="Tarjetas y QR">
                <template #acciones>
                    <Ayuda
                        texto="Las tarjetas y QR solo identifican al cliente. Si pierde su tarjeta, reemplázala: sus visitas se conservan."
                    />
                </template>
                <ul class="space-y-2">
                    <li
                        v-for="cred in activas"
                        :key="cred.id"
                        class="rounded-lg border p-3"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <component
                                :is="iconoCredencial(cred.tipo)"
                                class="size-4 text-primary"
                            />
                            {{ cred.tipo_etiqueta }}
                            <span
                                class="ml-auto text-xs font-normal text-muted-foreground"
                                >{{ fechaCorta(cred.created_at) }}</span
                            >
                        </div>
                        <div
                            class="mt-2 flex items-center gap-1 rounded-md bg-muted/50 px-2 py-1"
                        >
                            <code class="min-w-0 flex-1 truncate text-xs">{{
                                cred.url
                            }}</code>
                            <BotonCopiar
                                :texto="cred.url"
                                etiqueta="Copiar enlace de la tarjeta"
                                mensaje="Enlace copiado"
                                solo-icono
                                variante="ghost"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                as-child
                            >
                                <a
                                    :href="
                                        qrCredencial.url(cred.id, {
                                            query: { descargar: 1 },
                                        })
                                    "
                                    ><Download /> QR</a
                                >
                            </Button>
                            <Button
                                v-if="puede.credenciales"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                @click="abrirReemplazo(cred)"
                                ><RefreshCw /> Reemplazar</Button
                            >
                            <Button
                                v-if="puede.credenciales"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs text-destructive hover:text-destructive"
                                @click="revocando = cred"
                                ><Ban /> Revocar</Button
                            >
                        </div>
                    </li>
                </ul>
                <p v-if="!activas.length" class="text-sm text-muted-foreground">
                    Sin credenciales activas.
                </p>
                <div
                    v-if="puede.credenciales && c.estado === 'activo'"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <Button variant="outline" size="sm" @click="emitir('nfc')"
                        ><Nfc /> Emitir NFC</Button
                    >
                    <Button variant="outline" size="sm" @click="emitir('qr')"
                        ><QrCode /> Emitir QR</Button
                    >
                </div>
                <details
                    v-if="revocadas.length"
                    class="mt-3 text-xs text-muted-foreground"
                >
                    <summary class="cursor-pointer">
                        {{ revocadas.length }} revocadas
                    </summary>
                    <ul class="mt-2 space-y-1">
                        <li v-for="cred in revocadas" :key="cred.id">
                            {{ cred.tipo_etiqueta }} ·
                            {{ fechaCorta(cred.revocada_en) }} ·
                            {{ cred.motivo }}
                        </li>
                    </ul>
                </details>
            </Panel>

            <Panel titulo="Enlace digital">
                <template #acciones>
                    <Ayuda
                        texto="Página personal de solo lectura para consultar el progreso desde el teléfono, sin tarjeta y sin contraseña. Generar uno nuevo invalida el anterior."
                    />
                </template>
                <p class="text-sm text-muted-foreground">
                    {{
                        c.tiene_enlace
                            ? 'El cliente tiene un enlace activo.'
                            : 'Aún no tiene enlace digital.'
                    }}
                    <template v-if="c.email_verificado">
                        Puede recuperarlo por su cuenta con su correo
                        confirmado.</template
                    >
                </p>
                <Button
                    v-if="puede.credenciales && c.estado === 'activo'"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="dialogoEnlace = true"
                >
                    <Link2 />
                    {{
                        c.tiene_enlace
                            ? 'Generar enlace nuevo'
                            : 'Generar enlace'
                    }}
                </Button>
            </Panel>

            <Panel
                v-if="duplicados.length && puede.credenciales"
                titulo="Posibles duplicados"
            >
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="d in duplicados"
                        :key="d.id"
                        class="flex items-center justify-between gap-2 rounded-lg border p-2"
                    >
                        <span class="min-w-0">
                            <Link
                                :href="show(d.id)"
                                class="block truncate font-medium hover:underline"
                                >{{ d.nombre }}</Link
                            >
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{
                                    [d.telefono, d.email]
                                        .filter(Boolean)
                                        .join(' · ') || 'Sin contacto'
                                }}</span
                            >
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="abrirFusion(d)"
                            ><Merge /> Fusionar aquí</Button
                        >
                    </li>
                </ul>
            </Panel>
        </div>
    </div>

    <!-- Diálogos -->
    <DialogoConfirmacion
        :open="reemplazando !== null"
        titulo="Reemplazar credencial"
        descripcion="La credencial actual dejará de funcionar y se emitirá otra. Las visitas y recompensas se conservan."
        confirmar="Reemplazar"
        :procesando="formReemplazo.processing"
        :deshabilitado="formReemplazo.motivo.trim().length < 5"
        @update:open="(v: boolean) => !v && (reemplazando = null)"
        @confirmar="confirmarReemplazo"
    >
        <div class="space-y-3">
            <CampoFormulario
                etiqueta="Tipo de la nueva credencial"
                para="tipo-reemplazo"
            >
                <SelectNativo
                    id="tipo-reemplazo"
                    v-model="formReemplazo.tipo"
                    :opciones="[
                        { value: 'nfc', label: 'Tarjeta NFC' },
                        { value: 'qr', label: 'Código QR' },
                    ]"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Motivo"
                para="motivo-reemplazo"
                :error="formReemplazo.errors.motivo"
                ayuda="Obligatorio (mínimo 5 caracteres) porque cambia el acceso del consumidor. Por ejemplo: «Tarjeta extraviada, identidad verificada con INE»."
            >
                <Textarea
                    id="motivo-reemplazo"
                    v-model="formReemplazo.motivo"
                    rows="2"
                    maxlength="200"
                />
            </CampoFormulario>
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="revocando !== null"
        titulo="Revocar credencial"
        descripcion="Dejará de identificar al cliente. Sus visitas se conservan."
        confirmar="Revocar"
        destructivo
        :procesando="formRevocar.processing"
        :deshabilitado="formRevocar.motivo.trim().length < 5"
        @update:open="(v: boolean) => !v && (revocando = null)"
        @confirmar="confirmarRevocar"
    >
        <CampoFormulario
            etiqueta="Motivo"
            para="motivo-revocar"
            :error="formRevocar.errors.motivo"
            ayuda="Obligatorio (mínimo 5 caracteres): protege la cuenta del consumidor y queda en el historial."
        >
            <Textarea
                id="motivo-revocar"
                v-model="formRevocar.motivo"
                rows="2"
                maxlength="200"
            />
        </CampoFormulario>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        v-model:open="dialogoEnlace"
        titulo="Generar enlace digital"
        descripcion="Úsalo también para recuperar el acceso en persona. Verifica antes la identidad del cliente."
        confirmar="Generar enlace"
        :procesando="formEnlace.processing"
        :deshabilitado="
            formEnlace.motivo.trim().length < 5 ||
            !formEnlace.identidad_verificada
        "
        @confirmar="emitirEnlace"
    >
        <div class="space-y-3">
            <CampoFormulario
                etiqueta="Motivo"
                para="motivo-enlace"
                :error="formEnlace.errors.motivo"
                ayuda="Obligatorio (mínimo 5 caracteres): es una recuperación de acceso y queda en el historial."
            >
                <Textarea
                    id="motivo-enlace"
                    v-model="formEnlace.motivo"
                    rows="2"
                    maxlength="200"
                    placeholder="Ej. Alta en mostrador / perdió su enlace"
                />
            </CampoFormulario>
            <label class="flex items-start gap-2 text-sm">
                <input
                    v-model="formEnlace.identidad_verificada"
                    type="checkbox"
                    class="mt-0.5 size-4 accent-[var(--primary)]"
                />
                Verifiqué la identidad del cliente.
            </label>
            <p
                v-if="formEnlace.errors.identidad_verificada"
                class="text-sm text-destructive"
            >
                {{ formEnlace.errors.identidad_verificada }}
            </p>
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="ajustando !== null"
        titulo="Ajustar saldo de visitas"
        descripcion="Úsalo solo para corregir errores. Queda registrado con tu nombre y el motivo. Un ajuste no puede completar la meta."
        confirmar="Registrar ajuste"
        :procesando="formAjuste.processing"
        :deshabilitado="
            formAjuste.motivo.trim().length < 5 || !formAjuste.cantidad
        "
        @update:open="(v: boolean) => !v && (ajustando = null)"
        @confirmar="confirmarAjuste"
    >
        <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
            <CampoFormulario
                etiqueta="Cantidad"
                para="cantidad"
                :error="formAjuste.errors.cantidad"
                ayuda="Negativa para restar."
            >
                <Input
                    id="cantidad"
                    v-model.number="formAjuste.cantidad"
                    type="number"
                    min="-50"
                    max="50"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Motivo"
                para="motivo-ajuste"
                :error="formAjuste.errors.motivo"
                ayuda="Obligatorio (mínimo 5 caracteres): evita ajustes de visitas sin justificación y queda en el historial."
            >
                <Textarea
                    id="motivo-ajuste"
                    v-model="formAjuste.motivo"
                    rows="2"
                    maxlength="200"
                />
            </CampoFormulario>
        </div>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="anulando !== null"
        :titulo="
            anulando?.tipo === 'visita' ? 'Anular visita' : 'Anular recompensa'
        "
        :descripcion="
            anulando
                ? `Se anulará: ${anulando.texto}. El registro original se conserva en el historial.`
                : ''
        "
        confirmar="Anular"
        destructivo
        :procesando="formAnular.processing"
        :deshabilitado="formAnular.motivo.trim().length < 5"
        @update:open="(v: boolean) => !v && (anulando = null)"
        @confirmar="confirmarAnulacion"
    >
        <CampoFormulario
            etiqueta="Motivo"
            para="motivo-anular"
            ayuda="Obligatorio (mínimo 5 caracteres): anular cambia el saldo del consumidor y queda en el historial."
            :error="
                formAnular.errors.motivo ||
                (formAnular.errors as Record<string, string>).movimiento ||
                (formAnular.errors as Record<string, string>).recompensa
            "
        >
            <Textarea
                id="motivo-anular"
                v-model="formAnular.motivo"
                rows="2"
                maxlength="200"
            />
        </CampoFormulario>
    </DialogoConfirmacion>

    <DialogoConfirmacion
        :open="fusionando !== null"
        titulo="Fusionar cuentas"
        :descripcion="
            fusionando
                ? `Esta cuenta se fusionará en la de ${fusionando.nombre}: credenciales, visitas y recompensas pasan a ella. No se borra ningún registro.`
                : ''
        "
        confirmar="Fusionar"
        :procesando="formFusion.processing"
        :deshabilitado="formFusion.motivo.trim().length < 5"
        @update:open="(v: boolean) => !v && (fusionando = null)"
        @confirmar="confirmarFusion"
    >
        <CampoFormulario
            etiqueta="Motivo"
            para="motivo-fusion"
            ayuda="Obligatorio (mínimo 5 caracteres): fusionar mueve visitas entre cuentas y queda en el historial."
            :error="formFusion.errors.motivo || formFusion.errors.destino_id"
        >
            <Textarea
                id="motivo-fusion"
                v-model="formFusion.motivo"
                rows="2"
                maxlength="255"
            />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
