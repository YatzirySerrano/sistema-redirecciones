/** Tipos del editor de diseño de la tarjeta (panel del negocio). */
import type { DocumentoDiseno } from '@/components/fidelidad-diseno/tipos';

export type ArchivoImagen = {
    id: string;
    url: string;
    tipo: string;
    ancho: number | null;
    alto: number | null;
    bytes: number;
};

export type EstadoDiseno = {
    existe: boolean;
    version: number;
    borrador: DocumentoDiseno;
    publicado: DocumentoDiseno | null;
    publicado_en: string | null;
    cambios_sin_publicar: boolean;
    medios: Record<string, string>;
};

export type VersionDiseno = {
    id: number;
    motivo: string;
    en: string;
    autor: string | null;
    documento: DocumentoDiseno;
};

export type CampanaDiseno = {
    id: number;
    nombre: string;
    estado: string;
    meta: number;
    recompensa: string | null;
    participantes: number;
    variante: boolean;
};

export type PlantillaDiseno = {
    clave: string;
    nombre: string;
    descripcion: string;
    documento: DocumentoDiseno;
};

export type RespuestaDiseno = {
    mensaje: string;
    avisos: string[];
    diseno: EstadoDiseno;
    versiones: VersionDiseno[];
};
