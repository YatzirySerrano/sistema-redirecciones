<script setup lang="ts">
import { History, RotateCcw } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { fechaHora, relativo } from '@/lib/formato';
import type { Documento } from '../tipos';
import { api, ErrorApi } from './api';
import { usarEditor } from './contexto';
import { conId } from './rutas';
import type { ResumenPagina } from './useEditorPagina';

/** Historial de versiones (publicaciones y guardados importantes). */
const abierto = defineModel<boolean>('open', { default: false });

const editor = usarEditor();
type Version = {
    id: number;
    motivo: string;
    etiqueta: string;
    autor: string | null;
    en: string | null;
};
const lista = ref<Version[]>([]);
const cargando = ref(false);
const elegida = ref<Version | null>(null);
const restaurando = ref(false);

watch(abierto, async (v) => {
    if (!v) {
        return;
    }

    cargando.value = true;

    try {
        lista.value = (
            await api<{ versiones: Version[] }>(editor.rutas.versiones)
        ).versiones;
    } catch (e) {
        toast.error(
            e instanceof ErrorApi
                ? e.message
                : 'No pudimos cargar el historial.',
        );
    } finally {
        cargando.value = false;
    }
});

const confirmar = async () => {
    if (!elegida.value) {
        return;
    }

    restaurando.value = true;

    try {
        const r = await api<{
            documento: Documento;
            pagina: ResumenPagina;
            mensaje: string;
        }>(conId(editor.rutas.restaurar, elegida.value.id), {
            metodo: 'POST',
            datos: { version: editor.pagina.value.version },
        });
        editor.reiniciar(r.documento, r.pagina);
        toast.success(r.mensaje);
        elegida.value = null;
        abierto.value = false;
    } catch (e) {
        if (e instanceof ErrorApi && e.estado === 409) {
            toast.error(
                'Otra persona guardó cambios. Recarga la página antes de restaurar.',
            );
        } else {
            toast.error(
                e instanceof ErrorApi
                    ? e.message
                    : 'No pudimos restaurar la versión.',
            );
        }
    } finally {
        restaurando.value = false;
    }
};
</script>

<template>
    <Sheet v-model:open="abierto">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2"
                    ><History class="size-4" /> Historial de cambios</SheetTitle
                >
                <SheetDescription
                    >Se guardan las últimas 30 versiones: cada publicación y los
                    guardados importantes.</SheetDescription
                >
            </SheetHeader>
            <div class="px-4 pb-6">
                <div v-if="cargando" class="flex justify-center py-10">
                    <Spinner />
                </div>
                <p
                    v-else-if="lista.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Aún no hay versiones guardadas.
                </p>
                <ol v-else class="grid grid-cols-1 gap-2">
                    <li
                        v-for="v in lista"
                        :key="v.id"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ v.etiqueta }}</p>
                            <p class="text-xs text-muted-foreground">
                                <time
                                    :datetime="v.en ?? undefined"
                                    :title="fechaHora(v.en)"
                                    >{{ relativo(v.en) }}</time
                                >
                                <template v-if="v.autor">
                                    · {{ v.autor }}</template
                                >
                            </p>
                        </div>
                        <Button
                            v-if="editor.editable"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10"
                            @click="elegida = v"
                        >
                            <RotateCcw /> Restaurar
                        </Button>
                    </li>
                </ol>
            </div>
        </SheetContent>
    </Sheet>

    <DialogoConfirmacion
        :open="elegida !== null"
        titulo="¿Restaurar esta versión?"
        :descripcion="
            editor.sucio.value
                ? 'Se reemplazará tu borrador y se perderán los cambios que aún no guardas (del borrador guardado conservamos una copia). No se publica hasta que tú lo decidas.'
                : 'Se copiará al borrador (guardamos una copia del actual). No se publica hasta que tú lo decidas.'
        "
        confirmar="Restaurar"
        :procesando="restaurando"
        @update:open="(v: boolean) => !v && (elegida = null)"
        @confirmar="confirmar"
    />
</template>
