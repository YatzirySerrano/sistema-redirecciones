/**
 * Contrato entre el editor «Diseñar mi programa» y los renderizadores
 * compartidos. El editor PROVEE este contexto alrededor de la vista previa;
 * la página pública nunca lo provee, así que ahí `ZonaEditable` no agrega
 * ningún elemento, atributo ni control de edición.
 */
import type { InjectionKey, Ref } from 'vue';

export type ClaveZona =
    | 'fondo'
    | 'portada'
    | 'logo'
    | 'encabezado'
    | 'tarjeta'
    | 'progreso'
    | 'mensaje'
    | 'secciones'
    | 'terminos';

export type ContextoEdicion = {
    seleccionada: Ref<ClaveZona | null>;
    seleccionar: (zona: ClaveZona) => void;
    /** Nombre comprensible de la zona (para lectores y la etiqueta). */
    nombre: (zona: ClaveZona) => string;
    /** Zonas que no se editan en este diseño (p. ej. en una variante). */
    disponible: (zona: ClaveZona) => boolean;
};

export const CLAVE_EDICION: InjectionKey<ContextoEdicion> =
    Symbol('edicion-fidelidad');
