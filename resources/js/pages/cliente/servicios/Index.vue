<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Settings2, Sparkles } from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import IconoServicio from '@/components/servicios/IconoServicio.vue';
import type { ServicioCatalogo } from '@/components/servicios/tipos';
import { situacion } from '@/components/servicios/tipos';
import { Button } from '@/components/ui/button';
import { pesos } from '@/lib/formato';
import { index, show } from '@/routes/cliente/servicios';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Servicios', href: index() }] },
});

defineProps<{
    servicios: ServicioCatalogo[];
    puede_contratar: boolean;
}>();
</script>

<template>
    <Head title="Servicios" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <EncabezadoPagina
            titulo="Servicios de tu negocio"
            descripcion="Las redirecciones QR y NFC se incluyen con tus productos. Fidelidad y Página de enlaces se contratan por separado, con un solo pago."
            :icono="Sparkles"
        />

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="s in servicios"
                :key="s.clave"
                class="flex flex-col rounded-2xl border bg-card p-5 shadow-xs"
                :class="
                    s.estado.activo && !s.incluido
                        ? 'ring-1 ring-success/30'
                        : ''
                "
            >
                <div class="flex items-start gap-3">
                    <IconoServicio :clave="s.clave" />
                    <div class="min-w-0 flex-1">
                        <h2 class="leading-tight font-semibold">
                            {{ s.nombre }}
                        </h2>
                        <span
                            class="mt-1.5 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="situacion(s).tono"
                        >
                            <span
                                class="size-1.5 rounded-full bg-current"
                                aria-hidden="true"
                            />
                            {{ situacion(s).texto }}
                        </span>
                    </div>
                </div>

                <p
                    v-if="s.descripcion"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ s.descripcion }}
                </p>

                <ul v-if="s.beneficios.length" class="mt-4 space-y-2 text-sm">
                    <li v-for="b in s.beneficios" :key="b" class="flex gap-2">
                        <Check
                            class="mt-0.5 size-4 shrink-0 text-success"
                            aria-hidden="true"
                        />
                        <span>{{ b }}</span>
                    </li>
                </ul>

                <div class="mt-auto pt-5">
                    <p
                        v-if="!s.incluido && !s.estado.activo"
                        class="mb-3 text-sm"
                    >
                        <template v-if="s.precio_centavos">
                            <span class="text-xl font-semibold tabular-nums">{{
                                pesos(s.precio_centavos)
                            }}</span>
                            <span class="text-muted-foreground">
                                {{ s.moneda }} · pago único{{
                                    s.impuestos_incluidos
                                        ? ' · IVA incluido'
                                        : ' · más IVA'
                                }}
                            </span>
                        </template>
                        <span v-else class="text-muted-foreground"
                            >Precio por confirmar con DASTI.</span
                        >
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button v-if="s.url_modulo" as-child>
                            <Link :href="s.url_modulo">
                                <Settings2 aria-hidden="true" />
                                {{ s.incluido ? 'Ver productos' : 'Abrir' }}
                            </Link>
                        </Button>
                        <Button
                            v-if="!s.incluido"
                            :variant="s.url_modulo ? 'outline' : 'default'"
                            as-child
                        >
                            <Link :href="show(s.clave)">
                                {{
                                    s.estado.activo || s.estado.solo_lectura
                                        ? 'Detalles y pagos'
                                        : 'Conocer y contratar'
                                }}
                                <ArrowRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <p class="text-xs text-muted-foreground">
            Los pagos en línea se procesan con Openpay. DASTI nunca recibe ni
            guarda los datos de tu tarjeta. Contratar un servicio no activa los
            demás.
        </p>
    </div>
</template>
