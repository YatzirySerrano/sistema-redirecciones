/** Servicio del catálogo tal como lo envía Cliente\ServicioController. */
export type ServicioCatalogo = {
    clave: string;
    nombre: string;
    descripcion: string | null;
    beneficios: string[];
    incluido: boolean;
    precio_centavos: number | null;
    moneda: string;
    impuestos_incluidos: boolean;
    vigencia_meses: number | null;
    estado: {
        activo: boolean;
        solo_lectura: boolean;
        estado: string | null;
        vence_en: string | null;
    };
    no_disponible: string | null;
    puede_contratar: boolean;
    url_modulo: string | null;
};

/** Situación comercial legible del servicio para el negocio. */
export function situacion(s: ServicioCatalogo): {
    clave: 'incluido' | 'activo' | 'lectura' | 'suspendido' | 'disponible';
    texto: string;
    tono: string;
} {
    if (s.incluido) {
        return {
            clave: 'incluido',
            texto: 'Incluido con tus productos',
            tono: 'bg-success-soft text-success',
        };
    }

    if (s.estado.activo) {
        return {
            clave: 'activo',
            texto: 'Contratado',
            tono: 'bg-success-soft text-success',
        };
    }

    if (s.estado.solo_lectura) {
        return {
            clave: 'lectura',
            texto:
                s.estado.estado === 'vencida'
                    ? 'Vencido · solo lectura'
                    : 'En revisión · solo lectura',
            tono: 'bg-warning-soft text-warning',
        };
    }

    if (s.estado.estado === 'suspendida') {
        return {
            clave: 'suspendido',
            texto: 'Suspendido',
            tono: 'bg-destructive/10 text-destructive',
        };
    }

    return {
        clave: 'disponible',
        texto: 'Disponible para contratar',
        tono: 'bg-accent text-accent-foreground',
    };
}

/** Servicio en la administración de DASTI. */
export type ServicioAdmin = {
    id: number;
    clave: string;
    nombre: string;
    descripcion: string | null;
    beneficios: string[];
    es_base: boolean;
    precio_centavos: number | null;
    moneda: string;
    impuestos_incluidos: boolean;
    vigencia_meses: number | null;
    contratable_en_linea: boolean;
    activo: boolean;
    contrataciones_activas: number;
};
