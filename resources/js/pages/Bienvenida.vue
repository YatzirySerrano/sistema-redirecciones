<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    ChevronDown,
    Gift,
    Link2,
    LockKeyhole,
    Mail,
    MessageCircle,
    Moon,
    Nfc,
    Palette,
    QrCode,
    RefreshCw,
    Smartphone,
    Sun,
    Zap,
} from '@lucide/vue';
import type { Directive } from 'vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { login } from '@/routes';

/**
 * Portada pública de DASTI (/). Solo para invitados: con sesión, la ruta
 * lleva al panel (BienvenidaController). Los metadatos SEO/OG y los datos
 * estructurados (Organization, WebSite, Service, FAQPage) se generan en el
 * servidor: resources/views/partials/seo.blade.php.
 *
 * Sin precios ni testimonios. Animaciones sutiles al hacer scroll que se
 * desactivan con prefers-reduced-motion.
 */
const props = defineProps<{
    preguntas: { pregunta: string; respuesta: string }[];
    contacto: { correo: string | null; whatsapp: string | null };
    prefijoEnlace: string;
}>();

const { resolvedAppearance, updateAppearance } = useAppearance();
const oscuro = computed(() => resolvedAppearance.value === 'dark');
const alternarTema = () => updateAppearance(oscuro.value ? 'light' : 'dark');

const enlaceContacto = computed(
    () =>
        props.contacto.whatsapp ??
        (props.contacto.correo ? `mailto:${props.contacto.correo}` : null),
);
const enlaceEjemplo = computed(
    () => props.prefijoEnlace.replace(/^https?:\/\//, '') + 'k7m2x9qa4c',
);
const anio = new Date().getFullYear();

const pasos = [
    {
        icono: Link2,
        titulo: 'Recibe tu enlace permanente',
        texto: 'Cada código QR o tarjeta NFC lleva un enlace corto único de DASTI que nunca cambia.',
    },
    {
        icono: RefreshCw,
        titulo: 'Elige el destino',
        texto: 'Desde tu panel decides a dónde lleva: menú, catálogo, WhatsApp, redes sociales, reseñas o tu página de enlaces.',
    },
    {
        icono: BarChart3,
        titulo: 'Cámbialo y mide',
        texto: 'Actualiza el destino cuando quieras, sin reimprimir, y consulta las estadísticas de escaneos.',
    },
];

const servicios = [
    {
        icono: QrCode,
        titulo: 'Redirecciones QR y NFC',
        texto: 'Códigos QR dinámicos y tarjetas NFC con un destino que puedes cambiar en segundos. QR personalizados con los colores de tu marca.',
        puntos: [
            'Cambio de destino inmediato',
            'QR personalizados',
            'Estadísticas de escaneos',
        ],
    },
    {
        icono: Gift,
        titulo: 'Programa de fidelidad digital',
        texto: 'Tarjeta de recompensas digital para tus clientes, sin descargar aplicaciones. Registra visitas y canjes desde tu panel.',
        puntos: [
            'Tarjeta con tu diseño',
            'Visitas y recompensas',
            'Consulta desde el teléfono',
        ],
    },
    {
        icono: Smartphone,
        titulo: 'Página de enlaces',
        texto: 'Tu «link in bio»: redes sociales, contacto, ubicación y enlaces importantes en una sola página con la identidad de tu negocio.',
        puntos: [
            'Diseño personalizable',
            'Ideal para QR y NFC',
            'Visitas y clics',
        ],
    },
];

const beneficios = [
    {
        icono: Zap,
        titulo: 'Sin reimprimir',
        texto: 'Tus materiales impresos y tarjetas siguen funcionando aunque cambies de promoción, menú o red social.',
    },
    {
        icono: BarChart3,
        titulo: 'Decisiones con datos',
        texto: 'Accesos por día, producto y dispositivo para saber qué funciona.',
    },
    {
        icono: Nfc,
        titulo: 'QR y NFC juntos',
        texto: 'Un mismo destino para el código QR impreso y la tarjeta NFC que se acerca al teléfono.',
    },
    {
        icono: Palette,
        titulo: 'Con tu identidad',
        texto: 'Colores, logotipo y estilo de tu negocio en QR, tarjetas de fidelidad y página de enlaces.',
    },
    {
        icono: LockKeyhole,
        titulo: 'Acceso protegido',
        texto: 'Verificación en dos pasos y registro de cada cambio de destino.',
    },
    {
        icono: RefreshCw,
        titulo: 'Siempre al día',
        texto: 'Redirección inmediata y sin caché: cada escaneo consulta el destino vigente.',
    },
];

/**
 * Revela el elemento con un desvanecido al entrar en pantalla. Sin
 * IntersectionObserver (o con movimiento reducido, ver CSS) se muestra
 * directamente.
 */
const vRevelar: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (binding.value) {
            el.style.setProperty('--retraso', `${binding.value}ms`);
        }

        if (typeof IntersectionObserver === 'undefined') {
            el.classList.add('revelado');

            return;
        }

        el.classList.add('revelar');
        const observador = new IntersectionObserver(
            (entradas) => {
                for (const entrada of entradas) {
                    if (entrada.isIntersecting) {
                        // Al terminar, se retira la clase para que el
                        // elemento recupere sus propias transiciones (hover).
                        const limpiar = (evento: TransitionEvent) => {
                            if (evento.target !== el) {
                                return;
                            }

                            el.removeEventListener('transitionend', limpiar);
                            el.classList.remove('revelar', 'revelado');
                            el.style.removeProperty('--retraso');
                        };
                        el.addEventListener('transitionend', limpiar);
                        el.classList.add('revelado');
                        observador.disconnect();
                    }
                }
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
        );
        observador.observe(el);
    },
};
</script>

<template>
    <Head title="Redirecciones QR y NFC dinámicas" />

    <div
        class="relative min-h-dvh overflow-x-clip bg-background text-foreground"
    >
        <!-- Encabezado -->
        <header
            class="sticky top-0 z-40 border-b border-border/60 bg-background/75 backdrop-blur-lg supports-[backdrop-filter]:bg-background/60"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            >
                <a
                    href="#inicio"
                    class="flex items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <img
                        src="/iconos/icono-192.png"
                        alt=""
                        width="32"
                        height="32"
                        class="size-8"
                    />
                    <span class="text-base font-extrabold tracking-[0.2em]"
                        >DASTI</span
                    >
                </a>
                <nav
                    class="hidden items-center gap-7 text-sm text-muted-foreground md:flex"
                    aria-label="Secciones"
                >
                    <a href="#como-funciona" class="enlace-nav"
                        >Cómo funciona</a
                    >
                    <a href="#servicios" class="enlace-nav">Servicios</a>
                    <a href="#beneficios" class="enlace-nav">Beneficios</a>
                    <a href="#preguntas" class="enlace-nav"
                        >Preguntas frecuentes</a
                    >
                </nav>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="
                            oscuro ? 'Usar modo claro' : 'Usar modo oscuro'
                        "
                        @click="alternarTema"
                    >
                        <Sun v-if="oscuro" class="size-4" />
                        <Moon v-else class="size-4" />
                    </button>
                    <Link
                        :href="login()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-boton px-4 text-sm font-medium text-boton-foreground shadow-xs transition hover:bg-boton/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                    >
                        Iniciar sesión
                    </Link>
                </div>
            </div>
        </header>

        <main id="inicio">
            <!-- Hero -->
            <section class="relative isolate">
                <div class="fondo-hero" aria-hidden="true" />
                <div
                    class="mx-auto grid max-w-6xl items-center gap-14 px-4 pt-16 pb-20 sm:px-6 md:pt-24 lg:grid-cols-[1.1fr_0.9fr] lg:pb-28"
                >
                    <div class="text-center lg:text-left">
                        <p
                            v-revelar
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card/70 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur"
                        >
                            <span
                                class="size-1.5 rounded-full bg-sky-500 motion-safe:animate-pulse"
                            />
                            Redirecciones dinámicas para QR y NFC
                        </p>
                        <h1
                            v-revelar="60"
                            class="mt-6 text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.6rem]"
                        >
                            Códigos QR y tarjetas NFC que
                            <span class="texto-marca">nunca pierden</span> su
                            destino.
                        </h1>
                        <p
                            v-revelar="120"
                            class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground lg:mx-0"
                        >
                            Con DASTI, cada código QR dinámico y cada tarjeta
                            NFC lleva un enlace permanente. Cambia el destino
                            cuando quieras, mide los escaneos y suma un programa
                            de fidelidad digital y tu página de enlaces.
                        </p>
                        <div
                            v-revelar="180"
                            class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start"
                        >
                            <Link
                                :href="login()"
                                class="group inline-flex h-12 items-center gap-2 rounded-xl bg-boton px-6 text-base font-medium text-boton-foreground shadow-lg shadow-boton/25 transition hover:-translate-y-0.5 hover:bg-boton/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none motion-reduce:hover:translate-y-0"
                            >
                                Iniciar sesión
                                <ArrowRight
                                    class="size-4 transition-transform group-hover:translate-x-0.5"
                                />
                            </Link>
                            <a
                                v-if="enlaceContacto"
                                :href="enlaceContacto"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex h-12 items-center gap-2 rounded-xl border border-border bg-card/70 px-6 text-base font-medium backdrop-blur transition hover:-translate-y-0.5 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:hover:translate-y-0"
                            >
                                <MessageCircle
                                    v-if="contacto.whatsapp"
                                    class="size-4"
                                />
                                <Mail v-else class="size-4" />
                                Contactar
                            </a>
                            <a
                                v-else
                                href="#como-funciona"
                                class="inline-flex h-12 items-center gap-2 rounded-xl px-5 text-base font-medium text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Ver cómo funciona
                                <ChevronDown class="size-4" />
                            </a>
                        </div>
                    </div>

                    <!-- Ilustración: medio físico → enlace permanente → destino -->
                    <div
                        v-revelar="140"
                        class="relative mx-auto w-full max-w-md"
                        aria-hidden="true"
                    >
                        <div class="halo-logo" />
                        <img
                            src="/iconos/icono-512.png"
                            alt=""
                            width="512"
                            height="512"
                            class="flotar relative mx-auto w-56 drop-shadow-[0_24px_48px_rgba(0,90,255,0.35)] sm:w-64"
                        />
                        <div
                            class="relative -mt-6 rounded-2xl border border-border bg-card/85 p-4 shadow-xl shadow-black/5 backdrop-blur-md"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex -space-x-2">
                                    <span
                                        class="flex size-10 items-center justify-center rounded-xl bg-sky-500 text-white ring-4 ring-card"
                                    >
                                        <Nfc class="size-5" />
                                    </span>
                                    <span
                                        class="flex size-10 items-center justify-center rounded-xl bg-foreground text-background ring-4 ring-card"
                                    >
                                        <QrCode class="size-5" />
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate font-mono text-xs text-foreground"
                                    >
                                        {{ enlaceEjemplo }}
                                    </p>
                                    <div
                                        class="mt-2 h-1 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div class="barra-progreso" />
                                    </div>
                                </div>
                            </div>
                            <ul class="mt-4 grid gap-2 text-sm">
                                <li
                                    class="flex items-center justify-between rounded-lg bg-muted/60 px-3 py-2 text-muted-foreground line-through decoration-muted-foreground/50"
                                >
                                    menú de temporada anterior
                                    <span class="text-xs">antes</span>
                                </li>
                                <li
                                    class="flex items-center justify-between rounded-lg bg-sky-500/10 px-3 py-2 font-medium text-foreground ring-1 ring-sky-500/30"
                                >
                                    menú nuevo · promoción del mes
                                    <span
                                        class="text-xs text-sky-600 dark:text-sky-400"
                                        >ahora</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Cómo funciona -->
            <section
                id="como-funciona"
                class="scroll-mt-20 border-t border-border/60 bg-muted/30 py-20 sm:py-24"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div v-revelar class="mx-auto max-w-2xl text-center">
                        <p class="etiqueta">Cómo funciona</p>
                        <h2 class="titulo-seccion">
                            Redirecciones dinámicas en tres pasos
                        </h2>
                        <p class="mt-4 text-muted-foreground">
                            El enlace impreso en el QR o grabado en la tarjeta
                            NFC es permanente; lo que cambia es a dónde lleva.
                        </p>
                    </div>
                    <ol class="mt-14 grid gap-6 md:grid-cols-3">
                        <li
                            v-for="(paso, i) in pasos"
                            :key="paso.titulo"
                            v-revelar="i * 90"
                            class="tarjeta relative p-6"
                        >
                            <span
                                class="absolute top-5 right-5 font-mono text-4xl font-bold text-muted-foreground/15"
                                >0{{ i + 1 }}</span
                            >
                            <span class="icono-caja">
                                <component :is="paso.icono" class="size-5" />
                            </span>
                            <h3 class="mt-5 text-lg font-semibold">
                                {{ paso.titulo }}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ paso.texto }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Servicios -->
            <section id="servicios" class="scroll-mt-20 py-20 sm:py-24">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div v-revelar class="mx-auto max-w-2xl text-center">
                        <p class="etiqueta">Servicios</p>
                        <h2 class="titulo-seccion">
                            Todo lo que tu negocio conecta con un QR o una
                            tarjeta NFC
                        </h2>
                    </div>
                    <div class="mt-14 grid gap-6 lg:grid-cols-3">
                        <article
                            v-for="(servicio, i) in servicios"
                            :key="servicio.titulo"
                            v-revelar="i * 90"
                            class="tarjeta group flex flex-col p-7"
                        >
                            <span class="icono-caja icono-caja-grande">
                                <component
                                    :is="servicio.icono"
                                    class="size-6 transition-transform duration-300 group-hover:scale-110 motion-reduce:group-hover:scale-100"
                                />
                            </span>
                            <h3 class="mt-6 text-xl font-semibold">
                                {{ servicio.titulo }}
                            </h3>
                            <p
                                class="mt-3 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ servicio.texto }}
                            </p>
                            <ul class="mt-6 grid gap-2 text-sm">
                                <li
                                    v-for="punto in servicio.puntos"
                                    :key="punto"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="size-1.5 shrink-0 rounded-full bg-sky-500"
                                    />
                                    {{ punto }}
                                </li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Beneficios -->
            <section
                id="beneficios"
                class="scroll-mt-20 border-y border-border/60 bg-muted/30 py-20 sm:py-24"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div v-revelar class="mx-auto max-w-2xl text-center">
                        <p class="etiqueta">Beneficios</p>
                        <h2 class="titulo-seccion">
                            Un enlace que trabaja para ti
                        </h2>
                    </div>
                    <ul
                        class="mt-14 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <li
                            v-for="(b, i) in beneficios"
                            :key="b.titulo"
                            v-revelar="(i % 3) * 80"
                            class="flex gap-4"
                        >
                            <span class="icono-caja shrink-0">
                                <component :is="b.icono" class="size-5" />
                            </span>
                            <div>
                                <h3 class="font-semibold">{{ b.titulo }}</h3>
                                <p
                                    class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ b.texto }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Preguntas frecuentes (datos estructurados FAQPage en el servidor) -->
            <section id="preguntas" class="scroll-mt-20 py-20 sm:py-24">
                <div class="mx-auto max-w-3xl px-4 sm:px-6">
                    <div v-revelar class="text-center">
                        <p class="etiqueta">Preguntas frecuentes</p>
                        <h2 class="titulo-seccion">Resolvemos tus dudas</h2>
                    </div>
                    <div class="mt-12 grid gap-3">
                        <details
                            v-for="(p, i) in preguntas"
                            :key="p.pregunta"
                            v-revelar="i * 50"
                            class="pregunta tarjeta group px-5 py-1"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-medium focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                            >
                                {{ p.pregunta }}
                                <ChevronDown
                                    class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180"
                                />
                            </summary>
                            <p
                                class="pb-5 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ p.respuesta }}
                            </p>
                        </details>
                    </div>
                </div>
            </section>

            <!-- Llamado final -->
            <section class="px-4 pb-20 sm:px-6">
                <div
                    v-revelar
                    class="cta-final relative mx-auto max-w-6xl overflow-hidden rounded-3xl px-6 py-14 text-center text-white sm:px-12"
                >
                    <img
                        src="/iconos/icono-512.png"
                        alt=""
                        aria-hidden="true"
                        width="512"
                        height="512"
                        class="pointer-events-none absolute -right-10 -bottom-16 w-64 opacity-20 sm:w-80"
                    />
                    <h2
                        class="relative text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                    >
                        ¿Ya eres cliente de DASTI?
                    </h2>
                    <p class="relative mx-auto mt-4 max-w-xl text-white/80">
                        Entra a tu panel para cambiar destinos, revisar tus
                        estadísticas y administrar tu programa de fidelidad.
                    </p>
                    <div
                        class="relative mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                    >
                        <Link
                            :href="login()"
                            class="inline-flex h-12 items-center gap-2 rounded-xl bg-white px-6 font-medium text-slate-900 shadow-lg transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-blue-700 focus-visible:outline-none motion-reduce:hover:translate-y-0"
                        >
                            Iniciar sesión
                            <ArrowRight class="size-4" />
                        </Link>
                        <a
                            v-if="enlaceContacto"
                            :href="enlaceContacto"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-12 items-center gap-2 rounded-xl border border-white/30 px-6 font-medium text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                        >
                            Contactar
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border/60">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6"
            >
                <div class="flex items-center gap-2.5">
                    <img
                        src="/iconos/favicon-32.png"
                        alt=""
                        width="20"
                        height="20"
                        class="size-5"
                    />
                    <span
                        >© {{ anio }} DASTI · Redirecciones QR y NFC
                        dinámicas</span
                    >
                </div>
                <nav class="flex items-center gap-5" aria-label="Pie de página">
                    <a href="#servicios" class="enlace-nav">Servicios</a>
                    <a href="#preguntas" class="enlace-nav">Preguntas</a>
                    <Link :href="login()" class="enlace-nav"
                        >Iniciar sesión</Link
                    >
                </nav>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.texto-marca {
    background: linear-gradient(100deg, #0ea5e9, #2563eb 45%, #4f46e5);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.fondo-hero {
    position: absolute;
    inset: 0;
    z-index: -1;
    background:
        radial-gradient(
            60rem 30rem at 85% -10%,
            color-mix(in oklab, #0ea5e9 18%, transparent),
            transparent 70%
        ),
        radial-gradient(
            40rem 28rem at 0% 10%,
            color-mix(in oklab, var(--primary) 14%, transparent),
            transparent 70%
        );
    mask-image: linear-gradient(to bottom, black 70%, transparent);
}

.fondo-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, var(--border) 1px, transparent 1px),
        linear-gradient(to bottom, var(--border) 1px, transparent 1px);
    background-size: 48px 48px;
    opacity: 0.45;
    mask-image: radial-gradient(ellipse at 50% 0%, black, transparent 70%);
}

.halo-logo {
    position: absolute;
    top: 0;
    left: 50%;
    width: 18rem;
    height: 18rem;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        color-mix(in oklab, #1d8bff 35%, transparent),
        transparent 70%
    );
    filter: blur(24px);
}

.etiqueta {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #0284c7;
}

.dark .etiqueta {
    color: #38bdf8;
}

.titulo-seccion {
    margin-top: 0.75rem;
    font-size: clamp(1.75rem, 1.2rem + 2vw, 2.5rem);
    line-height: 1.15;
    font-weight: 600;
    letter-spacing: -0.02em;
    text-wrap: balance;
}

.tarjeta {
    border-radius: 1.25rem;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--card-foreground);
    transition:
        transform var(--mov-medio, 220ms) var(--curva-salida, ease-out),
        box-shadow var(--mov-medio, 220ms) var(--curva-salida, ease-out),
        border-color var(--mov-medio, 220ms) ease;
}

.tarjeta:not(details):hover {
    transform: translateY(-4px);
    border-color: color-mix(in oklab, #0ea5e9 40%, var(--border));
    box-shadow: 0 18px 40px -24px color-mix(in oklab, #1d4ed8 45%, transparent);
}

.pregunta[open] {
    border-color: color-mix(in oklab, #0ea5e9 35%, var(--border));
}

.pregunta summary:focus-visible {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 2px var(--ring);
}

.icono-caja {
    display: inline-flex;
    width: 2.75rem;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    color: #0369a1;
    background: linear-gradient(
        135deg,
        color-mix(in oklab, #0ea5e9 16%, transparent),
        color-mix(in oklab, #4f46e5 14%, transparent)
    );
    box-shadow: inset 0 0 0 1px color-mix(in oklab, #0ea5e9 25%, transparent);
}

.dark .icono-caja {
    color: #7dd3fc;
}

.icono-caja-grande {
    width: 3.25rem;
    height: 3.25rem;
}

.enlace-nav {
    border-radius: 0.375rem;
    transition: color var(--mov-rapido, 150ms) ease;
}

.enlace-nav:hover {
    color: var(--foreground);
}

.enlace-nav:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px var(--ring);
}

.cta-final {
    background:
        radial-gradient(
            40rem 20rem at 100% 100%,
            rgba(14, 165, 233, 0.45),
            transparent 70%
        ),
        linear-gradient(135deg, #0b1650, #1d3fd1 55%, #3b2bb8);
}

.barra-progreso {
    height: 100%;
    width: 40%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #0ea5e9, #2563eb);
    animation: recorrido 2.6s var(--curva-suave, ease-in-out) infinite;
}

@keyframes recorrido {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(260%);
    }
}

.flotar {
    animation: flotar 6s ease-in-out infinite;
}

@keyframes flotar {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Entrada al hacer scroll (directiva v-revelar). */
.revelar {
    opacity: 0;
    transform: translateY(18px);
    transition:
        opacity 600ms var(--curva-salida, ease-out),
        transform 600ms var(--curva-salida, ease-out);
    transition-delay: var(--retraso, 0ms);
}

.revelar.revelado {
    opacity: 1;
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .revelar {
        opacity: 1;
        transform: none;
        transition: none;
    }

    .flotar,
    .barra-progreso {
        animation: none;
    }

    .tarjeta,
    .tarjeta:not(details):hover {
        transform: none;
        transition: none;
    }
}
</style>
