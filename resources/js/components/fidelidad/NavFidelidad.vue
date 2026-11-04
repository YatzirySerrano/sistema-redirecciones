<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ChevronDown, Lock } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermisos } from '@/composables/usePermisos';
import { gruposVisibles } from './secciones';
import type { SeccionFidelidad } from './secciones';
import type { EstadoServicio } from './tipos';

/**
 * Navegación interna de Fidelidad, agrupada por finalidad (Inicio,
 * Operación, Programas, Personalización, Administración).
 *
 * Es navegación, no acciones: enlaces de texto con una barra bajo la
 * sección activa (sin fondo de botón). En pantallas angostas se convierte
 * en un selector compacto con la lista agrupada. Usa la misma definición
 * que el menú lateral (secciones.ts).
 */
const props = defineProps<{ servicio?: EstadoServicio }>();

const { puede } = usePermisos();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const habilitado = computed(
    () =>
        !props.servicio || props.servicio.activo || props.servicio.solo_lectura,
);
const grupos = computed(() => gruposVisibles(puede, habilitado.value));

const activa = (s: SeccionFidelidad) =>
    s.exacta ? isCurrentUrl(s.href) : isCurrentOrParentUrl(s.href);

const actual = computed(() => {
    for (const g of grupos.value) {
        const s = g.secciones.find(activa);

        if (s) {
            return { grupo: g, seccion: s };
        }
    }

    return null;
});
</script>

<template>
    <div class="@container space-y-3">
        <nav aria-label="Secciones de Fidelidad">
            <!-- Pantallas amplias: grupos con su título y enlaces de texto -->
            <div
                class="hidden flex-wrap items-end gap-x-7 gap-y-3 border-b pb-px @2xl:flex"
            >
                <div
                    v-for="g in grupos"
                    :key="g.clave"
                    class="min-w-0"
                    :data-activo="actual?.grupo.clave === g.clave || undefined"
                >
                    <p
                        class="px-1 text-[0.68rem] font-semibold tracking-wider text-muted-foreground/80 uppercase data-[activo]:text-primary"
                        :data-activo="
                            actual?.grupo.clave === g.clave || undefined
                        "
                    >
                        {{ g.titulo }}
                    </p>
                    <ul class="flex flex-wrap gap-x-1">
                        <li v-for="s in g.secciones" :key="s.clave">
                            <Link
                                :href="s.href"
                                :aria-current="activa(s) ? 'page' : undefined"
                                :title="s.descripcion"
                                class="relative -mb-px inline-flex h-10 items-center gap-1.5 px-1.5 text-sm text-muted-foreground transition-colors after:absolute after:inset-x-1 after:bottom-0 after:h-0.5 after:rounded-full after:bg-transparent hover:text-foreground hover:after:bg-border focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none aria-[current=page]:font-semibold aria-[current=page]:text-foreground aria-[current=page]:after:bg-primary"
                            >
                                <component
                                    :is="s.icono"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ s.titulo }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Pantallas angostas: selector compacto con lista agrupada -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex h-12 w-full items-center gap-3 rounded-xl border bg-card px-3 text-left shadow-xs transition hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none @2xl:hidden"
                        aria-label="Cambiar de sección de Fidelidad"
                    >
                        <component
                            :is="actual.seccion.icono"
                            v-if="actual"
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 leading-tight">
                            <span
                                class="block text-[0.68rem] font-semibold tracking-wider text-muted-foreground uppercase"
                                >{{
                                    actual
                                        ? `Fidelidad · ${actual.grupo.titulo}`
                                        : 'Fidelidad'
                                }}</span
                            >
                            <span class="block truncate text-sm font-semibold">
                                {{
                                    actual?.seccion.titulo ??
                                    'Elige una sección'
                                }}
                            </span>
                        </span>
                        <span
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            Secciones
                            <ChevronDown class="size-4" aria-hidden="true" />
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="w-(--reka-dropdown-menu-trigger-width) max-w-[calc(100vw-2rem)] min-w-64"
                >
                    <template v-for="(g, i) in grupos" :key="g.clave">
                        <DropdownMenuSeparator v-if="i > 0" />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel
                                class="text-[0.68rem] font-semibold tracking-wider text-muted-foreground uppercase"
                                >{{ g.titulo }}</DropdownMenuLabel
                            >
                            <DropdownMenuItem
                                v-for="s in g.secciones"
                                :key="s.clave"
                                as-child
                            >
                                <Link
                                    :href="s.href"
                                    :aria-current="
                                        activa(s) ? 'page' : undefined
                                    "
                                    class="min-h-11 cursor-pointer aria-[current=page]:bg-primary/8 aria-[current=page]:font-semibold aria-[current=page]:text-primary"
                                >
                                    <component
                                        :is="s.icono"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block">{{
                                            s.titulo
                                        }}</span>
                                        <span
                                            class="block text-xs font-normal text-muted-foreground"
                                            >{{ s.descripcion }}</span
                                        >
                                    </span>
                                    <Check
                                        v-if="activa(s)"
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </nav>

        <p
            v-if="props.servicio && props.servicio.solo_lectura"
            class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-sm text-warning"
            role="status"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                Fidelidad está en <strong>solo lectura</strong>
                {{
                    props.servicio.estado === 'vencida'
                        ? 'porque la contratación venció.'
                        : 'mientras DASTI revisa un pago.'
                }}
                Puedes consultar tu información, pero no registrar visitas ni
                canjes.
            </span>
        </p>
    </div>
</template>
