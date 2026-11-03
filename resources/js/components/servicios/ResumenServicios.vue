<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    HeartHandshake,
    LayoutList,
    Nfc,
} from '@lucide/vue';
import { computed } from 'vue';
import { index, show } from '@/routes/cliente/servicios';

/**
 * Resumen de los servicios del negocio en el inicio: incluido, contratados
 * y disponibles. Los no contratados llevan a su presentación comercial.
 */
const page = usePage();

const filas = computed(() => {
    const s = page.props.servicios;
    const estado = (clave: 'fidelidad' | 'pagina_enlaces') => {
        const e = s?.[clave];

        if (e?.activo) {
            return { texto: 'Contratado', activo: true };
        }

        if (e?.solo_lectura) {
            return { texto: 'Solo lectura', activo: true };
        }

        if (e?.estado === 'suspendida') {
            return { texto: 'Suspendido', activo: false };
        }

        return { texto: 'Disponible', activo: false };
    };

    return [
        {
            clave: 'redirecciones',
            nombre: 'Redirecciones QR y NFC',
            icono: Nfc,
            texto: 'Incluido',
            activo: true,
        },
        {
            clave: 'fidelidad',
            nombre: 'Fidelidad',
            icono: HeartHandshake,
            ...estado('fidelidad'),
        },
        {
            clave: 'pagina_enlaces',
            nombre: 'Página de enlaces',
            icono: LayoutList,
            ...estado('pagina_enlaces'),
        },
    ];
});
</script>

<template>
    <section
        class="rounded-xl border bg-card p-4 shadow-xs"
        aria-labelledby="resumen-servicios"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 id="resumen-servicios" class="text-sm font-semibold">
                Servicios de tu negocio
            </h2>
            <Link
                :href="index()"
                class="inline-flex items-center gap-1 text-xs font-medium text-enlace hover:underline"
                >Ver todos <ArrowRight class="size-3.5" aria-hidden="true"
            /></Link>
        </div>
        <ul class="grid gap-2 sm:grid-cols-3">
            <li v-for="f in filas" :key="f.clave">
                <Link
                    :href="
                        f.clave === 'redirecciones' ? index() : show(f.clave)
                    "
                    class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition hover:bg-muted/50"
                >
                    <component
                        :is="f.icono"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{
                            f.nombre
                        }}</span>
                        <span
                            class="flex items-center gap-1 text-xs"
                            :class="
                                f.activo
                                    ? 'text-success'
                                    : 'text-muted-foreground'
                            "
                        >
                            <Check
                                v-if="f.activo"
                                class="size-3"
                                aria-hidden="true"
                            />
                            {{ f.texto }}
                        </span>
                    </span>
                    <span
                        v-if="!f.activo"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                        >Conocer</span
                    >
                </Link>
            </li>
        </ul>
    </section>
</template>
