<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, Palette, UserRound } from '@lucide/vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    { title: 'Perfil', href: editProfile(), icon: UserRound },
    { title: 'Contraseña y seguridad', href: editSecurity(), icon: KeyRound },
    { title: 'Apariencia', href: editAppearance(), icon: Palette },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <EncabezadoPagina
        titulo="Mi cuenta"
        descripcion="Administra tus datos de acceso y preferencias."
    />

    <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
        <nav
            class="flex gap-1 overflow-x-auto lg:w-56 lg:flex-col"
            aria-label="Mi cuenta"
        >
            <Link
                v-for="item in sidebarNavItems"
                :key="toUrl(item.href)"
                :href="item.href"
                :class="
                    cn(
                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground',
                        isCurrentOrParentUrl(item.href) &&
                            'bg-card text-foreground shadow-sm ring-1 ring-border',
                    )
                "
            >
                <component :is="item.icon" class="size-4" />
                {{ item.title }}
            </Link>
        </nav>

        <div class="min-w-0 flex-1">
            <section
                class="max-w-2xl space-y-10 rounded-xl border bg-card p-5 shadow-sm sm:p-8"
            >
                <slot />
            </section>
        </div>
    </div>
</template>
