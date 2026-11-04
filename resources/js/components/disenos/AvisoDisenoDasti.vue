<script setup lang="ts">
import { Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Brush } from '@lucide/vue';
import { index } from '@/routes/admin/disenos';

/**
 * Aviso permanente cuando el personal DASTI edita el diseño de un negocio
 * («Diseño para clientes»): deja claro para quién se guardan los cambios y
 * sustituye las migas de pan del panel del cliente por las de la
 * administración.
 */
const props = defineProps<{
    contexto: { negocio_id: number; negocio: string; volver: string };
    editor: string;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Diseño para clientes', href: index() },
        { title: props.contexto.negocio, href: props.contexto.volver },
        { title: props.editor, href: '#' },
    ],
});
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
        role="status"
        data-aviso-dasti
    >
        <p class="flex min-w-0 items-start gap-2">
            <Brush
                class="mt-0.5 size-4 shrink-0 text-primary"
                aria-hidden="true"
            />
            <span class="min-w-0">
                Estás diseñando para
                <strong class="font-semibold">«{{ contexto.negocio }}»</strong>
                como personal DASTI. Los cambios se guardan en ese negocio y
                quedan en su auditoría con tu nombre.
            </span>
        </p>
        <Link
            :href="contexto.volver"
            class="inline-flex h-9 shrink-0 items-center gap-1.5 self-start rounded-lg px-3 font-medium text-primary hover:bg-primary/10 sm:self-auto"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Cambiar de negocio
        </Link>
    </div>
</template>
