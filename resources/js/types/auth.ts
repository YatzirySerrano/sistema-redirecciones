export type User = {
    id: number;
    name: string;
    email: string;
    rol: 'admin' | 'cliente';
    two_factor_enabled: boolean;
    negocio: { id: number; nombre_comercial: string } | null;
    avatar?: string;
};

export type Permisos = {
    administrar: boolean;
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
