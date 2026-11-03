<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Clock,
    Globe,
    Info,
    Plus,
    RefreshCw,
    Replace,
    Star,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import type { ResumenPagina } from '@/components/paginas/editor/useEditorPagina';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { relativo } from '@/lib/formato';
import { inicio } from '@/routes/cliente/paginas';
import {
    destroy,
    index,
    principal,
    store,
    verificar,
} from '@/routes/cliente/paginas/dominios';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Página de enlaces', href: inicio() },
            { title: 'Dominio propio', href: index() },
        ],
    },
});

type Registro = {
    tipo: string;
    nombre: string;
    nombre_corto: string;
    valor: string;
    proposito: string;
};
type Dominio = {
    id: number;
    host: string;
    host_unicode: string;
    estado: 'pendiente' | 'dns_verificado' | 'activo' | 'error';
    estado_etiqueta: string;
    es_principal: boolean;
    reemplaza_a: number | null;
    ultimo_error: string | null;
    ultima_revision_en: string | null;
    proxima_revision_en: string | null;
    https_verificado_en: string | null;
    diagnostico: {
        txt_encontrado?: boolean;
        apunta_a_dasti?: boolean;
        https?: boolean | null;
    } | null;
    instrucciones: {
        es_raiz: boolean;
        registros: Registro[];
        ips_configuradas: boolean;
    };
};

defineProps<{
    puede: { dominios: boolean };
    servicio: { opera: boolean };
    pagina: ResumenPagina;
    dominios: Dominio[];
    destino_cname: string;
    maximo: number;
}>();

const form = useForm({ host: '', reemplaza: null as number | null });
const reemplazando = ref<Dominio | null>(null);
const porDesvincular = ref<Dominio | null>(null);
const verificando = ref<number | null>(null);

const agregar = () => {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            reemplazando.value = null;
        },
    });
};

const empezarCambio = (d: Dominio) => {
    reemplazando.value = d;
    form.reemplaza = d.id;
    form.host = '';
};

const revisar = (d: Dominio) =>
    router.post(
        verificar(d.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (verificando.value = d.id),
            onFinish: () => (verificando.value = null),
        },
    );

const hacerPrincipal = (d: Dominio) =>
    router.patch(principal(d.id).url, {}, { preserveScroll: true });

const desvincular = () => {
    if (porDesvincular.value) {
        router.delete(destroy(porDesvincular.value.id).url, {
            preserveScroll: true,
            onFinish: () => (porDesvincular.value = null),
        });
    }
};

const ESTILOS: Record<Dominio['estado'], string> = {
    pendiente: 'bg-muted text-muted-foreground',
    dns_verificado: 'bg-primary/10 text-primary',
    activo: 'bg-success-soft text-success',
    error: 'bg-destructive/10 text-destructive',
};

const paso = (ok: boolean | null | undefined) =>
    ok ? CircleCheck : ok === false ? CircleAlert : Clock;
</script>

<template>
    <Head title="Dominio propio" />

    <EncabezadoPagina
        titulo="Dominio propio"
        descripcion="Muestra tu página en una dirección de tu negocio, como enlaces.tunegocio.com, con HTTPS incluido."
        :icono="Globe"
    />

    <div class="flex gap-3 rounded-xl border bg-card p-4 text-sm shadow-xs">
        <Info class="mt-0.5 size-4 shrink-0 text-primary" />
        <div class="grid grid-cols-1 gap-1">
            <p>
                <strong>Conectar tu dominio está incluido</strong> en tu
                servicio. La compra y la renovación del dominio son
                responsabilidad de su dueño (se contratan con tu proveedor de
                dominios).
            </p>
            <p class="text-muted-foreground">
                Tu dirección de DASTI
                <span class="font-mono">{{ pagina.url_publica }}</span> siempre
                seguirá funcionando como respaldo, aunque el dominio venza o
                deje de apuntar aquí.
            </p>
        </div>
    </div>

    <Panel
        v-if="puede.dominios && (dominios.length < maximo || reemplazando)"
        :titulo="
            reemplazando
                ? `Cambiar ${reemplazando.host_unicode} por otro dominio`
                : 'Conectar un dominio'
        "
    >
        <form
            class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-end"
            @submit.prevent="agregar"
        >
            <CampoFormulario
                etiqueta="Dominio"
                para="dominio-host"
                :error="form.errors.host"
                ayuda="Recomendamos un subdominio (por ejemplo «enlaces.tunegocio.com»): es más fácil de configurar."
            >
                <Input
                    id="dominio-host"
                    v-model="form.host"
                    placeholder="enlaces.tunegocio.com"
                    autocomplete="off"
                    spellcheck="false"
                    inputmode="url"
                    maxlength="253"
                    class="h-11 font-mono"
                />
            </CampoFormulario>
            <div class="flex gap-2 sm:mb-6">
                <Button
                    v-if="reemplazando"
                    type="button"
                    variant="ghost"
                    class="h-11"
                    @click="
                        reemplazando = null;
                        form.reemplaza = null;
                    "
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="form.processing || !form.host.trim()"
                >
                    <Spinner v-if="form.processing" />
                    <Plus v-else />
                    {{
                        reemplazando
                            ? 'Agregar nuevo dominio'
                            : 'Agregar dominio'
                    }}
                </Button>
            </div>
        </form>
        <p v-if="reemplazando" class="mt-2 text-xs text-muted-foreground">
            El dominio actual seguirá funcionando hasta que el nuevo esté
            activo; después se desvinculará solo.
        </p>
    </Panel>

    <div
        v-for="d in dominios"
        :key="d.id"
        class="overflow-hidden rounded-xl border bg-card shadow-xs"
    >
        <header class="flex flex-wrap items-center gap-3 border-b px-5 py-4">
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="truncate font-mono text-base font-semibold">{{
                        d.host_unicode
                    }}</span>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="ESTILOS[d.estado]"
                        >{{ d.estado_etiqueta }}</span
                    >
                    <span
                        v-if="d.es_principal && d.estado === 'activo'"
                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        ><Star class="size-3" /> Principal</span
                    >
                    <span
                        v-if="d.reemplaza_a"
                        class="rounded-full bg-muted px-2.5 py-0.5 text-xs"
                        >Reemplazará al dominio actual</span
                    >
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    <template v-if="d.ultima_revision_en"
                        >Revisado
                        {{ relativo(d.ultima_revision_en) }}.</template
                    >
                    <template
                        v-if="d.estado !== 'activo' && d.proxima_revision_en"
                    >
                        Volveremos a revisar
                        {{ relativo(d.proxima_revision_en) }}.</template
                    >
                </p>
            </div>
            <div
                v-if="puede.dominios || servicio.opera"
                class="flex flex-wrap gap-2"
            >
                <Button
                    v-if="puede.dominios"
                    variant="outline"
                    class="h-10"
                    :disabled="verificando === d.id"
                    @click="revisar(d)"
                >
                    <Spinner v-if="verificando === d.id" />
                    <RefreshCw v-else />
                    Revisar ahora
                </Button>
                <Button
                    v-if="
                        puede.dominios &&
                        d.estado === 'activo' &&
                        !d.es_principal
                    "
                    variant="outline"
                    class="h-10"
                    @click="hacerPrincipal(d)"
                    ><Star /> Hacer principal</Button
                >
                <Button
                    v-if="puede.dominios && !d.reemplaza_a"
                    variant="ghost"
                    class="h-10"
                    @click="empezarCambio(d)"
                    ><Replace /> Cambiar</Button
                >
                <Button
                    variant="ghost"
                    class="h-10 text-destructive"
                    @click="porDesvincular = d"
                    ><Trash2 /> Desvincular</Button
                >
            </div>
        </header>

        <div class="grid grid-cols-1 gap-5 p-5">
            <div
                v-if="d.ultimo_error && d.estado !== 'activo'"
                class="flex gap-2 rounded-lg bg-warning-soft/60 p-3 text-sm"
                role="status"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0 text-warning" />
                <p>{{ d.ultimo_error }}</p>
            </div>

            <ol class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                <li class="flex items-center gap-2 rounded-lg border p-3">
                    <component
                        :is="paso(d.diagnostico?.txt_encontrado)"
                        class="size-4 shrink-0"
                        :class="
                            d.diagnostico?.txt_encontrado
                                ? 'text-success'
                                : 'text-muted-foreground'
                        "
                    />
                    1. Propiedad (TXT)
                </li>
                <li class="flex items-center gap-2 rounded-lg border p-3">
                    <component
                        :is="paso(d.diagnostico?.apunta_a_dasti)"
                        class="size-4 shrink-0"
                        :class="
                            d.diagnostico?.apunta_a_dasti
                                ? 'text-success'
                                : 'text-muted-foreground'
                        "
                    />
                    2. Apunta a DASTI
                </li>
                <li class="flex items-center gap-2 rounded-lg border p-3">
                    <component
                        :is="
                            paso(
                                d.estado === 'activo'
                                    ? true
                                    : d.diagnostico?.https,
                            )
                        "
                        class="size-4 shrink-0"
                        :class="
                            d.estado === 'activo'
                                ? 'text-success'
                                : 'text-muted-foreground'
                        "
                    />
                    3. HTTPS activo
                </li>
            </ol>

            <section
                v-if="d.estado !== 'activo'"
                class="grid grid-cols-1 gap-3"
            >
                <h3 class="text-sm font-semibold">
                    Crea estos registros en el panel de tu proveedor de dominio
                </h3>
                <p class="text-sm text-muted-foreground">
                    Entra a la sección «DNS» de donde compraste tu dominio
                    (GoDaddy, Hostinger, Cloudflare, Google, etc.) y agrega:
                </p>
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="r in d.instrucciones.registros"
                        :key="r.tipo + r.valor"
                        class="grid grid-cols-1 gap-2 rounded-lg border p-3 sm:grid-cols-[5rem_1fr_1fr] sm:items-center"
                    >
                        <span
                            class="w-fit rounded-md bg-muted px-2 py-1 font-mono text-xs font-semibold"
                            >{{ r.tipo }}</span
                        >
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Nombre / Host
                            </p>
                            <div class="flex items-center gap-1">
                                <code class="truncate text-sm">{{
                                    r.nombre_corto
                                }}</code>
                                <BotonCopiar
                                    :texto="r.nombre_corto"
                                    etiqueta="Copiar nombre"
                                    solo-icono
                                    variante="ghost"
                                />
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Valor / Apunta a
                            </p>
                            <div class="flex items-center gap-1">
                                <code class="truncate text-sm">{{
                                    r.valor
                                }}</code>
                                <BotonCopiar
                                    :texto="r.valor"
                                    etiqueta="Copiar valor"
                                    solo-icono
                                    variante="ghost"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground sm:col-span-3">
                            {{ r.proposito }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="
                        d.instrucciones.es_raiz &&
                        !d.instrucciones.ips_configuradas
                    "
                    class="text-sm text-warning"
                >
                    Para dominios sin subdominio necesitamos indicarte las
                    direcciones IP de DASTI. Mientras tanto, usa un subdominio
                    (por ejemplo «enlaces.{{ d.host_unicode }}») o contacta a
                    DASTI.
                </p>
                <p class="text-xs text-muted-foreground">
                    Los cambios de DNS pueden tardar desde unos minutos hasta 24
                    horas. Revisamos automáticamente; cuando el DNS esté listo
                    activamos el certificado HTTPS sin que hagas nada más.
                </p>
            </section>
            <p v-else class="text-sm text-muted-foreground">
                Tu página se muestra en
                <a
                    :href="`https://${d.host}/`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-enlace hover:underline"
                    >https://{{ d.host_unicode }}</a
                >. El certificado HTTPS se renueva automáticamente.
            </p>
        </div>
    </div>

    <p
        v-if="dominios.length === 0 && !puede.dominios"
        class="text-sm text-muted-foreground"
    >
        Aún no hay dominios conectados.
    </p>

    <DialogoConfirmacion
        :open="porDesvincular !== null"
        :titulo="`¿Desvincular ${porDesvincular?.host_unicode ?? ''}?`"
        descripcion="Tu página dejará de mostrarse en ese dominio. Seguirá disponible en tu dirección de DASTI."
        confirmar="Desvincular"
        destructivo
        @update:open="(v: boolean) => !v && (porDesvincular = null)"
        @confirmar="desvincular"
    />
</template>
