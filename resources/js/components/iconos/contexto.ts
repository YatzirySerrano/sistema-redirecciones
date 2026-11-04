import { inject, provide } from 'vue';
import type { InjectionKey } from 'vue';
import type { NodoIcono } from './catalogo';

/** Trazos de los íconos de la biblioteca: valor (`i:<nombre>`) => nodos. */
export type MapaIconos = Record<string, NodoIcono[]>;

type ContextoIconos = {
    mapa: MapaIconos;
    /**
     * Si un ícono no está en `mapa`, ¿se descarga el catálogo completo?
     * Sí en los editores; NO en las páginas públicas (el servidor ya
     * incrusta todos los que se usan).
     */
    cargarFaltantes: boolean;
};

const CLAVE: InjectionKey<ContextoIconos> = Symbol('iconos');

/**
 * Llamar en el `setup` de la raíz de una página pública con el mapa que
 * incrusta el servidor (CatalogoIconos::paraDocumentos).
 */
export function proveerIconos(
    mapa: MapaIconos | null | undefined,
    opciones: { cargarFaltantes?: boolean } = {},
): void {
    provide(CLAVE, {
        mapa: mapa && typeof mapa === 'object' ? mapa : {},
        cargarFaltantes: opciones.cargarFaltantes ?? false,
    });
}

const EDITOR: ContextoIconos = { mapa: {}, cargarFaltantes: true };

/** Sin proveedor (editores) se usa el catálogo diferido. */
export function usarIconos(): ContextoIconos {
    return inject(CLAVE, EDITOR);
}
