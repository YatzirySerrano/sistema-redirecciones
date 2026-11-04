<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChartNoAxesColumn,
    CircleAlert,
    Download,
    ExternalLink,
    Eye,
    Globe,
    Inbox,
    Info,
    LayoutTemplate,
    Link2,
    MousePointerClick,
    Palette,
    Percent,
} from '@lucide/vue';
import BotonCopiar from '@/components/comun/BotonCopiar.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import type { ResumenPagina } from '@/components/paginas/editor/useEditorPagina';
import { Button } from '@/components/ui/button';
import { numero, relativo } from '@/lib/formato';
import { editor, inicio, qr } from '@/routes/cliente/paginas';
import { index as dominios } from '@/routes/cliente/paginas/dominios';
import { index as estadisticas } from '@/routes/cliente/paginas/estadisticas';
import { index as mensajes } from '@/routes/cliente/paginas/mensajes';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Página de enlaces', href: inicio() }] },
});

const props = defineProps<{
    servicio: {
        activo: boolean;
        solo_lectura: boolean;
        estado: string | null;
        opera: boolean;
    };
    puede: {
        editar: boolean;
        publicar: boolean;
        dominios: boolean;
        ver_dominios: boolean;
        mensajes: boolean;
        estadisticas: boolean;
    };
    pagina: ResumenPagina;
    totales: { visitas: number; clics: number; contactos: number; ctr: number };
    mensajes_sin_leer: number | null;
    dominios: {
        id: number;
        host: string;
        estado: string;
        estado_etiqueta: string;
        es_principal: boolean;
    }[];
    elementos: number;
}>();

const urlQr = (formato: 'png' | 'svg', descargar = false) =>
    qr({ query: { formato, ...(descargar ? { descargar: 1 } : {}) } }).url;
</script>

<template>
    <Head title="Página de enlaces" />

    <EncabezadoPagina
        titulo="Página de enlaces"
        descripcion="Tus redes, contactos y enlaces en una sola dirección para tu bio, tus QR y tus tarjetas NFC."
        :icono="Link2"
    >
        <template #acciones>
            <Button as-child class="h-11">
                <Link :href="editor()"
                    ><Palette />
                    {{
                        puede.editar ? 'Diseñar mi página' : 'Ver el editor'
                    }}</Link
                >
            </Button>
            <Button
                v-if="pagina.publicada"
                variant="outline"
                as-child
                class="h-11"
            >
                <a
                    :href="pagina.url_canonica"
                    target="_blank"
                    rel="noopener noreferrer"
                    ><ExternalLink /> Ver página</a
                >
            </Button>
        </template>
    </EncabezadoPagina>

    <div
        v-if="!servicio.opera"
        class="flex gap-3 rounded-xl border border-warning/40 bg-warning-soft/50 p-4 text-sm"
        role="status"
    >
        <CircleAlert class="mt-0.5 size-4 shrink-0 text-warning" />
        <div>
            <p class="font-medium">Tu página está en modo de solo lectura</p>
            <p class="text-muted-foreground">
                {{
                    servicio.estado === 'vencida'
                        ? 'El servicio venció. Tu contenido se conserva, pero no puedes editarlo y los visitantes ven «página no disponible».'
                        : 'El servicio está en revisión. Tu contenido se conserva; mientras tanto no puedes editarlo.'
                }}
                Contacta a DASTI para reactivarlo.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <Panel
            titulo="Tu dirección pública"
            descripcion="Compártela en tus redes o úsala como destino de tus QR y NFC."
        >
            <div class="grid grid-cols-1 gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            pagina.publicada
                                ? 'bg-success-soft text-success'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ pagina.estado_etiqueta }}</span
                    >
                    <span
                        v-if="pagina.publicada && pagina.cambios_sin_publicar"
                        class="rounded-full bg-warning-soft px-2.5 py-0.5 text-xs font-medium text-warning"
                        >Hay cambios sin publicar</span
                    >
                    <span
                        v-if="pagina.publicado_en"
                        class="text-xs text-muted-foreground"
                        >Publicada {{ relativo(pagina.publicado_en) }}</span
                    >
                </div>
                <div
                    class="flex flex-col gap-2 rounded-lg border bg-muted/40 p-3 sm:flex-row sm:items-center"
                >
                    <code
                        class="min-w-0 flex-1 truncate text-sm font-medium break-all"
                        >{{ pagina.url_canonica }}</code
                    >
                    <BotonCopiar
                        :texto="pagina.url_canonica"
                        etiqueta="Copiar dirección"
                        mensaje="Dirección copiada"
                    />
                </div>
                <p
                    v-if="pagina.dominio_principal"
                    class="text-xs text-muted-foreground"
                >
                    También funciona en la dirección de respaldo:
                    <span class="font-mono">{{ pagina.url_publica }}</span>
                </p>
                <p
                    v-if="!pagina.publicada"
                    class="flex gap-2 text-sm text-muted-foreground"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    Tu página aún no es visible. Diséñala en el editor y pulsa
                    «Publicar» cuando esté lista.
                </p>
                <p class="flex gap-2 text-sm text-muted-foreground">
                    <Info class="mt-0.5 size-4 shrink-0" />
                    Para que un QR o una tarjeta NFC lleve aquí, abre el
                    producto en «Productos» y elige «Usar mi página de enlaces»
                    como destino.
                </p>
            </div>
        </Panel>

        <Panel titulo="Código QR">
            <div class="flex flex-col items-center gap-3">
                <img
                    :src="urlQr('svg')"
                    alt="Código QR de tu página de enlaces"
                    class="size-40 rounded-lg border bg-white p-2"
                />
                <div class="flex w-full gap-2">
                    <Button variant="outline" class="h-11 flex-1" as-child>
                        <a :href="urlQr('png', true)"><Download /> PNG</a>
                    </Button>
                    <Button variant="outline" class="h-11 flex-1" as-child>
                        <a :href="urlQr('svg', true)"><Download /> SVG</a>
                    </Button>
                </div>
                <p class="text-center text-xs text-muted-foreground">
                    El QR lleva siempre a tu dirección principal.
                </p>
            </div>
        </Panel>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Visitas · 30 días"
            :valor="numero(totales.visitas)"
            :icono="Eye"
            :href="puede.estadisticas ? estadisticas() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Clics · 30 días"
            :valor="numero(totales.clics)"
            :icono="MousePointerClick"
            :href="puede.estadisticas ? estadisticas() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Tasa de clics"
            :valor="`${totales.ctr} %`"
            :icono="Percent"
            detalle="Clics por cada 100 visitas"
        />
        <TarjetaIndicador
            v-if="mensajes_sin_leer !== null"
            etiqueta="Mensajes sin leer"
            :valor="numero(mensajes_sin_leer)"
            :icono="Inbox"
            :href="mensajes()"
            :tono="mensajes_sin_leer > 0 ? 'alerta' : 'normal'"
        />
        <TarjetaIndicador
            v-else
            etiqueta="Elementos publicados"
            :valor="numero(elementos)"
            :icono="ChartNoAxesColumn"
        />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Link
            :href="editor()"
            class="group rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40"
        >
            <Palette class="size-5 text-primary" />
            <p class="mt-2 text-sm font-semibold">Editor visual</p>
            <p class="text-xs text-muted-foreground">
                Contenido, diseño y subpáginas.
            </p>
        </Link>
        <Link
            v-if="puede.editar"
            :href="editor({ query: { plantillas: 1 } })"
            class="group rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40"
        >
            <LayoutTemplate class="size-5 text-primary" />
            <p class="mt-2 text-sm font-semibold">Plantillas de diseño</p>
            <p class="text-xs text-muted-foreground">
                Diseños listos para tu tipo de negocio. Elige uno y
                personalízalo.
            </p>
        </Link>
        <Link
            v-if="puede.estadisticas"
            :href="estadisticas()"
            class="group rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40"
        >
            <ChartNoAxesColumn class="size-5 text-primary" />
            <p class="mt-2 text-sm font-semibold">Estadísticas</p>
            <p class="text-xs text-muted-foreground">
                Visitas, clics por enlace y procedencia.
            </p>
        </Link>
        <Link
            v-if="puede.mensajes"
            :href="mensajes()"
            class="group rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40"
        >
            <Inbox class="size-5 text-primary" />
            <p class="mt-2 text-sm font-semibold">Mensajes</p>
            <p class="text-xs text-muted-foreground">
                Lo que te escriben desde el formulario.
            </p>
        </Link>
        <Link
            v-if="puede.ver_dominios"
            :href="dominios()"
            class="group rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40"
        >
            <Globe class="size-5 text-primary" />
            <p class="mt-2 text-sm font-semibold">Dominio propio</p>
            <p class="text-xs text-muted-foreground">
                <template v-if="props.dominios.length"
                    >{{ props.dominios[0].host }} ·
                    {{ props.dominios[0].estado_etiqueta }}</template
                >
                <template v-else
                    >Usa una dirección como enlaces.tunegocio.com.</template
                >
            </p>
        </Link>
    </div>
</template>
