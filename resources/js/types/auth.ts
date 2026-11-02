export type User = {
    id: number;
    name: string;
    email: string;
    rol: 'admin' | 'cliente';
    /** Nombre del rol dinámico asignado. */
    rol_nombre: string | null;
    two_factor_enabled: boolean;
    negocio: { id: number; nombre_comercial: string } | null;
    avatar?: string;
};

export type Permisos = {
    /** Cuenta del personal de DASTI (alcance: todos los negocios). */
    administrar: boolean;
    superadministrador: boolean;
    /** Permisos efectivos del rol («negocios.ver», «productos.crear», …). */
    lista: string[];
    ver_estadisticas: boolean;
    /** Administrador que aún debe activar la verificación en dos pasos. */
    requiere_dos_pasos: boolean;
};

export type Auth = {
    user: User;
    permisos: Permisos;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
