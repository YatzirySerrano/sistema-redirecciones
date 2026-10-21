<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ScrollText } from '@lucide/vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import { fechaHora, relativo } from '@/lib/formato';
import { show as negocioShow } from '@/routes/admin/negocios';
import type { RegistroAuditoria } from '@/types';

withDefaults(
    defineProps<{ registros: RegistroAuditoria[]; mostrarNegocio?: boolean }>(),
    {
        mostrarNegocio: false,
    },
);

const ETIQUETAS_CAMPO: Record<string, string> = {
    nombre_comercial: 'Nombre comercial',
    responsable: 'Responsable',
    email_contacto: 'Correo de contacto',
    telefono: 'Teléfono',
    notas_internas: 'Notas internas',
    estado: 'Estado',
    motivo: 'Motivo',
    url_destino: 'URL de destino',
    nombre: 'Nombre',
    name: 'Nombre',
    email: 'Correo',
    tipo: 'Tipo',
    codigo: 'Código',
    rol: 'Rol',
    metodo_acceso: 'Método de acceso',
    negocio_id: 'Negocio (id)',
    negocio_anterior: 'Negocio anterior',
    negocio_nuevo: 'Negocio nuevo',
    destino_conservado: 'Destino conservado',
    estado_preparacion: 'Preparación',
    fecha_entrega: 'Fecha de entrega',
    referencia_comercial: 'Referencia comercial',
    permitir_destinos_http: 'Permitir destinos http',
    clientes_descargan_qr: 'Clientes descargan QR',
    clientes_ven_estadisticas: 'Clientes ven estadísticas',
    dominios_bloqueados: 'Dominios bloqueados',
    dias_retencion_eventos: 'Días de retención',
};

const valor = (v: unknown): string => {
    if (v === null || v === undefined || v === '') {
        return '—';
    }

    if (typeof v === 'boolean') {
        return v ? 'Sí' : 'No';
    }

    if (Array.isArray(v)) {
        return v.join(', ') || '—';
    }

    return String(v);
};

const campos = (r: RegistroAuditoria) =>
    Array.from(
        new Set([
            ...Object.keys(r.valores_anteriores ?? {}),
            ...Object.keys(r.valores_nuevos ?? {}),
        ]),
    );
</script>

<template>
    <EstadoVacio
        v-if="registros.length === 0"
        :icono="ScrollText"
        titulo="Sin actividad registrada"
    />
    <ul v-else class="divide-y" role="list">
        <li
            v-for="r in registros"
            :key="r.id"
            class="py-3 first:pt-0 last:pb-0"
        >
            <details class="group">
                <summary
                    class="flex cursor-pointer list-none items-start gap-3 rounded-md focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm">
                            <span class="font-medium">{{
                                r.accion_etiqueta
                            }}</span>
                            <span class="text-muted-foreground">
                                — {{ r.descripcion }}</span
                            >
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ r.usuario?.name ?? 'Sistema' }}
                            <template v-if="mostrarNegocio && r.negocio">
                                ·
                                <Link
                                    :href="negocioShow(r.negocio.id)"
                                    class="hover:text-primary hover:underline"
                                    @click.stop
                                    >{{ r.negocio.nombre_comercial }}</Link
                                >
                            </template>
                            ·
                            <time
                                :datetime="r.created_at"
                                :title="fechaHora(r.created_at)"
                                >{{ relativo(r.created_at) }}</time
                            >
                        </p>
                    </div>
                    <ChevronDown
                        v-if="campos(r).length > 0 || r.ip"
                        class="mt-1 size-4 shrink-0 text-muted-foreground transition group-open:rotate-180"
                        aria-hidden="true"
                    />
                </summary>
                <div
                    v-if="campos(r).length > 0 || r.ip"
                    class="mt-2 overflow-x-auto rounded-lg border bg-muted/40 text-xs"
                >
                    <table class="w-full">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th scope="col" class="px-3 py-1.5 font-medium">
                                    Campo
                                </th>
                                <th scope="col" class="px-3 py-1.5 font-medium">
                                    Antes
                                </th>
                                <th scope="col" class="px-3 py-1.5 font-medium">
                                    Después
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="campo in campos(r)"
                                :key="campo"
                                class="border-t align-top"
                            >
                                <td
                                    class="px-3 py-1.5 font-medium whitespace-nowrap"
                                >
                                    {{ ETIQUETAS_CAMPO[campo] ?? campo }}
                                </td>
                                <td
                                    class="px-3 py-1.5 break-all text-muted-foreground"
                                >
                                    {{ valor(r.valores_anteriores?.[campo]) }}
                                </td>
                                <td class="px-3 py-1.5 break-all">
                                    {{ valor(r.valores_nuevos?.[campo]) }}
                                </td>
                            </tr>
                            <tr v-if="r.ip" class="border-t">
                                <td class="px-3 py-1.5 font-medium">
                                    IP del actor
                                </td>
                                <td colspan="2" class="px-3 py-1.5 font-mono">
                                    {{ r.ip }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </li>
    </ul>
</template>
