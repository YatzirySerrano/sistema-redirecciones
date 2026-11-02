import { router } from '@inertiajs/vue3';

/**
 * Colores personalizados de la interfaz (Configuración › Apariencia).
 *
 * El servidor ya los incluye en <style id="tema-personalizado"> al cargar la
 * página; aquí se actualizan en vivo cuando cambia la prop compartida
 * «tema» (p. ej. al guardar o restablecer), sin recargar ni recompilar.
 */
export type TokensTema = {
    claro: Record<string, string>;
    oscuro: Record<string, string>;
} | null;

const VARIABLE = /^--[a-z0-9-]+$/;
const VALOR = /^#[0-9a-f]{6}$/i;

function bloque(variables: Record<string, string>): string {
    return Object.entries(variables)
        .filter(([clave, valor]) => VARIABLE.test(clave) && VALOR.test(valor))
        .map(([clave, valor]) => `${clave}:${valor};`)
        .join('');
}

export function hojaDeEstilo(tokens: TokensTema): string {
    if (!tokens) {
        return '';
    }

    return `:root{${bloque(tokens.claro)}}.dark{${bloque(tokens.oscuro)}}`;
}

export function aplicarTema(tokens: TokensTema): void {
    if (typeof document === 'undefined') {
        return;
    }

    let estilo = document.getElementById('tema-personalizado');

    if (!estilo) {
        estilo = document.createElement('style');
        estilo.id = 'tema-personalizado';
        document.head.appendChild(estilo);
    }

    const css = hojaDeEstilo(tokens);

    if (estilo.textContent !== css) {
        estilo.textContent = css;
    }
}

export function initializeTema(): void {
    router.on('navigate', (event) => {
        const props = (event as CustomEvent).detail?.page?.props as
            | { tema?: TokensTema }
            | undefined;

        if (props && 'tema' in props) {
            aplicarTema(props.tema ?? null);
        }
    });
    router.on('success', (event) => {
        const props = (event as CustomEvent).detail?.page?.props as
            | { tema?: TokensTema }
            | undefined;

        if (props && 'tema' in props) {
            aplicarTema(props.tema ?? null);
        }
    });
}
