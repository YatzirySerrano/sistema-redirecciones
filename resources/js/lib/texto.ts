/**
 * Normaliza texto para búsquedas: sin mayúsculas ni acentos («Ñ» se
 * conserva como «n»), con espacios compactados.
 */
export function normalizarBusqueda(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** ¿Contiene `texto` todas las palabras de `consulta`? */
export function coincide(texto: string, consulta: string): boolean {
    const normalizado = normalizarBusqueda(texto);

    return normalizarBusqueda(consulta)
        .split(' ')
        .filter(Boolean)
        .every((palabra) => normalizado.includes(palabra));
}
