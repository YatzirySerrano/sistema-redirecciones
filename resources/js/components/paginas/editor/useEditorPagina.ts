import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import type { Documento } from '../tipos';
import { api, ErrorApi } from './api';
import type { RutasEditor } from './rutas';

export type ResumenPagina = {
    id: number;
    slug: string;
    estado: 'borrador' | 'publicado' | 'despublicado';
    estado_etiqueta: string;
    publicada: boolean;
    version: number;
    url_publica: string;
    url_canonica: string;
    dominio_principal: string | null;
    prefijo_url: string;
    publicado_en: string | null;
    borrador_actualizado_en: string | null;
    editor: string | null;
    cambios_sin_publicar: boolean;
    slug_cambiado_en: string | null;
};

export type Conflicto = {
    version: number;
    por: string | null;
    en: string | null;
};

type RespuestaPagina = {
    documento?: Documento;
    pagina: ResumenPagina;
    mensaje?: string;
};

const LIMITE_HISTORIAL = 100;

/**
 * Estado del editor: documento local, deshacer/rehacer, detección de
 * cambios sin guardar, guardado con control de versión (409) y errores del
 * servidor asociados a cada elemento.
 */
export function useEditorPagina(inicial: {
    documento: Documento;
    pagina: ResumenPagina;
    rutas: RutasEditor;
}) {
    const rutas = inicial.rutas;
    const documento = ref<Documento>(
        JSON.parse(JSON.stringify(inicial.documento)) as Documento,
    );
    const pagina = ref<ResumenPagina>(inicial.pagina);
    const guardadoJson = ref(JSON.stringify(documento.value));
    const errores = ref<Record<string, string>>({});
    const conflicto = ref<Conflicto | null>(null);
    const ocupado = ref<'guardando' | 'publicando' | 'despublicando' | null>(
        null,
    );
    const guardadoEn = ref<string | null>(
        inicial.pagina.borrador_actualizado_en,
    );

    // ---- Deshacer / rehacer ----
    const pilaDeshacer = ref<string[]>([]);
    const pilaRehacer = ref<string[]>([]);
    let instantanea = guardadoJson.value;
    let aplicando = false;

    const registrar = () => {
        const actual = JSON.stringify(documento.value);

        if (actual === instantanea) {
            return;
        }

        pilaDeshacer.value.push(instantanea);

        if (pilaDeshacer.value.length > LIMITE_HISTORIAL) {
            pilaDeshacer.value.shift();
        }

        instantanea = actual;
        pilaRehacer.value = [];
    };

    const registrarConRetardo = useDebounceFn(registrar, 450);

    watch(
        documento,
        () => {
            if (!aplicando) {
                void registrarConRetardo();
            }
        },
        { deep: true },
    );

    const aplicar = (json: string) => {
        aplicando = true;
        documento.value = JSON.parse(json) as Documento;
        void nextTick(() => {
            aplicando = false;
        });
    };

    const deshacer = () => {
        registrar();
        const anterior = pilaDeshacer.value.pop();

        if (anterior === undefined) {
            return;
        }

        pilaRehacer.value.push(instantanea);
        instantanea = anterior;
        aplicar(anterior);
    };

    const rehacer = () => {
        registrar();
        const siguiente = pilaRehacer.value.pop();

        if (siguiente === undefined) {
            return;
        }

        pilaDeshacer.value.push(instantanea);
        instantanea = siguiente;
        aplicar(siguiente);
    };

    /** Cambio en bloque (plantilla, restablecer) que se puede deshacer. */
    const cambiarConDeshacer = (cambio: () => void, mensaje: string) => {
        registrar();
        cambio();
        registrar();
        toast.success(mensaje, {
            action: { label: 'Deshacer', onClick: deshacer },
        });
    };

    const puedeDeshacer = computed(() => pilaDeshacer.value.length > 0);
    const puedeRehacer = computed(() => pilaRehacer.value.length > 0);
    const sucio = computed(
        () => JSON.stringify(documento.value) !== guardadoJson.value,
    );

    // ---- Servidor ----

    /** Adopta lo que devolvió el servidor sin perder lo escrito mientras tanto. */
    const adoptar = (respuesta: RespuestaPagina, enviado?: string) => {
        pagina.value = respuesta.pagina;
        guardadoEn.value = respuesta.pagina.borrador_actualizado_en;
        conflicto.value = null;

        if (!respuesta.documento) {
            return;
        }

        const json = JSON.stringify(respuesta.documento);
        guardadoJson.value = json;

        if (
            enviado === undefined ||
            JSON.stringify(documento.value) === enviado
        ) {
            instantanea = json;
            aplicar(json);
        }
    };

    const tratarError = (e: unknown, accion: string) => {
        if (!(e instanceof ErrorApi)) {
            toast.error(`No pudimos ${accion}. Inténtalo de nuevo.`);

            return;
        }

        if (e.estado === 409) {
            conflicto.value = (e.datos.conflicto as Conflicto | undefined) ?? {
                version: 0,
                por: null,
                en: null,
            };

            return;
        }

        if (e.estado === 422 && Object.keys(e.errores).length > 0) {
            errores.value = e.errores;
            const total = Object.keys(e.errores).length;
            toast.error(
                total === 1
                    ? 'Hay un dato por corregir. Lo marcamos en rojo.'
                    : `Hay ${total} datos por corregir. Los marcamos en rojo.`,
            );

            return;
        }

        toast.error(e.message);
    };

    const guardar = async (
        opciones: { forzar?: boolean; silencioso?: boolean } = {},
    ): Promise<boolean> => {
        if (ocupado.value) {
            return false;
        }

        registrar();
        ocupado.value = 'guardando';
        const enviado = JSON.stringify(documento.value);

        try {
            const respuesta = await api<RespuestaPagina>(rutas.borrador, {
                metodo: 'PUT',
                datos: {
                    documento: documento.value,
                    version:
                        opciones.forzar && conflicto.value
                            ? conflicto.value.version
                            : pagina.value.version,
                },
            });
            errores.value = {};
            adoptar(respuesta, enviado);

            if (!opciones.silencioso) {
                toast.success(
                    'Borrador guardado. Tus visitantes aún no ven estos cambios.',
                );
            }

            return true;
        } catch (e) {
            tratarError(e, 'guardar');

            return false;
        } finally {
            ocupado.value = null;
        }
    };

    const publicar = async (): Promise<boolean> => {
        if (sucio.value && !(await guardar({ silencioso: true }))) {
            return false;
        }

        ocupado.value = 'publicando';

        try {
            const respuesta = await api<RespuestaPagina>(rutas.publicar, {
                metodo: 'POST',
                datos: { version: pagina.value.version },
            });
            errores.value = {};
            adoptar(respuesta);
            toast.success(respuesta.mensaje ?? 'Tu página está publicada.');

            return true;
        } catch (e) {
            tratarError(e, 'publicar');

            return false;
        } finally {
            ocupado.value = null;
        }
    };

    const despublicar = async (): Promise<boolean> => {
        ocupado.value = 'despublicando';

        try {
            const respuesta = await api<RespuestaPagina>(rutas.despublicar, {
                metodo: 'POST',
            });
            pagina.value = respuesta.pagina;
            toast.success(respuesta.mensaje ?? 'Página despublicada.');

            return true;
        } catch (e) {
            tratarError(e, 'despublicar');

            return false;
        } finally {
            ocupado.value = null;
        }
    };

    /** Reemplaza todo (versión restaurada o recargada del servidor). */
    const reiniciar = (doc: Documento, resumen: ResumenPagina) => {
        const json = JSON.stringify(doc);
        guardadoJson.value = json;
        registrar();
        pilaDeshacer.value.push(instantanea);
        instantanea = json;
        aplicar(json);
        pagina.value = resumen;
        guardadoEn.value = resumen.borrador_actualizado_en;
        conflicto.value = null;
        errores.value = {};
    };

    // ---- Errores por elemento ----
    const erroresDe = (prefijo: string): Record<string, string> =>
        Object.fromEntries(
            Object.entries(errores.value)
                .filter(([clave]) => clave.startsWith(`${prefijo}.`))
                .map(([clave, mensaje]) => [
                    clave.slice(prefijo.length + 1),
                    mensaje,
                ]),
        );

    const bloquesConError = computed(
        () =>
            new Set(
                Object.keys(errores.value)
                    .filter((c) => c.startsWith('bloques.'))
                    .map((c) => c.split('.')[1]),
            ),
    );

    const quitarError = (clave: string) => {
        if (clave in errores.value) {
            const copia = { ...errores.value };
            delete copia[clave];
            errores.value = copia;
        }
    };

    return {
        rutas,
        documento,
        pagina,
        errores,
        conflicto,
        ocupado,
        guardadoEn,
        sucio,
        puedeDeshacer,
        puedeRehacer,
        deshacer,
        rehacer,
        cambiarConDeshacer,
        guardar,
        publicar,
        despublicar,
        reiniciar,
        erroresDe,
        bloquesConError,
        quitarError,
    };
}

export type EditorPagina = ReturnType<typeof useEditorPagina>;
