import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { pedir } from '@/lib/peticion';
import { vista as rutaVista } from '@/routes/preferencias';

export type Vista = 'tabla' | 'tarjetas';

/**
 * Vista «tabla» o «tarjetas» de un listado. La preferencia se guarda por
 * usuario en el servidor (se conserva al navegar, al volver y en otros
 * dispositivos). Sin preferencia: tarjetas en pantallas pequeñas y tabla en
 * las demás.
 */
export function useVistaListado(modulo: string) {
    const page = usePage();
    const guardada = page.props.preferencias?.vistas?.[modulo] as
        | Vista
        | undefined;
    const pequena =
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 639px)').matches;

    const vista = ref<Vista>(guardada ?? (pequena ? 'tarjetas' : 'tabla'));

    watch(vista, (nueva) => {
        void pedir(rutaVista.url(), {
            metodo: 'PUT',
            datos: { modulo, vista: nueva },
        }).catch(() => {
            // Si no se guarda, la vista elegida sigue aplicada en esta página.
        });
    });

    return { vista };
}
