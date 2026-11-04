import { usePage } from '@inertiajs/vue3';
import { mensajeErrorSubida } from '@/lib/archivos';
import type { LimiteArchivo, TipoLimite } from '@/lib/archivos';
import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import type { Medio } from '../tipos';
import { api, ErrorApi } from './api';
import { conId } from './rutas';

export type MedioBiblioteca = Medio & {
    tipo: 'imagen' | 'video';
    bytes: number;
    nombre: string | null;
};

type Uso = {
    usados: number;
    bytes: number;
    max_archivos: number;
    max_bytes: number;
};

/**
 * Biblioteca de imágenes y videos del negocio (compartida por todos los
 * selectores del editor). Solo contiene archivos del propio negocio.
 */
export const biblioteca = reactive({
    medios: [] as MedioBiblioteca[],
    uso: null as Uso | null,
    cargada: false,
    cargando: false,
    subiendo: false,
});

/** URL de la biblioteca; el editor las configura al abrirse. */
const rutas = { medios: '', subir: '', eliminar: '' };

/**
 * Configura las URL (vienen en la prop `rutas` del editor). Si cambian
 * (otro negocio), se descarta la lista cargada.
 */
export function configurarBiblioteca(nuevas: {
    medios: string;
    subir: string;
    eliminar: string;
}): void {
    if (nuevas.medios !== rutas.medios) {
        biblioteca.medios = [];
        biblioteca.uso = null;
        biblioteca.cargada = false;
    }

    Object.assign(rutas, nuevas);
}

export async function cargarBiblioteca(forzar = false): Promise<void> {
    if ((biblioteca.cargada && !forzar) || biblioteca.cargando) {
        return;
    }

    biblioteca.cargando = true;

    try {
        const respuesta = await api<{ medios: MedioBiblioteca[]; uso: Uso }>(
            rutas.medios,
        );
        biblioteca.medios = respuesta.medios;
        biblioteca.uso = respuesta.uso;
        biblioteca.cargada = true;
    } catch (e) {
        toast.error(
            e instanceof ErrorApi
                ? e.message
                : 'No pudimos cargar tus imágenes.',
        );
    } finally {
        biblioteca.cargando = false;
    }
}

export async function subirMedio(
    archivo: File,
    tipo: 'imagen' | 'video',
    uso: 'normal' | 'fondo' | 'favicon' | 'miniatura' = 'normal',
): Promise<MedioBiblioteca | null> {
    const datos = new FormData();
    datos.append('archivo', archivo);
    datos.append('tipo', tipo);
    datos.append('uso', uso);
    biblioteca.subiendo = true;

    try {
        const respuesta = await api<{ medio: MedioBiblioteca }>(rutas.subir, {
            metodo: 'POST',
            formulario: datos,
        });
        biblioteca.medios.unshift(respuesta.medio);

        if (biblioteca.uso) {
            biblioteca.uso.usados++;
            biblioteca.uso.bytes += respuesta.medio.bytes;
        }

        return respuesta.medio;
    } catch (e) {
        const limites = (usePage().props.limites_archivos ?? null) as Record<
            TipoLimite,
            LimiteArchivo
        > | null;
        const limite = limites?.[tipo === 'video' ? 'video' : 'imagen'];
        toast.error(
            e instanceof ErrorApi
                ? e.estado === 413 && limite
                    ? mensajeErrorSubida(413, limite)
                    : (e.errores.archivo ?? e.message)
                : 'No pudimos subir el archivo.',
        );

        return null;
    } finally {
        biblioteca.subiendo = false;
    }
}

export async function eliminarMedio(medio: MedioBiblioteca): Promise<boolean> {
    try {
        await api(conId(rutas.eliminar, medio.id), { metodo: 'DELETE' });
        biblioteca.medios = biblioteca.medios.filter((m) => m.id !== medio.id);

        if (biblioteca.uso) {
            biblioteca.uso.usados = Math.max(0, biblioteca.uso.usados - 1);
            biblioteca.uso.bytes = Math.max(
                0,
                biblioteca.uso.bytes - medio.bytes,
            );
        }

        return true;
    } catch (e) {
        toast.error(
            e instanceof ErrorApi
                ? e.message
                : 'No pudimos eliminar el archivo.',
        );

        return false;
    }
}
