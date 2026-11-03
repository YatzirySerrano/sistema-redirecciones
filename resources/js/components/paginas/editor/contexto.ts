import { inject, provide } from 'vue';
import type { InjectionKey, Ref } from 'vue';
import type { EditorPagina } from './useEditorPagina';

export type ContextoEditor = EditorPagina & {
    /** Puede modificar el contenido (permiso y servicio vigente). */
    editable: boolean;
    /** Slug de la subpágina que se está editando. */
    paginaActual: Ref<string>;
    /** Elemento recién agregado (se abre y se desplaza hasta él). */
    recienAgregado: Ref<string | null>;
    /** Abre el catálogo para agregar un elemento. */
    abrirCatalogo: () => void;
};

const CLAVE: InjectionKey<ContextoEditor> = Symbol('editor-pagina');

export const proveerEditor = (contexto: ContextoEditor) =>
    provide(CLAVE, contexto);

export function usarEditor(): ContextoEditor {
    const contexto = inject(CLAVE);

    if (!contexto) {
        throw new Error('El editor de la página no está disponible.');
    }

    return contexto;
}
