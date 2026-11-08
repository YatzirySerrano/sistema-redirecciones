<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronDown,
    CircleHelp,
    Info,
    Lightbulb,
    Search,
    SearchX,
    TriangleAlert,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import type { Ambito, Bloque, Tema } from '@/components/guia/contenido';
import {
    normalizar,
    temasGuia,
    textoDeTema,
} from '@/components/guia/contenido';
import TextoGuia from '@/components/guia/TextoGuia.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { usePermisos } from '@/composables/usePermisos';
import { guia } from '@/routes';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Guía de uso', href: guia() }] },
});

const props = defineProps<{ ambito: Ambito }>();

const { puedeAlguno } = usePermisos();

const temas = computed(() => temasGuia(props.ambito));
const indice = computed(() =>
    temas.value.map((t) => [t, textoDeTema(t)] as const),
);

/* --------------------------- Buscador --------------------------- */

const busqueda = ref('');
const campo = ref<HTMLInputElement | null>(null);

const visibles = computed<Tema[]>(() => {
    const palabras = normalizar(busqueda.value).split(/\s+/).filter(Boolean);

    if (palabras.length === 0) {
        return temas.value;
    }

    return indice.value
        .filter(([, texto]) => palabras.every((p) => texto.includes(p)))
        .map(([t]) => t);
});

// Índice agrupado (en el orden de los temas).
const grupos = computed(() => {
    const mapa = new Map<string, Tema[]>();

    for (const t of visibles.value) {
        mapa.set(t.grupo, [...(mapa.get(t.grupo) ?? []), t]);
    }

    return [...mapa.entries()].map(([titulo, lista]) => ({ titulo, lista }));
});

const limpiar = () => {
    busqueda.value = '';
    campo.value?.focus();
};

// «/» enfoca el buscador desde cualquier parte de la guía.
const alTeclear = (e: KeyboardEvent) => {
    const destino = e.target as HTMLElement | null;
    const escribiendo =
        destino?.closest('input, textarea, select, [contenteditable]') !== null;

    if (e.key === '/' && !escribiendo) {
        e.preventDefault();
        campo.value?.focus();
    }
};

/* --------------------------- Scrollspy --------------------------- */

const activo = ref<string | null>(null);
const resaltado = ref<string | null>(null);
const listaIndice = ref<HTMLElement | null>(null);
const barra = ref<{ y: number; alto: number } | null>(null);
let observador: IntersectionObserver | null = null;

const observar = () => {
    observador?.disconnect();
    observador = new IntersectionObserver(
        (entradas) => {
            const vistas = entradas
                .filter((e) => e.isIntersecting)
                .sort(
                    (a, b) =>
                        a.boundingClientRect.top - b.boundingClientRect.top,
                );

            if (vistas.length > 0) {
                activo.value = vistas[0].target.id;
            }
        },
        // Activo: el tema que ocupa la franja superior de la pantalla.
        { rootMargin: '-72px 0px -60% 0px', threshold: 0 },
    );

    for (const t of visibles.value) {
        const el = document.getElementById(t.id);

        if (el) {
            observador.observe(el);
        }
    }
};

const moverBarra = () => {
    const el = listaIndice.value?.querySelector<HTMLElement>(
        `[data-tema="${activo.value}"]`,
    );

    barra.value = el ? { y: el.offsetTop, alto: el.offsetHeight } : null;
};

watch(activo, () => void nextTick(moverBarra));
watch(visibles, async () => {
    await nextTick();

    if (!visibles.value.some((t) => t.id === activo.value)) {
        activo.value = visibles.value[0]?.id ?? null;
    }

    observar();
    moverBarra();
});

const irA = (id: string) => {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    const reducido = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    el.scrollIntoView({
        block: 'start',
        behavior: reducido ? 'auto' : 'smooth',
    });
    history.replaceState(history.state, '', `#${id}`);
    activo.value = id;
    resaltar(id);
};

let temporizador: ReturnType<typeof setTimeout> | undefined;
const resaltar = (id: string) => {
    clearTimeout(temporizador);
    resaltado.value = id;
    temporizador = setTimeout(() => (resaltado.value = null), 1600);
};

onMounted(async () => {
    window.addEventListener('keydown', alTeclear);
    activo.value = temas.value[0]?.id ?? null;
    await nextTick();
    observar();
    moverBarra();

    // Ayuda contextual: /guia#tema abre directamente ese tema.
    const hash = decodeURIComponent(window.location.hash.slice(1));

    if (hash && document.getElementById(hash)) {
        requestAnimationFrame(() => irA(hash));
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', alTeclear);
    observador?.disconnect();
    clearTimeout(temporizador);
});

/* --------------------------- Presentación --------------------------- */

const notas = {
    consejo: {
        icono: Lightbulb,
        clase: 'border-primary/20 bg-accent/60 text-accent-foreground',
        iconoClase: 'text-primary',
    },
    aviso: {
        icono: TriangleAlert,
        clase: 'border-warning/30 bg-warning-soft text-warning',
        iconoClase: 'text-warning',
    },
    importante: {
        icono: Info,
        clase: 'border-border bg-muted/60 text-foreground',
        iconoClase: 'text-foreground',
    },
} as const;

const notaDe = (b: Bloque) =>
    b.tipo === 'nota' ? notas[b.tono] : notas.consejo;

const enlaceVisible = (t: Tema) =>
    !!t.enlace && (!t.enlace.permisos || puedeAlguno(...t.enlace.permisos));

const totalTemas = computed(() => temas.value.length);
</script>

<template>
    <Head title="Guía de uso" />

    <!-- Encabezado con el buscador -->
    <section
        class="relative overflow-hidden rounded-2xl border bg-card px-5 py-7 shadow-xs sm:px-8 sm:py-9"
        aria-labelledby="titulo-guia"
    >
        <div
            class="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--primary)_16%,transparent),transparent)]"
            aria-hidden="true"
        />
        <div class="relative max-w-2xl">
            <span
                class="mb-4 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
                aria-hidden="true"
            >
                <CircleHelp class="size-5" />
            </span>
            <h1
                id="titulo-guia"
                class="text-2xl font-semibold tracking-tight text-balance sm:text-3xl"
            >
                ¿En qué te ayudamos?
            </h1>
            <p
                class="mt-2 text-sm text-pretty text-muted-foreground sm:text-base"
            >
                {{
                    ambito === 'admin'
                        ? 'Guía para el personal de DASTI: negocios, productos, servicios, pagos y administración de la plataforma.'
                        : 'Guía de tu panel: tus productos QR y NFC, estadísticas, servicios y cuenta.'
                }}
            </p>

            <div class="relative mt-6">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    ref="campo"
                    v-model="busqueda"
                    type="search"
                    class="h-12 w-full rounded-xl border border-input bg-background pr-20 pl-10 text-base shadow-xs transition-[border-color,box-shadow] duration-150 outline-none placeholder:text-muted-foreground hover:border-ring/50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 [&::-webkit-search-cancel-button]:hidden"
                    placeholder="Busca un tema: destino, QR, contraseña, recompensa…"
                    aria-label="Buscar en la guía"
                    aria-controls="temas-guia"
                    @keydown.esc="limpiar"
                />
                <button
                    v-if="busqueda"
                    type="button"
                    class="absolute top-1/2 right-2.5 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Borrar búsqueda"
                    @click="limpiar"
                >
                    <X class="size-4" />
                </button>
                <kbd
                    v-else
                    class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded-md border bg-muted px-1.5 py-0.5 font-sans text-xs text-muted-foreground sm:block"
                    aria-hidden="true"
                    >/</kbd
                >
            </div>
            <p class="mt-2 text-xs text-muted-foreground" aria-live="polite">
                <template v-if="busqueda">
                    {{
                        visibles.length === 1
                            ? '1 tema coincide'
                            : `${visibles.length} temas coinciden`
                    }}
                    con «{{ busqueda }}»
                </template>
                <template v-else>{{ totalTemas }} temas</template>
            </p>
        </div>
    </section>

    <!-- Índice compacto (teléfono y tableta) -->
    <nav
        v-if="visibles.length > 0"
        class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:hidden"
        aria-label="Temas de la guía"
    >
        <button
            v-for="t in visibles"
            :key="t.id"
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-xs font-medium transition-colors duration-150"
            :class="
                activo === t.id
                    ? 'border-primary/40 bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
            @click="irA(t.id)"
        >
            <component :is="t.icono" class="size-3.5" aria-hidden="true" />
            {{ t.titulo }}
        </button>
    </nav>

    <div
        class="grid gap-8 lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] xl:gap-10"
    >
        <!-- Índice lateral pegajoso -->
        <aside class="hidden lg:block">
            <nav
                class="sticky top-20 max-h-[calc(100svh-6rem)] overflow-y-auto pr-1 pb-4"
                aria-label="Índice de la guía"
            >
                <div ref="listaIndice" class="relative border-l">
                    <span
                        v-if="barra"
                        class="absolute -left-px w-0.5 rounded-full bg-primary transition-[transform,height] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :style="{
                            transform: `translateY(${barra.y}px)`,
                            height: `${barra.alto}px`,
                        }"
                        aria-hidden="true"
                    />
                    <div
                        v-for="g in grupos"
                        :key="g.titulo"
                        class="pb-4 last:pb-0"
                    >
                        <p
                            class="px-4 pb-1.5 text-xs font-semibold text-foreground/70"
                        >
                            {{ g.titulo }}
                        </p>
                        <a
                            v-for="t in g.lista"
                            :key="t.id"
                            :href="`#${t.id}`"
                            :data-tema="t.id"
                            class="block py-1.5 pr-2 pl-4 text-sm transition-colors duration-150"
                            :class="
                                activo === t.id
                                    ? 'font-medium text-primary'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-current="
                                activo === t.id ? 'location' : undefined
                            "
                            @click.prevent="irA(t.id)"
                            >{{ t.titulo }}</a
                        >
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Temas -->
        <div id="temas-guia" class="min-w-0 space-y-6">
            <div
                v-if="visibles.length === 0"
                class="rounded-xl border border-dashed bg-card"
            >
                <EstadoVacio
                    :icono="SearchX"
                    titulo="Ningún tema coincide con tu búsqueda"
                    descripcion="Prueba con otra palabra, por ejemplo «destino», «QR», «visitas» o «contraseña»."
                >
                    <Button variant="outline" size="sm" @click="limpiar"
                        >Ver todos los temas</Button
                    >
                </EstadoVacio>
            </div>

            <TransitionGroup
                tag="div"
                class="space-y-6"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-1"
            >
                <section
                    v-for="t in visibles"
                    :id="t.id"
                    :key="t.id"
                    class="scroll-mt-20 overflow-hidden rounded-2xl border bg-card shadow-xs transition-[box-shadow,border-color] duration-500"
                    :class="
                        resaltado === t.id
                            ? 'border-primary/50 shadow-[0_0_0_4px_color-mix(in_oklab,var(--primary)_14%,transparent)]'
                            : ''
                    "
                    :aria-labelledby="`titulo-${t.id}`"
                >
                    <header
                        class="flex flex-col gap-4 border-b bg-muted/30 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6"
                    >
                        <div class="flex min-w-0 gap-3.5">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
                                aria-hidden="true"
                            >
                                <component :is="t.icono" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">
                                    {{ t.grupo }}
                                </p>
                                <h2
                                    :id="`titulo-${t.id}`"
                                    class="text-lg font-semibold tracking-tight text-balance"
                                >
                                    {{ t.titulo }}
                                </h2>
                                <p
                                    class="mt-0.5 max-w-prose text-sm text-pretty text-muted-foreground"
                                >
                                    {{ t.resumen }}
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="enlaceVisible(t)"
                            variant="outline"
                            size="sm"
                            class="group shrink-0 self-start"
                            as-child
                        >
                            <Link :href="t.enlace!.href">
                                {{ t.enlace!.texto }}
                                <ArrowRight
                                    class="transition-transform duration-200 group-hover:translate-x-0.5"
                                />
                            </Link>
                        </Button>
                    </header>

                    <div
                        class="space-y-6 px-5 py-6 text-sm leading-relaxed text-muted-foreground sm:px-6"
                    >
                        <template v-for="(b, i) in t.bloques" :key="i">
                            <!-- Texto -->
                            <p
                                v-if="b.tipo === 'texto'"
                                class="max-w-prose text-pretty"
                            >
                                <TextoGuia :texto="b.texto" />
                            </p>

                            <!-- Pasos numerados -->
                            <div v-else-if="b.tipo === 'pasos'">
                                <h3
                                    v-if="b.titulo"
                                    class="mb-3 text-sm font-semibold text-foreground"
                                >
                                    {{ b.titulo }}
                                </h3>
                                <ol class="relative max-w-prose space-y-3">
                                    <li
                                        v-for="(p, n) in b.pasos"
                                        :key="n"
                                        class="relative flex gap-3"
                                    >
                                        <span
                                            v-if="n < b.pasos.length - 1"
                                            class="absolute top-7 bottom-[-0.75rem] left-3 w-px bg-border"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="relative flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums"
                                            aria-hidden="true"
                                            >{{ n + 1 }}</span
                                        >
                                        <span class="pt-0.5 text-pretty">
                                            <TextoGuia :texto="p" />
                                        </span>
                                    </li>
                                </ol>
                            </div>

                            <!-- Lista -->
                            <div v-else-if="b.tipo === 'lista'">
                                <h3
                                    v-if="b.titulo"
                                    class="mb-2 text-sm font-semibold text-foreground"
                                >
                                    {{ b.titulo }}
                                </h3>
                                <ul class="max-w-prose space-y-1.5">
                                    <li
                                        v-for="(e, n) in b.elementos"
                                        :key="n"
                                        class="flex gap-2.5 text-pretty"
                                    >
                                        <span
                                            class="mt-[0.6em] size-1.5 shrink-0 rounded-full bg-primary/60"
                                            aria-hidden="true"
                                        />
                                        <span><TextoGuia :texto="e" /></span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Consejos y avisos -->
                            <aside
                                v-else-if="b.tipo === 'nota'"
                                class="flex max-w-prose gap-3 rounded-xl border px-4 py-3"
                                :class="notaDe(b).clase"
                            >
                                <component
                                    :is="notaDe(b).icono"
                                    class="mt-0.5 size-4 shrink-0"
                                    :class="notaDe(b).iconoClase"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="font-semibold">{{ b.titulo }}</p>
                                    <p class="mt-0.5 opacity-90">
                                        <TextoGuia :texto="b.texto" />
                                    </p>
                                </div>
                            </aside>

                            <!-- Preguntas frecuentes -->
                            <div v-else-if="b.tipo === 'preguntas'">
                                <h3
                                    class="mb-2 text-sm font-semibold text-foreground"
                                >
                                    Preguntas frecuentes
                                </h3>
                                <div
                                    class="max-w-prose divide-y overflow-hidden rounded-xl border"
                                >
                                    <Collapsible
                                        v-for="(q, n) in b.preguntas"
                                        :key="n"
                                        v-slot="{ open }"
                                    >
                                        <CollapsibleTrigger
                                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-medium text-foreground transition-colors duration-150 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                        >
                                            {{ q.pregunta }}
                                            <ChevronDown
                                                class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                                :class="
                                                    open ? 'rotate-180' : ''
                                                "
                                                aria-hidden="true"
                                            />
                                        </CollapsibleTrigger>
                                        <CollapsibleContent
                                            class="contenido-pregunta overflow-hidden"
                                        >
                                            <p class="px-4 pb-4 text-pretty">
                                                <TextoGuia
                                                    :texto="q.respuesta"
                                                />
                                            </p>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </TransitionGroup>

            <p
                v-if="visibles.length > 0"
                class="px-1 text-sm text-muted-foreground"
            >
                {{
                    ambito === 'admin'
                        ? 'Los detalles técnicos (despliegue, Openpay, variables de entorno) están en la documentación del proyecto.'
                        : 'Si algo no funciona como se describe aquí, contacta a DASTI.'
                }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.contenido-pregunta[data-state='open'] {
    animation: abrir-pregunta 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.contenido-pregunta[data-state='closed'] {
    animation: cerrar-pregunta 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes abrir-pregunta {
    from {
        height: 0;
        opacity: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
}

@keyframes cerrar-pregunta {
    from {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
    to {
        height: 0;
        opacity: 0;
    }
}
</style>
