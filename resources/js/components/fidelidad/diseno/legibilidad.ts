/**
 * Avisos de legibilidad (WCAG 2.x): texto normal ≥ 4.5:1, elementos
 * gráficos y texto grande ≥ 3:1. Son recomendaciones: no impiden guardar.
 */
import { contraste } from '@/components/fidelidad-diseno/color';
import type { DocumentoDiseno } from '@/components/fidelidad-diseno/tipos';
import type { ClaveHerramienta } from './pasos';

/** `herramienta`: dónde se corrige (el editor lleva ahí al usuario). */
export type AvisoLegibilidad = {
    herramienta: ClaveHerramienta;
    mensaje: string;
};

const fmt = (n: number) => `${n.toFixed(1)}:1`;

export function avisosLegibilidad(
    d: DocumentoDiseno,
    variante: boolean,
): AvisoLegibilidad[] {
    const avisos: AvisoLegibilidad[] = [];
    const t = d.tarjeta;
    const fondos =
        t.fondo_tipo === 'solido' ? [t.color1] : [t.color1, t.color2];
    const peor = (color: string) =>
        Math.min(...fondos.map((f) => contraste(color, f)));

    if (t.fondo_tipo === 'imagen') {
        if (t.oscurecer < 30) {
            avisos.push({
                herramienta: 'tarjeta',
                mensaje:
                    'Sobre una foto, oscurece el fondo al menos 30 % para que el texto se lea bien.',
            });
        }
    } else {
        const c = peor(t.texto);

        if (c < 4.5) {
            avisos.push({
                herramienta: 'tarjeta',
                mensaje: `El texto de la tarjeta tiene poco contraste con el fondo (${fmt(c)}; se recomienda 4.5:1 o más).`,
            });
        }

        const s = peor(t.texto_suave);

        if (s < 3) {
            avisos.push({
                herramienta: 'tarjeta',
                mensaje: `El texto secundario de la tarjeta se lee con dificultad (${fmt(s)}; se recomienda 3:1 o más).`,
            });
        }

        const sello = peor(d.progreso.completo_fondo);

        if (sello < 1.6 && d.progreso.presentacion !== 'barra') {
            avisos.push({
                herramienta: 'progreso',
                mensaje:
                    'Los sellos completados casi no se distinguen del fondo de la tarjeta. Usa un color más contrastante.',
            });
        }
    }

    const icono = contraste(
        d.progreso.completo_icono,
        d.progreso.completo_fondo,
    );

    if (icono < 3) {
        avisos.push({
            herramienta: 'progreso',
            mensaje: `El icono del sello completado no resalta sobre su color (${fmt(icono)}; se recomienda 3:1 o más).`,
        });
    }

    if (
        contraste(d.progreso.completo_fondo, d.progreso.pendiente_fondo) < 1.4
    ) {
        avisos.push({
            herramienta: 'progreso',
            mensaje:
                'Los sellos completados y pendientes tienen colores muy parecidos: será difícil ver el avance.',
        });
    }

    if (!variante && d.pagina.fondo_tipo !== 'imagen') {
        const pagina = Math.min(
            contraste(d.colores.texto, d.pagina.color1),
            d.pagina.fondo_tipo === 'degradado'
                ? contraste(d.colores.texto, d.pagina.color2)
                : 21,
        );

        if (pagina < 4.5) {
            avisos.push({
                herramienta: 'fondo',
                mensaje: `El texto de la página tiene poco contraste con su fondo (${fmt(pagina)}).`,
            });
        }
    }

    if (!variante && contraste(d.colores.texto, d.colores.superficie) < 4.5) {
        avisos.push({
            herramienta: 'colores',
            mensaje:
                'El texto de las secciones informativas no contrasta con su recuadro.',
        });
    }

    return avisos;
}
