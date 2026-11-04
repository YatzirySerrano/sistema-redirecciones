import type { Documento } from '../tipos';
import { duplicarBloque } from './bloques';

/** Plantilla del catálogo (App\Services\Paginas\PlantillasPagina). */
export type PlantillaPagina = {
    clave: string;
    nombre: string;
    descripcion: string;
    giros: string[];
    estilo: string;
    rasgos: string[];
    documento: Documento;
};

export type CategoriasPlantillas = {
    giros: Record<string, string>;
    estilos: Record<string, string>;
};

/**
 * Qué se aplica de la plantilla:
 * - diseno: colores, fondo, tipografía, botones y disposición.
 * - agregar: el diseño y, al final de «Inicio», los elementos de ejemplo.
 * - reemplazar: el diseño y los elementos de «Inicio» por los de ejemplo.
 */
export type ModoPlantilla = 'diseno' | 'agregar' | 'reemplazar';

/** Aplica la plantilla sobre el documento (in situ). */
export function aplicarPlantilla(
    doc: Documento,
    plantilla: PlantillaPagina,
    modo: ModoPlantilla,
): void {
    const copia = JSON.parse(JSON.stringify(plantilla.documento)) as Documento;
    doc.tema = copia.tema;

    if (modo === 'diseno') {
        return;
    }

    const inicio =
        doc.paginas.find((p) => p.slug === 'inicio') ?? doc.paginas[0];
    // Identificadores nuevos: nunca se mezclan con estadísticas anteriores.
    const ejemplo = (copia.paginas[0]?.bloques ?? []).map(duplicarBloque);
    inicio.bloques =
        modo === 'reemplazar' ? ejemplo : [...inicio.bloques, ...ejemplo];

    if (!doc.perfil.descripcion.trim()) {
        doc.perfil.descripcion = copia.perfil.descripcion;
    }
}

/** Texto sin acentos y en minúsculas para buscar. */
export const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/**
 * Hojas de tipografías de las miniaturas: una por combinación, sin tocar
 * la de la vista previa del editor.
 */
const hojas = new Set<string>();

export function cargarFuentesCatalogo(url: string | null): void {
    if (typeof document === 'undefined' || !url || hojas.has(url)) {
        return;
    }

    const enlace = document.createElement('link');
    enlace.rel = 'stylesheet';
    enlace.href = url;
    enlace.dataset.paginaCatalogo = '';
    document.head.appendChild(enlace);
    hojas.add(url);
}
