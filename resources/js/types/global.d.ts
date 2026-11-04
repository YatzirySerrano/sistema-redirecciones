import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            preferencias: { vistas: Record<string, 'tabla' | 'tarjetas'> };
            tema: import('@/lib/tema').TokensTema;
            servicios: Record<
                'fidelidad' | 'pagina_enlaces',
                {
                    activo: boolean;
                    solo_lectura: boolean;
                    estado: string | null;
                }
            > | null;
            marca: {
                logo: string | null;
                logo_oscuro: string | null;
                isotipo: string | null;
                isotipo_oscuro: string | null;
                temporal: boolean;
            };
            limites_archivos: Record<
                'logo' | 'imagen' | 'video',
                import('@/lib/archivos').LimiteArchivo
            > | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
