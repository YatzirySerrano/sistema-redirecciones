<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Inbox, Mail, MailOpen, Phone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import { Button } from '@/components/ui/button';
import { fechaHora, relativo } from '@/lib/formato';
import { inicio } from '@/routes/cliente/paginas';
import { destroy, index, update } from '@/routes/cliente/paginas/mensajes';
import type { Paginado } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Página de enlaces', href: inicio() },
            { title: 'Mensajes', href: index() },
        ],
    },
});

type Mensaje = {
    id: number;
    nombre: string;
    email: string | null;
    telefono: string | null;
    mensaje: string;
    leido: boolean;
    recibido_en: string | null;
};

defineProps<{
    mensajes: Paginado<Mensaje>;
    filtro: 'todos' | 'sin_leer';
    sin_leer: number;
}>();

const porEliminar = ref<Mensaje | null>(null);
const procesando = ref(false);

const marcar = (m: Mensaje, leido: boolean) =>
    router.patch(
        update(m.id).url,
        { leido },
        { preserveScroll: true, preserveState: true },
    );

const eliminar = () => {
    if (!porEliminar.value) {
        return;
    }

    router.delete(destroy(porEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (procesando.value = true),
        onFinish: () => {
            procesando.value = false;
            porEliminar.value = null;
        },
    });
};
</script>

<template>
    <Head title="Mensajes" />

    <EncabezadoPagina
        titulo="Mensajes"
        descripcion="Lo que tus visitantes te escriben desde el formulario de contacto de tu página. Solo tú y tu equipo pueden verlos."
        :icono="Inbox"
    />

    <div
        role="tablist"
        aria-label="Filtrar mensajes"
        class="flex w-fit gap-1 rounded-lg bg-muted p-1"
    >
        <Link
            :href="index()"
            role="tab"
            :aria-selected="filtro === 'todos'"
            class="flex min-h-10 items-center rounded-md px-4 text-sm font-medium"
            :class="
                filtro === 'todos'
                    ? 'bg-background shadow-xs'
                    : 'text-muted-foreground'
            "
            preserve-scroll
            >Todos</Link
        >
        <Link
            :href="index({ query: { filtro: 'sin_leer' } })"
            role="tab"
            :aria-selected="filtro === 'sin_leer'"
            class="flex min-h-10 items-center gap-2 rounded-md px-4 text-sm font-medium"
            :class="
                filtro === 'sin_leer'
                    ? 'bg-background shadow-xs'
                    : 'text-muted-foreground'
            "
            preserve-scroll
        >
            Sin leer
            <span
                v-if="sin_leer"
                class="rounded-full bg-primary px-1.5 text-[11px] text-primary-foreground"
                >{{ sin_leer }}</span
            >
        </Link>
    </div>

    <EstadoVacio
        v-if="mensajes.data.length === 0"
        class="rounded-xl border bg-card"
        :icono="Inbox"
        :titulo="
            filtro === 'sin_leer'
                ? 'No tienes mensajes sin leer'
                : 'Aún no recibes mensajes'
        "
        descripcion="Agrega un «Formulario de contacto» a tu página para que tus visitantes puedan escribirte."
    />

    <div v-else class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <ul class="divide-y">
            <li
                v-for="m in mensajes.data"
                :key="m.id"
                class="grid grid-cols-1 gap-3 p-4 sm:p-5"
                :class="m.leido ? '' : 'bg-primary/[0.03]'"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 font-medium">
                            <span
                                v-if="!m.leido"
                                class="size-2 shrink-0 rounded-full bg-primary"
                                aria-label="Sin leer"
                            />
                            {{ m.nombre }}
                        </p>
                        <div
                            class="mt-0.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
                        >
                            <a
                                v-if="m.email"
                                :href="`mailto:${m.email}`"
                                class="inline-flex items-center gap-1 hover:text-foreground hover:underline"
                                ><Mail class="size-3.5" /> {{ m.email }}</a
                            >
                            <a
                                v-if="m.telefono"
                                :href="`tel:${m.telefono.replace(/[^\d+]/g, '')}`"
                                class="inline-flex items-center gap-1 hover:text-foreground hover:underline"
                                ><Phone class="size-3.5" /> {{ m.telefono }}</a
                            >
                        </div>
                    </div>
                    <time
                        class="text-xs text-muted-foreground"
                        :datetime="m.recibido_en ?? undefined"
                        :title="fechaHora(m.recibido_en)"
                        >{{ relativo(m.recibido_en) }}</time
                    >
                </div>
                <p class="text-sm whitespace-pre-line">{{ m.mensaje }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="m.email"
                        variant="outline"
                        size="sm"
                        class="h-10"
                        as-child
                    >
                        <a
                            :href="`mailto:${m.email}?subject=${encodeURIComponent('Respuesta a tu mensaje')}`"
                            ><Mail /> Responder</a
                        >
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-10"
                        @click="marcar(m, !m.leido)"
                    >
                        <MailOpen />
                        {{
                            m.leido
                                ? 'Marcar como no leído'
                                : 'Marcar como leído'
                        }}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-10 text-destructive"
                        @click="porEliminar = m"
                        ><Trash2 /> Eliminar</Button
                    >
                </div>
            </li>
        </ul>
        <Paginacion :meta="mensajes.meta" nombre="mensajes" />
    </div>

    <DialogoConfirmacion
        :open="porEliminar !== null"
        titulo="¿Eliminar este mensaje?"
        descripcion="Se borrará de forma definitiva, junto con los datos de contacto del visitante."
        confirmar="Eliminar"
        destructivo
        :procesando="procesando"
        @update:open="(v: boolean) => !v && (porEliminar = null)"
        @confirmar="eliminar"
    />
</template>
