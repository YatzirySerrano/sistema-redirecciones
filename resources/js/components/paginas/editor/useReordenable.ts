import { onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Reordenar arrastrando con el ratón, el dedo o un lápiz (Pointer Events,
 * sin dependencias). Se arrastra desde el asa del elemento (con
 * `touch-action: none`); el resto de la tarjeta conserva el desplazamiento
 * normal en pantallas táctiles. El teclado y los botones «Subir/Bajar»
 * ofrecen la misma función sin arrastrar.
 *
 * Los elementos reordenables son los hijos directos del contenedor con el
 * atributo `data-reordenable`.
 */
export function useReordenable(
    contenedor: Ref<HTMLElement | null>,
    alMover: (desde: number, hasta: number) => void,
) {
    const arrastrando = ref<number | null>(null);
    /** Posición de inserción en la lista original (antes del elemento k). */
    const indicador = ref<number | null>(null);

    let centros: number[] = [];
    let inicioY = 0;
    let elemento: HTMLElement | null = null;
    let desplazamiento = 0;

    const elementos = () =>
        Array.from(contenedor.value?.children ?? []).filter(
            (e): e is HTMLElement =>
                e instanceof HTMLElement && e.dataset.reordenable !== undefined,
        );

    const alMoverPuntero = (e: PointerEvent) => {
        if (arrastrando.value === null || !elemento) {
            return;
        }

        e.preventDefault();
        const y = e.clientY;
        elemento.style.transform = `translateY(${y - inicioY + (window.scrollY - desplazamiento)}px)`;
        indicador.value = centros.filter((c) => c < y + window.scrollY).length;

        // Desplazamiento automático cerca de los bordes.
        if (y < 72) {
            window.scrollBy({ top: -14 });
        } else if (y > window.innerHeight - 72) {
            window.scrollBy({ top: 14 });
        }
    };

    const terminar = () => {
        window.removeEventListener('pointermove', alMoverPuntero);
        window.removeEventListener('pointerup', terminar);
        window.removeEventListener('pointercancel', terminar);

        if (elemento) {
            elemento.style.transform = '';
            elemento.style.zIndex = '';
            elemento.removeAttribute('data-arrastrando');
        }

        const desde = arrastrando.value;
        const k = indicador.value;
        arrastrando.value = null;
        indicador.value = null;
        elemento = null;

        if (desde === null || k === null || k === desde || k === desde + 1) {
            return;
        }

        alMover(desde, k > desde ? k - 1 : k);
    };

    const iniciar = (e: PointerEvent, indice: number) => {
        if (e.button !== 0 && e.pointerType === 'mouse') {
            return;
        }

        const lista = elementos();
        elemento = lista[indice] ?? null;

        if (!elemento) {
            return;
        }

        e.preventDefault();
        centros = lista.map((el) => {
            const r = el.getBoundingClientRect();

            return r.top + window.scrollY + r.height / 2;
        });
        inicioY = e.clientY;
        desplazamiento = window.scrollY;
        arrastrando.value = indice;
        indicador.value = indice;
        elemento.style.zIndex = '20';
        elemento.setAttribute('data-arrastrando', '');
        window.addEventListener('pointermove', alMoverPuntero, {
            passive: false,
        });
        window.addEventListener('pointerup', terminar);
        window.addEventListener('pointercancel', terminar);
    };

    /** Flechas arriba/abajo sobre el asa. */
    const teclado = (e: KeyboardEvent, indice: number, total: number) => {
        if (e.key === 'ArrowUp' && indice > 0) {
            e.preventDefault();
            alMover(indice, indice - 1);
        } else if (e.key === 'ArrowDown' && indice < total - 1) {
            e.preventDefault();
            alMover(indice, indice + 1);
        }
    };

    onBeforeUnmount(terminar);

    return { arrastrando, indicador, iniciar, teclado };
}

export function mover<T>(lista: T[], desde: number, hasta: number): void {
    const [elemento] = lista.splice(desde, 1);

    if (elemento !== undefined) {
        lista.splice(hasta, 0, elemento);
    }
}
