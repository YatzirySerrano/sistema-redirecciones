import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';

/**
 * Filtros de listado sincronizados con la URL (búsqueda, selects, orden).
 * Los cambios de texto se envían con retardo; el resto, al instante. El
 * filtrado, el orden y la paginación se resuelven en el servidor.
 */
export function useFiltros<T extends Record<string, string>>(
    url: string,
    iniciales: T,
    opciones: { retardo?: number; only?: string[] } = {},
) {
    const filtros = reactive({ ...iniciales }) as T;

    const enviar = () => {
        const consulta = Object.fromEntries(
            Object.entries(filtros).filter(([, valor]) => valor !== ''),
        );

        router.get(url, consulta, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: opciones.only,
        });
    };

    const enviarConRetardo = useDebounceFn(enviar, opciones.retardo ?? 350);

    watch(
        () => ({ ...filtros }),
        (nuevo, anterior) => {
            const soloTexto = Object.keys(nuevo).every(
                (clave) =>
                    clave === 'buscar' ||
                    nuevo[clave] === (anterior as T)[clave],
            );

            if (soloTexto) {
                void enviarConRetardo();
            } else {
                enviar();
            }
        },
    );

    const limpiar = () => {
        // Limpia los filtros pero conserva el orden elegido.
        for (const clave of Object.keys(filtros)) {
            if (clave !== 'orden' && clave !== 'direccion') {
                (filtros as Record<string, string>)[clave] = '';
            }
        }
    };

    const ordenarPor = (campo: string) => {
        const f = filtros as Record<string, string>;

        if (f.orden === campo) {
            f.direccion = f.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            f.orden = campo;
            f.direccion = campo === 'nombre' ? 'asc' : 'desc';
        }
    };

    return { filtros, limpiar, ordenarPor, enviar };
}
