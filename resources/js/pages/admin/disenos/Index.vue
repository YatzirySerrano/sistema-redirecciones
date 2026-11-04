<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Brush,
    CircleAlert,
    Gift,
    LayoutList,
    ShieldCheck,
    Store,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/disenos';
import { negocios as opcionesNegocios } from '@/routes/admin/opciones';
import { index as servicios } from '@/routes/admin/servicios';

/**
 * «Diseño para clientes»: el personal DASTI elige un negocio y abre su
 * editor de Fidelidad o de Página de enlaces. La pantalla solo orienta: el
 * servidor vuelve a comprobar el permiso y la contratación en cada acción.
 */
type Modulo = {
    permitido: boolean;
    estado: string | null;
    puede_abrir: boolean;
    puede_guardar: boolean;
    url: string | null;
    aviso: string | null;
};

const props = defineProps<{
    negocio: {
        id: number;
        nombre: string;
        responsable: string | null;
        activo: boolean;
    } | null;
    modulos: { fidelidad: Modulo; paginas: Modulo };
    puede_servicios: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Diseño para clientes', href: index() }],
    },
});

const elegido = ref(props.negocio ? String(props.negocio.id) : '');

watch(elegido, (valor) => {
    if (valor !== (props.negocio ? String(props.negocio.id) : '')) {
        router.get(
            index.url({ query: valor ? { negocio: valor } : {} }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    }
});

const ESTADOS: Record<string, string> = {
    activa: 'Contratado',
    restringida: 'Restringido',
    vencida: 'Vencido',
    suspendida: 'Suspendido',
    cancelada: 'Cancelado',
};

const tarjetas = [
    {
        clave: 'fidelidad' as const,
        titulo: 'Diseñar Fidelidad',
        descripcion:
            'Plantilla, colores, logotipo y presentación de visitas y recompensas de la página que ven sus clientes.',
        icono: Gift,
    },
    {
        clave: 'paginas' as const,
        titulo: 'Diseñar Página de enlaces',
        descripcion:
            'Plantillas, bloques, imágenes y publicación de su página de enlaces.',
        icono: LayoutList,
    },
];
</script>

<template>
    <Head title="Diseño para clientes" />

    <div class="space-y-6">
        <EncabezadoPagina
            titulo="Diseño para clientes"
            descripcion="Abre el editor de un negocio para crear o ajustar su diseño como servicio de DASTI."
            :icono="Brush"
        />

        <Panel
            titulo="1. Elige el negocio"
            descripcion="Busca por nombre comercial, responsable o correo."
        >
            <SelectorBuscable
                id="negocio-diseno"
                v-model="elegido"
                :url="opcionesNegocios.url()"
                :seleccion-inicial="
                    negocio
                        ? { value: String(negocio.id), label: negocio.nombre }
                        : null
                "
                etiqueta="Negocio"
                placeholder="Busca un negocio"
                placeholder-busqueda="Nombre, responsable o correo…"
                sin-resultados="Ningún negocio coincide con la búsqueda."
            />
            <p
                class="mt-3 flex items-start gap-2 text-xs text-muted-foreground"
            >
                <ShieldCheck class="mt-0.5 size-3.5 shrink-0" />
                Los cambios se guardan en el negocio elegido y quedan en su
                auditoría con tu nombre, marcados como realizados por el
                personal DASTI. No cambian visitas, campañas, pagos ni datos de
                sus clientes.
            </p>
        </Panel>

        <EstadoVacio
            v-if="!negocio"
            :icono="Store"
            titulo="Elige un negocio para empezar"
            descripcion="Verás qué editores puedes abrir según tus permisos y los servicios que tenga contratados."
        />

        <section v-else aria-labelledby="titulo-editores" class="space-y-3">
            <h2
                id="titulo-editores"
                class="flex flex-wrap items-center gap-2 text-sm font-semibold"
            >
                2. Elige qué diseñar para
                <span
                    class="rounded-md bg-primary/10 px-2 py-0.5 text-primary"
                    >{{ negocio.nombre }}</span
                >
                <span
                    v-if="!negocio.activo"
                    class="rounded-md bg-warning-soft px-2 py-0.5 text-xs text-warning"
                    >Negocio suspendido</span
                >
            </h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <article
                    v-for="t in tarjetas"
                    :key="t.clave"
                    class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component
                                :is="t.icono"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">{{ t.titulo }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t.descripcion }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 flex items-center gap-2 text-sm">
                        <dt class="text-muted-foreground">Servicio:</dt>
                        <dd class="font-medium">
                            {{
                                modulos[t.clave].estado
                                    ? (ESTADOS[modulos[t.clave].estado!] ??
                                      modulos[t.clave].estado)
                                    : 'No contratado'
                            }}
                        </dd>
                        <Ayuda
                            texto="Un permiso no sustituye la contratación: solo se puede diseñar si el negocio tiene el servicio. Guardar y publicar requieren el servicio activo."
                        />
                    </dl>

                    <p
                        v-if="modulos[t.clave].aviso"
                        class="mt-3 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
                        role="status"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" />
                        {{ modulos[t.clave].aviso }}
                    </p>

                    <div class="mt-auto flex flex-wrap gap-2 pt-4">
                        <Button
                            v-if="modulos[t.clave].url"
                            as-child
                            class="w-full sm:w-auto"
                        >
                            <Link :href="modulos[t.clave].url!">
                                Abrir editor
                                <ArrowRight />
                            </Link>
                        </Button>
                        <Button
                            v-else-if="
                                modulos[t.clave].permitido && puede_servicios
                            "
                            variant="outline"
                            as-child
                            class="w-full sm:w-auto"
                        >
                            <Link :href="servicios()">Ir a Servicios</Link>
                        </Button>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
