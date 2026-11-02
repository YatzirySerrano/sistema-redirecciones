<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Ellipsis } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Acciones de un registro en tablas y tarjetas, siempre en una sola línea.
 *
 * - Las acciones `principal` (normalmente Ver y Editar) quedan visibles.
 * - El resto se agrupa en «Más acciones»; las peligrosas (`destructiva`)
 *   van al final, separadas y en rojo. La confirmación la muestra quien
 *   escucha el evento `accion`.
 *
 * Variante `tabla`: botones de solo icono con descripción emergente.
 * Variante `tarjeta`: icono y texto.
 */
export type AccionRegistro = {
    clave: string;
    etiqueta: string;
    icono: Component;
    href?: NonNullable<InertiaLinkProps['href']>;
    /** Enlace normal (descargas): no usa navegación de Inertia. */
    externo?: boolean;
    principal?: boolean;
    destructiva?: boolean;
    visible?: boolean;
};

const props = withDefaults(
    defineProps<{
        acciones: AccionRegistro[];
        variante?: 'tabla' | 'tarjeta';
        /** Nombre del registro, para los nombres accesibles. */
        nombre?: string;
    }>(),
    { variante: 'tabla', nombre: '' },
);

const emit = defineEmits<{ accion: [clave: string] }>();

const visibles = computed(() =>
    props.acciones.filter((a) => a.visible !== false),
);
const principales = computed(() => visibles.value.filter((a) => a.principal));
const secundarias = computed(() =>
    visibles.value.filter((a) => !a.principal && !a.destructiva),
);
const peligrosas = computed(() =>
    visibles.value.filter((a) => !a.principal && a.destructiva),
);

const nombreAccesible = (a: AccionRegistro) =>
    props.nombre ? `${a.etiqueta}: ${props.nombre}` : a.etiqueta;
</script>

<template>
    <div
        class="flex shrink-0 items-center gap-1 whitespace-nowrap"
        :class="variante === 'tabla' && 'justify-end'"
    >
        <template v-for="a in principales" :key="a.clave">
            <Tooltip v-if="variante === 'tabla'">
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :class="
                            cn(
                                'text-muted-foreground hover:text-foreground',
                                a.destructiva &&
                                    'text-destructive hover:bg-destructive/10 hover:text-destructive',
                            )
                        "
                        :as-child="!!a.href"
                        :aria-label="nombreAccesible(a)"
                        @click="!a.href && emit('accion', a.clave)"
                    >
                        <a v-if="a.href && a.externo" :href="String(a.href)">
                            <component :is="a.icono" aria-hidden="true" />
                        </a>
                        <Link v-else-if="a.href" :href="a.href">
                            <component :is="a.icono" aria-hidden="true" />
                        </Link>
                        <component :is="a.icono" v-else aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ a.etiqueta }}</TooltipContent>
            </Tooltip>
            <Button
                v-else
                variant="outline"
                size="sm"
                class="h-8 px-2.5 text-xs"
                :as-child="!!a.href"
                :aria-label="nombreAccesible(a)"
                @click="!a.href && emit('accion', a.clave)"
            >
                <a v-if="a.href && a.externo" :href="String(a.href)">
                    <component :is="a.icono" aria-hidden="true" />
                    {{ a.etiqueta }}
                </a>
                <Link v-else-if="a.href" :href="a.href">
                    <component :is="a.icono" aria-hidden="true" />
                    {{ a.etiqueta }}
                </Link>
                <template v-else>
                    <component :is="a.icono" aria-hidden="true" />
                    {{ a.etiqueta }}
                </template>
            </Button>
        </template>

        <DropdownMenu v-if="secundarias.length + peligrosas.length > 0">
            <Tooltip>
                <TooltipTrigger as-child>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            :size="variante === 'tabla' ? 'icon-sm' : 'sm'"
                            class="text-muted-foreground hover:text-foreground"
                            :class="variante === 'tarjeta' && 'h-8 px-2'"
                            :aria-label="
                                nombre
                                    ? `Más acciones: ${nombre}`
                                    : 'Más acciones'
                            "
                        >
                            <Ellipsis aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Más acciones</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" class="min-w-44">
                <template v-for="a in secundarias" :key="a.clave">
                    <DropdownMenuItem v-if="a.href && a.externo" as-child>
                        <a :href="String(a.href)">
                            <component :is="a.icono" aria-hidden="true" />
                            {{ a.etiqueta }}
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-else-if="a.href" as-child>
                        <Link :href="a.href">
                            <component :is="a.icono" aria-hidden="true" />
                            {{ a.etiqueta }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-else @select="emit('accion', a.clave)">
                        <component :is="a.icono" aria-hidden="true" />
                        {{ a.etiqueta }}
                    </DropdownMenuItem>
                </template>
                <DropdownMenuSeparator
                    v-if="secundarias.length > 0 && peligrosas.length > 0"
                />
                <DropdownMenuItem
                    v-for="a in peligrosas"
                    :key="a.clave"
                    variant="destructive"
                    @select="emit('accion', a.clave)"
                >
                    <component :is="a.icono" aria-hidden="true" />
                    {{ a.etiqueta }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
