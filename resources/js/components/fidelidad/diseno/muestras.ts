/**
 * Datos de EJEMPLO para la vista previa. Nunca se usan datos reales de
 * consumidores: solo el nombre del negocio y las reglas reales (meta y
 * recompensa) de la campaña elegida.
 */
import type {
    DatosPublicos,
    DocumentoDiseno,
    EstadoPrograma,
    ProgramaPublico,
} from '@/components/fidelidad-diseno/tipos';
import type { CampanaDiseno } from './tipos';

export const EJEMPLO = {
    consumidor: 'Ana (ejemplo)',
    campana: 'Campaña de ejemplo',
    meta: 10,
    recompensa: 'Un producto de regalo',
};

export function programaDeMuestra(
    campana: CampanaDiseno | null,
    visitas: number,
    estado: EstadoPrograma,
): ProgramaPublico {
    const meta = campana ? Math.max(1, campana.meta) : EJEMPLO.meta;
    const recompensa = campana?.recompensa ?? EJEMPLO.recompensa;
    const v = Math.max(0, Math.min(visitas, meta));

    return {
        campana: campana?.nombre ?? EJEMPLO.campana,
        visitas: v,
        meta,
        faltan: meta - v,
        recompensa,
        estado,
        recompensas:
            estado === 'disponible'
                ? [{ descripcion: recompensa, vence_en: null }]
                : [],
        variante: null,
    };
}

export function datosDeMuestra(
    negocio: string,
    general: DocumentoDiseno,
    tarjeta: DocumentoDiseno,
    programa: ProgramaPublico,
    medios: Record<string, string>,
    esVariante: boolean,
): DatosPublicos {
    return {
        negocio,
        consumidor: EJEMPLO.consumidor,
        programas: [{ ...programa, variante: esVariante ? 0 : null }],
        aviso: null,
        disenos: {
            general: esVariante ? general : tarjeta,
            variantes: esVariante ? [tarjeta] : [],
        },
        medios,
        recuperar_url: '#',
    };
}
