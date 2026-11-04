<script setup lang="ts">
import TextoEnriquecido from '../TextoEnriquecido.vue';
import AvatarPerfil from './AvatarPerfil.vue';
import ContactoPerfil from './ContactoPerfil.vue';

/**
 * Composiciones de encabezado añadidas al catálogo de diseño: banda de
 * color a sangre, tarjeta flotante, encabezado dividido y editorial. Los
 * encabezados originales (centrado, foto al lado y portada) siguen en el
 * renderizador sin cambios.
 */
const props = defineProps<{
    estilo: 'banda' | 'tarjeta' | 'dividido' | 'editorial';
    nombre: string;
    mostrarNombre: boolean;
    descripcion: string;
    foto: string | null;
    logo: string | null;
    portada: string | null;
    forma: string;
    monograma: boolean;
    centrado: boolean;
    contacto: { telefono: string; email: string; direccion: string };
    modo: 'publico' | 'vista_previa';
}>();

const conAvatar = () => !!props.foto || props.monograma;
</script>

<template>
    <!-- Banda de color (o portada) a sangre con la foto encima del borde -->
    <header v-if="estilo === 'banda'" class="mb-7" data-cabecera="banda">
        <div
            class="pe-banda relative -mx-5 -mt-8 h-32 overflow-hidden @md:-mx-6 @md:-mt-12 @md:h-40 @md:rounded-b-[calc(var(--pe-radio)+10px)]"
        >
            <img
                v-if="portada"
                :src="portada"
                alt=""
                class="size-full object-cover"
            />
        </div>
        <div
            class="relative flex flex-col"
            :class="[
                centrado ? 'items-center text-center' : 'items-start text-left',
                conAvatar() ? '-mt-14' : 'mt-5',
            ]"
        >
            <AvatarPerfil
                :foto="foto"
                :nombre="nombre"
                :monograma="monograma"
                class="size-28 text-[2.1em]"
                :class="forma"
            />
            <div :class="conAvatar() ? 'mt-3' : ''">
                <img
                    v-if="logo"
                    :src="logo"
                    alt=""
                    class="mb-2 h-8 w-auto object-contain"
                    :class="centrado ? 'mx-auto' : ''"
                />
                <h1
                    v-if="mostrarNombre && nombre"
                    class="pe-titulo text-[calc(1.5em*var(--pe-escala))] leading-tight text-balance"
                >
                    {{ nombre }}
                </h1>
                <p
                    v-if="descripcion"
                    class="mt-1.5 max-w-[34rem] leading-relaxed text-pretty text-(--pe-suave)"
                    :class="centrado ? 'mx-auto' : ''"
                >
                    <TextoEnriquecido :texto="descripcion" />
                </p>
            </div>
        </div>
        <ContactoPerfil v-bind="contacto" :centrado="centrado" :modo="modo" />
    </header>

    <!-- Tarjeta flotante -->
    <header
        v-else-if="estilo === 'tarjeta'"
        class="pe-tarjeta pe-cabecera-tarjeta relative mb-7 px-5 pb-6"
        :class="conAvatar() ? 'mt-12 pt-0' : 'pt-6'"
        :data-centrado="centrado || undefined"
        data-cabecera="tarjeta"
    >
        <div
            class="flex flex-col"
            :class="centrado ? 'items-center' : 'items-start'"
        >
            <AvatarPerfil
                :foto="foto"
                :nombre="nombre"
                :monograma="monograma"
                class="-mt-12 size-24 text-[1.9em]"
                :class="forma"
            />
            <img
                v-if="logo"
                :src="logo"
                alt=""
                class="mt-3 h-8 w-auto object-contain"
            />
            <h1
                v-if="mostrarNombre && nombre"
                class="pe-titulo mt-3 text-[calc(1.45em*var(--pe-escala))] leading-tight text-balance"
            >
                {{ nombre }}
            </h1>
            <p
                v-if="descripcion"
                class="mt-1.5 max-w-[34rem] leading-relaxed text-pretty opacity-80"
            >
                <TextoEnriquecido :texto="descripcion" />
            </p>
        </div>
        <ContactoPerfil v-bind="contacto" :centrado="centrado" :modo="modo" />
    </header>

    <!-- Dividido: texto a la izquierda, foto a la derecha -->
    <header
        v-else-if="estilo === 'dividido'"
        class="mb-7 border-b border-(--pe-borde) pb-6 text-left"
        data-cabecera="dividido"
    >
        <div class="flex items-center gap-4">
            <div class="min-w-0 flex-1">
                <img
                    v-if="logo"
                    :src="logo"
                    alt=""
                    class="mb-2 h-8 w-auto object-contain"
                />
                <h1
                    v-if="mostrarNombre && nombre"
                    class="pe-titulo text-[calc(1.6em*var(--pe-escala))] leading-[1.1] text-balance"
                >
                    {{ nombre }}
                </h1>
                <p
                    v-if="descripcion"
                    class="mt-2 leading-relaxed text-pretty text-(--pe-suave)"
                >
                    <TextoEnriquecido :texto="descripcion" />
                </p>
            </div>
            <AvatarPerfil
                :foto="foto"
                :nombre="nombre"
                :monograma="monograma"
                class="size-24 text-[1.9em] @md:size-32 @md:text-[2.4em]"
                :class="forma"
            />
        </div>
        <ContactoPerfil v-bind="contacto" :centrado="false" :modo="modo" />
    </header>

    <!-- Editorial: título grande alineado a la izquierda -->
    <header v-else class="mb-8 text-left" data-cabecera="editorial">
        <div v-if="conAvatar() || logo" class="mb-6 flex items-center gap-3">
            <AvatarPerfil
                :foto="foto"
                :nombre="nombre"
                :monograma="monograma"
                class="size-14 border-2 text-[1.15em]"
                :class="forma"
            />
            <img
                v-if="logo"
                :src="logo"
                alt=""
                class="h-8 w-auto object-contain"
            />
        </div>
        <span
            class="block h-1 w-12 rounded-full bg-(--pe-acento)"
            aria-hidden="true"
        />
        <h1
            v-if="mostrarNombre && nombre"
            class="pe-titulo mt-4 text-[calc(2em*var(--pe-escala))] leading-[1.05] text-balance"
        >
            {{ nombre }}
        </h1>
        <p
            v-if="descripcion"
            class="mt-3 max-w-[34rem] text-[1.06em] leading-relaxed text-pretty text-(--pe-suave)"
        >
            <TextoEnriquecido :texto="descripcion" />
        </p>
        <ContactoPerfil v-bind="contacto" :centrado="false" :modo="modo" />
    </header>
</template>
