import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permisos efectivos del usuario para mostrar u ocultar menús, botones y
 * acciones. Es solo presentación: el servidor comprueba cada operación.
 */
export function usePermisos() {
    const page = usePage();
    const lista = computed(
        () => new Set(page.props.auth?.permisos?.lista ?? []),
    );

    const puede = (permiso: string): boolean => lista.value.has(permiso);
    const puedeAlguno = (...permisos: string[]): boolean =>
        permisos.some((p) => lista.value.has(p));

    return {
        puede,
        puedeAlguno,
        esSuperadministrador: computed(
            () => page.props.auth?.permisos?.superadministrador ?? false,
        ),
    };
}
