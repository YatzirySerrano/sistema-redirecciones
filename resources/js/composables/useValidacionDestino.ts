import { refDebounced } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import { ErrorPeticion, pedir } from '@/lib/peticion';
import { validacion } from '@/routes/destinos';

export type EstadoValidacionDestino =
    | { estado: 'vacia' }
    | { estado: 'comprobando' }
    | { estado: 'valida'; dominio: string; segura: boolean; url: string }
    | { estado: 'invalida'; motivo: string }
    | { estado: 'sin_comprobar'; motivo: string };

/**
 * Comprueba en vivo una URL de destino con las reglas del servidor
 * (ValidadorUrlDestino), con un breve retardo mientras se escribe.
 *
 * El navegador solo descarta lo evidente (vacío o sin http/https); la
 * aceptación siempre la confirma Laravel, que vuelve a validar al guardar.
 */
export function useValidacionDestino(url: Ref<string>) {
    const resultado = ref<EstadoValidacionDestino>({ estado: 'vacia' });
    const estable = refDebounced(url, 400);
    let controlador: AbortController | null = null;

    const comprobar = async (valor: string) => {
        controlador?.abort();
        const texto = valor.trim();

        if (texto === '') {
            resultado.value = { estado: 'vacia' };

            return;
        }

        if (!/^https?:\/\//i.test(texto)) {
            resultado.value = {
                estado: 'invalida',
                motivo: 'La dirección debe comenzar con https://',
            };

            return;
        }

        controlador = new AbortController();
        resultado.value = { estado: 'comprobando' };

        try {
            const respuesta = await pedir(validacion.url(), {
                metodo: 'POST',
                datos: { url: texto },
                senal: controlador.signal,
            });
            const datos = (await respuesta.json()) as {
                valida: boolean;
                mensaje: string | null;
                url?: string;
                dominio?: string;
                segura?: boolean;
            };

            resultado.value = datos.valida
                ? {
                      estado: 'valida',
                      dominio: datos.dominio ?? '',
                      segura: datos.segura ?? false,
                      url: datos.url ?? texto,
                  }
                : {
                      estado: 'invalida',
                      motivo: datos.mensaje ?? 'La dirección no es válida.',
                  };
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            resultado.value = {
                estado: 'sin_comprobar',
                motivo:
                    error instanceof ErrorPeticion
                        ? error.message
                        : 'No se pudo comprobar la dirección.',
            };
        }
    };

    // Mientras se escribe, el estado anterior deja de ser confiable.
    watch(url, (valor) => {
        if (valor.trim() !== '' && resultado.value.estado !== 'comprobando') {
            resultado.value = { estado: 'comprobando' };
        }
    });
    watch(estable, (valor) => void comprobar(valor), { immediate: true });

    return { resultado };
}
