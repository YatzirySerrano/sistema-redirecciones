<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, CreditCard, Layers } from '@lucide/vue';
import { ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { fechaHora, pesos } from '@/lib/formato';
import { index, revision } from '@/routes/admin/pagos';
import { index as servicios } from '@/routes/admin/servicios';
import type { MetaPaginacion, Opcion } from '@/types';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pagos', href: index() }] } });

type Pago = {
    id: number;
    negocio: { id: number; nombre_comercial: string };
    servicio: string;
    proveedor: string;
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
};

const props = defineProps<{
    pagos: { data: Pago[]; meta: MetaPaginacion };
    filtros: { estado: string; revision: string };
    estados: Opcion[];
    pendientes_revision: number;
}>();

const { filtros } = useFiltros(index.url(), { ...props.filtros });
const { puede } = usePermisos();

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
        descripcion="Pagos en línea (Stripe) y registrados fuera de línea. Nunca se guardan datos de tarjeta."
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

    <div
        v-if="pendientes_revision > 0"
        class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning"
        role="status"
    >
        <CircleAlert class="mt-0.5 size-4 shrink-0" />
        {{ pendientes_revision }} pago(s) requieren revisión (reembolsos
        parciales, disputas o datos que no coinciden).
    </div>

    <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <div class="flex flex-col gap-2 border-b p-4 sm:flex-row">
            <SelectNativo
                v-model="filtros.estado"
                :opciones="estados"
                vacio="Todos los estados"
                etiqueta="Filtrar por estado"
                class="sm:w-52"
            />
            <SelectNativo
                v-model="filtros.revision"
                :opciones="[
                    { value: '1', label: 'Solo con revisión pendiente' },
                ]"
                vacio="Todos"
                etiqueta="Revisión"
                class="sm:w-60"
            />
        </div>
        <div v-if="pagos.data.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Pagos
                </caption>
                <thead
                    class="bg-muted/40 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="h-9 px-4 font-medium">Negocio</th>
                        <th class="h-9 px-3 text-right font-medium">Importe</th>
                        <th class="h-9 px-3 font-medium">Estado</th>
                        <th class="hidden h-9 px-3 font-medium md:table-cell">
                            Medio
                        </th>
                        <th class="hidden h-9 px-3 font-medium lg:table-cell">
                            Fecha
                        </th>
                        <th class="h-9 px-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in pagos.data" :key="p.id" class="border-t">
                        <td class="px-4 py-2.5">
                            <p class="font-medium">
                                {{ p.negocio.nombre_comercial }}
                            </p>
                            <p
                                class="max-w-xs truncate text-xs text-muted-foreground"
                                :title="p.motivo ?? undefined"
                            >
                                {{ p.servicio
                                }}<template v-if="p.motivo">
                                    · {{ p.motivo }}</template
                                >
                            </p>
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ pesos(p.importe_centavos) }}
                            <span
                                v-if="p.reembolsado_centavos"
                                class="block text-xs text-destructive"
                                >−{{ pesos(p.reembolsado_centavos) }}</span
                            >
                        </td>
                        <td class="px-3 py-2.5">
                            <InsigniaEstado
                                :estado="tono(p.estado)"
                                :etiqueta="p.estado_etiqueta"
                            />
                            <span
                                v-if="p.requiere_revision"
                                class="mt-1 block text-xs text-warning"
                                >Requiere revisión</span
                            >
                        </td>
                        <td
                            class="hidden px-3 py-2.5 text-muted-foreground md:table-cell"
                        >
                            {{ p.proveedor
                            }}<template v-if="p.referencia">
                                · {{ p.referencia }}</template
                            >
                            <span
                                v-if="p.proveedor === 'Stripe'"
                                class="block text-xs"
                                >{{
                                    p.livemode ? 'Producción' : 'Pruebas'
                                }}</span
                            >
                        </td>
                        <td
                            class="hidden px-3 py-2.5 whitespace-nowrap text-muted-foreground lg:table-cell"
                        >
                            {{ fechaHora(p.pagado_en ?? p.created_at) }}
                        </td>
                        <td class="px-2 py-1.5 text-right">
                            <Button
                                v-if="
                                    p.requiere_revision &&
                                    puede('servicios.gestionar')
                                "
                                variant="ghost"
                                size="sm"
                                @click="revisando = p"
                                >Marcar revisado</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EstadoVacio v-else :icono="CreditCard" titulo="Sin pagos" />
        <Paginacion :meta="pagos.meta" nombre="pagos" />
    </section>

    <DialogoConfirmacion
        :open="revisando !== null"
        titulo="Marcar incidencia como revisada"
        descripcion="Indica qué decidiste (por ejemplo, mantener o suspender el servicio). Si debes cambiar el servicio, hazlo desde Contrataciones."
        confirmar="Marcar revisado"
        :procesando="form.processing"
        :deshabilitado="form.nota.trim().length < 5"
        @update:open="(v: boolean) => !v && (revisando = null)"
        @confirmar="confirmar"
    >
        <CampoFormulario etiqueta="Nota" para="nota" :error="form.errors.nota">
            <Textarea id="nota" v-model="form.nota" rows="2" maxlength="255" />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
