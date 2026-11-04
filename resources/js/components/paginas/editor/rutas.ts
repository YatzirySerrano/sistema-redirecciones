/**
 * URL que usa el editor de la Página de enlaces. Las construye el
 * controlador (EditorController::rutasEditor) para que el mismo editor
 * funcione desde el panel del negocio o desde la administración de DASTI.
 * Las que llevan un identificador usan el marcador __ID__.
 *
 * Las marcadas como `string | null` pueden no estar disponibles en otros
 * contextos (p. ej. la dirección pública la administra el negocio): el
 * editor oculta entonces los controles correspondientes.
 */
export type RutasEditor = {
    editor: string;
    borrador: string;
    publicar: string;
    despublicar: string;
    versiones: string;
    version: string;
    restaurar: string;
    medios: string;
    medios_subir: string;
    medios_eliminar: string;
    direccion_disponible: string | null;
    direccion_update: string | null;
    qr: string | null;
    dominios: string | null;
    inicio: string | null;
};

/** Sustituye el marcador __ID__ por el identificador indicado. */
export const conId = (plantilla: string, id: string | number): string =>
    plantilla.replace('__ID__', encodeURIComponent(String(id)));

/** Agrega parámetros de consulta a una URL (relativa o absoluta). */
export function conConsulta(
    url: string,
    parametros: Record<string, string | number>,
): string {
    const consulta = new URLSearchParams(
        Object.entries(parametros).map(([k, v]) => [k, String(v)]),
    ).toString();

    return consulta ? `${url}${url.includes('?') ? '&' : '?'}${consulta}` : url;
}
