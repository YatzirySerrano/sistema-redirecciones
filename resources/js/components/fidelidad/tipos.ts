/** Tipos compartidos de las pantallas de Fidelidad DASTI. */

export type EstadoServicio = {
    activo: boolean;
    solo_lectura: boolean;
    estado: string | null;
    vence_en: string | null;
};

export type PuedeFidelidad = {
    visitas: boolean;
    canjear: boolean;
    consumidores: boolean;
    credenciales: boolean;
    ajustes: boolean;
    campanas: boolean;
    personal: boolean;
    auditoria: boolean;
};

export type ProgresoCampana = {
    campana_id: number;
    campana: string;
    participacion_id: number | null;
    visitas: number;
    meta: number;
    recompensa: string | null;
    completada: boolean;
    ultima_visita_en: string | null;
    version: number;
};

export type RecompensaDisponible = {
    id: number;
    descripcion: string;
    vence_en: string | null;
};

export type ConsumidorFicha = {
    id: number;
    nombre: string;
    alias: string | null;
    telefono: string | null;
    email: string | null;
    email_verificado: boolean;
    estado: string;
    tiene_enlace: boolean;
    consentimiento_en: string;
    created_at: string | null;
    progreso: ProgresoCampana[];
    recompensas: RecompensaDisponible[];
};

export type ReglasCampana = {
    descripcion: string | null;
    meta_visitas: number;
    recompensa: string;
    vigencia_visitas_dias: number | null;
    vigencia_recompensa_dias: number | null;
    limite_participantes: number | null;
    max_visitas_por_dia: number;
    intervalo_minimo_minutos: number;
    repetir_ciclo: boolean;
    inicia_en: string;
    termina_en: string | null;
};
