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
