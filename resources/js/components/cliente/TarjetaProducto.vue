<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleAlert, MousePointerClick, PencilLine } from '@lucide/vue';
import EnlacePermanente from '@/components/comun/EnlacePermanente.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import { Button } from '@/components/ui/button';
import { dominio, numero } from '@/lib/formato';
import { show } from '@/routes/cliente/productos';
import type { Producto } from '@/types';

defineProps<{ producto: Producto }>();
</script>

<template>
    <article
        class="flex min-w-0 flex-col rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-sm"
    >
        <header class="flex items-start justify-between gap-3">
            <h3 class="min-w-0 leading-snug font-semibold">
                <Link
                    :href="show(producto.id)"
                    class="hover:text-primary focus-visible:underline focus-visible:outline-none"
                >
                    {{ producto.nombre }}
                </Link>
            </h3>
            <InsigniaEstado
                :estado="producto.estado"
                :etiqueta="producto.estado_etiqueta"
            />
        </header>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <InsigniaTipo
                :tipo="producto.tipo"
                :etiqueta="producto.tipo_etiqueta"
            />
            <span
                class="inline-flex items-center gap-1 text-xs text-muted-foreground tabular-nums"
            >
                <MousePointerClick class="size-3.5" aria-hidden="true" />
                {{ numero(producto.total_accesos) }} accesos
            </span>
        </div>

        <dl class="mt-4 space-y-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Destino actual</dt>
                <dd
                    class="mt-0.5 truncate font-medium"
                    :title="producto.url_destino ?? undefined"
                >
                    <template v-if="producto.url_destino">{{
                        dominio(producto.url_destino)
                    }}</template>
                    <span v-else class="text-warning">Sin destino</span>
                </dd>
            </div>
            <div>
                <dt class="mb-1 text-xs text-muted-foreground">
                    Enlace permanente
                </dt>
                <dd>
                    <EnlacePermanente :url="producto.url_permanente" compacto />
                </dd>
            </div>
        </dl>

        <div class="mt-auto pt-4">
            <p
                v-if="!producto.permisos.cambiar_destino"
                class="flex items-center gap-1.5 rounded-lg bg-destructive/10 px-3 py-2 text-xs font-medium text-destructive"
            >
                <CircleAlert class="size-3.5 shrink-0" /> Suspendido: contacta a
                DASTI
            </p>
            <Button v-else as-child variant="outline" class="w-full">
                <Link :href="show(producto.id)"
                    ><PencilLine /> Editar destino</Link
                >
            </Button>
        </div>
    </article>
</template>
