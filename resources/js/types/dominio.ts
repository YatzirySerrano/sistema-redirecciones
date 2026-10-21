/**
 * Tipos de las props que envían los controladores (API Resources de Laravel).
 */

export type Opcion = { value: string; label: string };

export type EnlacePaginacion = {
    url: string | null;
    label: string;
    active: boolean;
};

export type MetaPaginacion = {
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
    per_page: number;
    links: EnlacePaginacion[];
};

export type Paginado<T> = {
    data: T[];
    meta: MetaPaginacion;
};

export type Coleccion<T> = { data: T[] };

export type Recurso<T> = { data: T };

export type EstadoCuenta = 'activo' | 'suspendido';
export type TipoProducto = 'qr' | 'nfc' | 'combinado';

export type Producto = {
    id: number;
    codigo: string;
    nombre: string;
    tipo: TipoProducto;
    tipo_etiqueta: string;
    url_permanente: string;
    url_destino: string | null;
    estado: EstadoCuenta;
    estado_etiqueta: string;
    total_accesos: number;
    ultimo_acceso_en: string | null;
    destino_actualizado_en: string | null;
    created_at: string | null;
    negocio?: {
        id: number;
        nombre_comercial: string;
        estado: EstadoCuenta;
        tiene_logo: boolean;
    };
    // Solo administrador:
    estado_preparacion?: 'pendiente' | 'listo' | 'entregado';
    estado_preparacion_etiqueta?: string;
    fecha_entrega?: string | null;
    referencia_comercial?: string | null;
    notas_internas?: string | null;
    suspendido_en?: string | null;
    permisos: { cambiar_destino: boolean; descargar_qr: boolean };
};

export type Negocio = {
    id: number;
    nombre_comercial: string;
    responsable: string | null;
    email_contacto: string | null;
    telefono: string | null;
    notas_internas: string | null;
    estado: EstadoCuenta;
    estado_etiqueta: string;
    tiene_logo: boolean;
    logo_url: string | null;
    created_at: string | null;
    suspendido_en: string | null;
    productos_count?: number;
    productos_qr_count?: number | null;
    productos_nfc_count?: number | null;
    productos_activos_count?: number | null;
    usuarios_count?: number;
    ultima_actividad?: string | null;
};

export type Usuario = {
    id: number;
    name: string;
    email: string;
    rol: 'admin' | 'cliente';
    rol_etiqueta: string;
    estado: EstadoCuenta;
    estado_etiqueta: string;
    negocio?: {
        id: number;
        nombre_comercial: string;
        estado: EstadoCuenta;
    } | null;
    debe_cambiar_password: boolean;
    two_factor: boolean;
    ultimo_acceso_en: string | null;
    created_at: string | null;
    es_actual: boolean;
};

export type CambioDestino = {
    id: number;
    url_anterior: string | null;
    url_nueva: string | null;
    origen: 'admin' | 'cliente' | 'sistema';
    origen_etiqueta: string;
    autor: string;
    producto?: { id: number; nombre: string; codigo: string };
    created_at: string;
};

export type RegistroAuditoria = {
    id: number;
    accion: string;
    accion_etiqueta: string;
    descripcion: string;
    usuario: { id: number; name: string; email: string } | null;
    negocio: { id: number; nombre_comercial: string } | null;
    recurso: { tipo: string; id: number } | null;
    valores_anteriores: Record<string, unknown> | null;
    valores_nuevos: Record<string, unknown> | null;
    ip: string | null;
    created_at: string;
};

export type PuntoSerie = { fecha: string; total: number };

export type ElementoDesglose = {
    clave: string;
    etiqueta: string;
    total: number;
};

export type TotalesEstadisticas = {
    total: number;
    bots: number;
    visitantes_estimados: number;
    productos_con_accesos: number;
    promedio_diario: number;
};

export type ProductoTop = {
    id: number;
    nombre: string;
    codigo: string;
    tipo: string;
    negocio: string;
    total: number;
};

export type NegocioTop = { id: number; nombre: string; total: number };

export type OpcionesQr = {
    color: string;
    fondo: string;
    fondo_transparente: boolean;
    margen: 'ninguno' | 'reducido' | 'estandar' | 'amplio';
    incluir_logo: boolean;
};

export type Barra = {
    clave: string | number;
    etiqueta: string;
    detalle?: string;
    total: number;
    href?: string;
};
