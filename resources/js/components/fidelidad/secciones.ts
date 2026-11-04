/**
 * Organización ÚNICA de Fidelidad, compartida por la navegación interna del
 * módulo (NavFidelidad.vue) y por la sección Fidelidad del menú lateral
 * (AppSidebar.vue): mismos grupos, nombres, íconos y permisos.
 *
 * Cada sección exige el mismo permiso que su ruta GET en
 * routes/fidelidad.php (si el rol no puede abrirla, no aparece).
 */
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    CreditCard,
    Gift,
    History,
    LayoutDashboard,
    Palette,
    ScanLine,
    UserCog,
    UsersRound,
} from '@lucide/vue';
import {
    actividad,
    contratacion,
    inicio,
    mostrador,
} from '@/routes/cliente/fidelidad';
import { index as campanas } from '@/routes/cliente/fidelidad/campanas';
import { index as consumidores } from '@/routes/cliente/fidelidad/consumidores';
import { edit as diseno } from '@/routes/cliente/fidelidad/diseno';
import { index as personal } from '@/routes/cliente/fidelidad/personal';

export type SeccionFidelidad = {
    clave: string;
    titulo: string;
    descripcion: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icono: LucideIcon;
    permiso: string;
    /** Activa solo en su URL exacta (no en subrutas). */
    exacta?: boolean;
    /** Se muestra aunque Fidelidad no esté contratada. */
    sinServicio?: boolean;
};

export type GrupoFidelidad = {
    clave: string;
    titulo: string;
    secciones: SeccionFidelidad[];
};

export const GRUPOS_FIDELIDAD = (): GrupoFidelidad[] => [
    {
        clave: 'inicio',
        titulo: 'Inicio',
        secciones: [
            {
                clave: 'resumen',
                titulo: 'Resumen',
                descripcion: 'Indicadores, alertas y accesos rápidos',
                href: inicio(),
                icono: LayoutDashboard,
                permiso: 'fidelidad.ver',
                exacta: true,
            },
        ],
    },
    {
        clave: 'operacion',
        titulo: 'Operación',
        secciones: [
            {
                clave: 'mostrador',
                titulo: 'Mostrador',
                descripcion: 'Registrar visitas y entregar recompensas',
                href: mostrador(),
                icono: ScanLine,
                permiso: 'fidelidad.ver',
            },
            {
                clave: 'consumidores',
                titulo: 'Consumidores',
                descripcion: 'Clientes inscritos, su progreso y sus canjes',
                href: consumidores(),
                icono: UsersRound,
                permiso: 'fidelidad.ver',
            },
        ],
    },
    {
        clave: 'programas',
        titulo: 'Programas',
        secciones: [
            {
                clave: 'campanas',
                titulo: 'Campañas',
                descripcion: 'Metas, recompensas y estado de cada campaña',
                href: campanas(),
                icono: Gift,
                permiso: 'fidelidad.ver',
            },
        ],
    },
    {
        clave: 'personalizacion',
        titulo: 'Personalización',
        secciones: [
            {
                clave: 'diseno',
                titulo: 'Diseñar mi programa',
                descripcion: 'Cómo ven tus clientes su página y su tarjeta',
                href: diseno(),
                icono: Palette,
                permiso: 'fidelidad.diseno',
            },
        ],
    },
    {
        clave: 'administracion',
        titulo: 'Administración',
        secciones: [
            {
                clave: 'personal',
                titulo: 'Personal autorizado',
                descripcion: 'Quién registra visitas y entrega recompensas',
                href: personal(),
                icono: UserCog,
                permiso: 'fidelidad.personal',
            },
            {
                clave: 'actividad',
                titulo: 'Historial y actividad',
                descripcion: 'Qué registró cada persona de tu equipo',
                href: actividad(),
                icono: History,
                permiso: 'fidelidad.auditoria',
            },
            {
                clave: 'contratacion',
                titulo: 'Contratación',
                descripcion: 'Plan, vigencia y pagos del servicio',
                href: contratacion(),
                icono: CreditCard,
                permiso: 'servicios.contratar',
                sinServicio: true,
            },
        ],
    },
];

/**
 * Grupos visibles para el usuario: filtra por permiso y, sin servicio
 * contratado (ni en solo lectura), deja únicamente la contratación.
 */
export function gruposVisibles(
    puede: (permiso: string) => boolean,
    habilitado: boolean,
    excluir: string[] = [],
): GrupoFidelidad[] {
    return GRUPOS_FIDELIDAD()
        .map((g) => ({
            ...g,
            secciones: g.secciones.filter(
                (s) =>
                    puede(s.permiso) &&
                    (habilitado || s.sinServicio) &&
                    !excluir.includes(s.clave),
            ),
        }))
        .filter((g) => g.secciones.length > 0);
}
