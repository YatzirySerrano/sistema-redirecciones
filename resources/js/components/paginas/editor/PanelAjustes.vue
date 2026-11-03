<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, CircleX, Globe } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { disponible, update } from '@/routes/cliente/paginas/direccion';
import { index as dominios } from '@/routes/cliente/paginas/dominios';
import { api, ErrorApi } from './api';
import { usarEditor } from './contexto';
import SelectorMedio from './SelectorMedio.vue';
import type { ResumenPagina } from './useEditorPagina';

/** Dirección pública, buscadores, redes y avisos. */
const props = defineProps<{ puedePublicar: boolean; puedeDominios: boolean }>();

const editor = usarEditor();
const ajustes = computed(() => editor.documento.value.ajustes);
const perfil = computed(() => editor.documento.value.perfil);
const off = computed(() => !editor.editable);

// ---- Dirección pública ----
const slug = ref(editor.pagina.value.slug);
const revision = ref<{
    estado: 'libre' | 'ocupado' | 'revisando' | null;
    mensaje: string | null;
}>({ estado: null, mensaje: null });
const cambiando = ref(false);
const confirmar = ref(false);

const revisar = useDebounceFn(async (valor: string) => {
    try {
        const r = await api<{ disponible: boolean; mensaje: string | null }>(
            disponible({ query: { slug: valor } }).url,
        );
        revision.value = {
            estado: r.disponible ? 'libre' : 'ocupado',
            mensaje: r.mensaje,
        };
    } catch (e) {
        revision.value = {
            estado: null,
            mensaje: e instanceof ErrorApi ? e.message : null,
        };
    }
}, 400);

watch(slug, (valor) => {
    const limpio = valor.trim().toLowerCase();

    if (limpio === editor.pagina.value.slug || limpio.length < 3) {
        revision.value = {
            estado: null,
            mensaje:
                limpio.length > 0 && limpio.length < 3
                    ? 'Usa al menos 3 caracteres.'
                    : null,
        };

        return;
    }

    revision.value = { estado: 'revisando', mensaje: null };
    void revisar(limpio);
});

const guardarSlug = async () => {
    cambiando.value = true;

    try {
        const r = await api<{ pagina: ResumenPagina; mensaje: string }>(
            update().url,
            { metodo: 'PUT', datos: { slug: slug.value.trim().toLowerCase() } },
        );
        editor.pagina.value = r.pagina;
        slug.value = r.pagina.slug;
        revision.value = { estado: null, mensaje: null };
        toast.success(r.mensaje);
        confirmar.value = false;
    } catch (e) {
        toast.error(
            e instanceof ErrorApi
                ? (e.errores.slug ?? e.message)
                : 'No pudimos cambiar la dirección.',
        );
    } finally {
        cambiando.value = false;
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-6">
        <section class="grid grid-cols-1 gap-3">
            <div>
                <h3 class="text-sm font-semibold">Dirección de tu página</h3>
                <p class="text-xs text-muted-foreground">
                    Es la que compartes en tus redes, QR o tarjetas NFC.
                </p>
            </div>
            <div class="grid grid-cols-1 gap-1.5">
                <label
                    for="ajuste-slug"
                    class="text-xs font-medium text-muted-foreground"
                    >Dirección</label
                >
                <div
                    class="flex items-stretch overflow-hidden rounded-lg border focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/30"
                >
                    <span
                        class="hidden items-center bg-muted px-2.5 font-mono text-xs text-muted-foreground sm:flex"
                        >{{
                            editor.pagina.value.prefijo_url.replace(
                                /^https?:\/\//,
                                '',
                            )
                        }}</span
                    >
                    <input
                        id="ajuste-slug"
                        v-model="slug"
                        class="h-11 min-w-0 flex-1 bg-background px-3 font-mono text-sm outline-none disabled:opacity-60"
                        maxlength="40"
                        spellcheck="false"
                        autocomplete="off"
                        :disabled="!props.puedePublicar"
                        aria-describedby="ajuste-slug-estado"
                    />
                </div>
                <p
                    id="ajuste-slug-estado"
                    class="flex min-h-5 items-center gap-1.5 text-xs"
                    aria-live="polite"
                >
                    <template v-if="revision.estado === 'revisando'"
                        ><Spinner class="size-3" /> Revisando…</template
                    >
                    <template v-else-if="revision.estado === 'libre'"
                        ><CircleCheck class="size-3.5 text-success" />
                        Disponible</template
                    >
                    <template v-else-if="revision.mensaje"
                        ><CircleX class="size-3.5 text-destructive" />
                        <span class="text-destructive">{{
                            revision.mensaje
                        }}</span></template
                    >
                    <template v-else
                        ><span class="text-muted-foreground"
                            >Minúsculas, números y guiones.</span
                        ></template
                    >
                </p>
            </div>
            <Button
                v-if="props.puedePublicar"
                type="button"
                variant="outline"
                class="h-11"
                :disabled="revision.estado !== 'libre' || cambiando"
                @click="confirmar = true"
            >
                Cambiar dirección
            </Button>
            <Button
                v-if="props.puedeDominios"
                variant="ghost"
                class="h-11 justify-start"
                as-child
            >
                <Link :href="dominios()"
                    ><Globe /> Conectar mi propio dominio</Link
                >
            </Button>
        </section>

        <fieldset class="grid grid-cols-1 gap-4" :disabled="off">
            <div>
                <h3 class="text-sm font-semibold">
                    Buscadores y redes sociales
                </h3>
                <p class="text-xs text-muted-foreground">
                    Cómo se ve tu página al compartirla y en Google.
                </p>
            </div>
            <CampoFormulario
                etiqueta="Título"
                para="ajuste-titulo"
                opcional
                :ayuda="`Si lo dejas vacío se usa «${perfil.nombre || 'el nombre público'}».`"
            >
                <Input
                    id="ajuste-titulo"
                    v-model="ajustes.seo_titulo"
                    maxlength="70"
                    class="h-11"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Descripción"
                para="ajuste-descripcion"
                opcional
            >
                <Input
                    id="ajuste-descripcion"
                    v-model="ajustes.seo_descripcion"
                    maxlength="160"
                    class="h-11"
                />
            </CampoFormulario>
            <SelectorMedio
                v-model="ajustes.imagen_social"
                etiqueta="Imagen al compartir"
                uso="fondo"
                ayuda="Horizontal (1200 × 630 px aprox.). Si no eliges una, se usa la portada o el logotipo."
                :deshabilitado="off"
            />
            <SelectorMedio
                v-model="perfil.favicon"
                etiqueta="Ícono de la pestaña (favicon)"
                uso="favicon"
                ayuda="Cuadrado. Si no eliges uno, se usa tu logotipo o foto."
                :deshabilitado="off"
            />
            <label
                class="flex min-h-11 items-center justify-between gap-3 text-sm"
            >
                <span
                    ><span class="font-medium">Aparecer en buscadores</span
                    ><span class="block text-xs text-muted-foreground"
                        >Desactívalo si tu página es privada o temporal.</span
                    ></span
                >
                <Switch v-model="ajustes.indexar" :disabled="off" />
            </label>
        </fieldset>

        <fieldset class="grid grid-cols-1 gap-3" :disabled="off">
            <h3 class="text-sm font-semibold">Avisos</h3>
            <label
                class="flex min-h-11 items-center justify-between gap-3 text-sm"
            >
                <span
                    ><span class="font-medium"
                        >Avisarme por correo de mensajes nuevos</span
                    ><span class="block text-xs text-muted-foreground"
                        >Te enviamos un aviso; el mensaje se lee en tu
                        panel.</span
                    ></span
                >
                <Switch v-model="ajustes.notificar_mensajes" :disabled="off" />
            </label>
        </fieldset>

        <DialogoConfirmacion
            v-model:open="confirmar"
            titulo="¿Cambiar la dirección de tu página?"
            :descripcion="`Tu nueva dirección será ${editor.pagina.value.prefijo_url}${slug.trim().toLowerCase()}. La anterior seguirá llevando a tu página. Solo puedes cambiarla una vez al día.`"
            confirmar="Cambiar dirección"
            :procesando="cambiando"
            @confirmar="guardarSlug"
        />
    </div>
</template>
