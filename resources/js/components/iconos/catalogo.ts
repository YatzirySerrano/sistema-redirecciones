import { shallowRef } from 'vue';

/**
 * Biblioteca de íconos (resources/iconos/catalogo.json, generado por
 * scripts/generar-catalogo-iconos.mjs). El archivo completo SOLO se carga
 * en los editores, de forma diferida y en un chunk aparte, al abrir el
 * selector o al dibujar un ícono que aún no se conoce. Las páginas públicas
 * reciben del servidor únicamente los trazos que usan (ver contexto.ts).
 */

export type NodoIcono = [string, Record<string, string | number>];

export type IconoBiblioteca = {
    nombre: string;
    palabras: string;
    nodos: NodoIcono[];
    propio?: boolean;
    /** Nombre traducido automáticamente (íconos no curados). */
    auto?: boolean;
};

export type CategoriaIconos = {
    clave: string;
    nombre: string;
    iconos: string[];
};

export type Catalogo = {
    version: number;
    categorias: CategoriaIconos[];
    redes: Record<string, { nombre: string; palabras: string }>;
    iconos: Record<string, IconoBiblioteca>;
};

export const PREFIJO = 'i:';

/** Catálogo cargado (null mientras no se necesite). */
export const catalogo = shallowRef<Catalogo | null>(null);

let promesa: Promise<Catalogo> | null = null;

export function cargarCatalogo(): Promise<Catalogo> {
    if (catalogo.value) {
        return Promise.resolve(catalogo.value);
    }

    promesa ??= import('../../../iconos/catalogo.json?raw')
        .then((modulo) => {
            const datos = JSON.parse(modulo.default) as Catalogo;
            catalogo.value = datos;

            return datos;
        })
        .catch((error: unknown) => {
            promesa = null;

            throw error;
        });

    return promesa;
}

/** Valor guardado de un ícono de la biblioteca o de una red. */
export function valorDe(clave: string): string {
    return clave.startsWith('red:') ? clave : `${PREFIJO}${clave}`;
}

export function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

const VACIAS = new Set([
    'de',
    'del',
    'la',
    'el',
    'los',
    'las',
    'y',
    'e',
    'para',
    'con',
    'en',
    'a',
    'un',
    'una',
    'al',
    'o',
]);

function variantes(palabra: string): string[] {
    const lista = [palabra];

    if (palabra.length > 4 && palabra.endsWith('es')) {
        lista.push(palabra.slice(0, -2));
    }

    if (palabra.length > 3 && palabra.endsWith('s')) {
        lista.push(palabra.slice(0, -1));
    }

    return lista;
}

type Entrada = {
    valor: string;
    titulo: string;
    texto: string;
    auto: boolean;
};

const indices = new WeakMap<Catalogo, Entrada[]>();

function indice(datos: Catalogo): Entrada[] {
    let lista = indices.get(datos);

    if (!lista) {
        lista = [
            ...Object.entries(datos.iconos).map(([clave, i]) => ({
                valor: `${PREFIJO}${clave}`,
                titulo: ` ${normalizar(i.nombre)}`,
                texto: ` ${normalizar(i.nombre)} ${i.palabras}`,
                auto: !!i.auto,
            })),
            ...Object.entries(datos.redes).map(([red, r]) => ({
                valor: `red:${red}`,
                titulo: ` ${normalizar(r.nombre)}`,
                texto: ` ${normalizar(r.nombre)} ${r.palabras}`,
                auto: false,
            })),
        ];
        indices.set(datos, lista);
    }

    return lista;
}

/**
 * Búsqueda sin acentos ni mayúsculas (misma lógica que
 * App\Services\Iconos\CatalogoIconos::buscar). Devuelve valores guardables
 * (`i:` / `red:`) por relevancia.
 */
export function buscar(
    datos: Catalogo,
    consulta: string,
    permitidos?: Set<string>,
): string[] {
    const palabras = normalizar(consulta)
        .split(/[^a-z0-9]+/)
        .filter((p) => p && !VACIAS.has(p));

    if (palabras.length === 0) {
        return [];
    }

    const resultados: { valor: string; puntos: number }[] = [];

    for (const e of indice(datos)) {
        if (permitidos && !permitidos.has(e.valor)) {
            continue;
        }

        let puntos = 0;
        let coincide = true;

        for (const palabra of palabras) {
            // Por inicio de palabra: «agua» no encuentra «paraguas».
            const v = variantes(palabra).find((x) => e.texto.includes(` ${x}`));

            if (!v) {
                coincide = false;
                break;
            }

            puntos += e.titulo.includes(` ${v}`)
                ? e.titulo.startsWith(` ${v}`)
                    ? 4
                    : 3
                : 1;
        }

        if (coincide) {
            resultados.push({
                valor: e.valor,
                puntos: puntos - (e.auto ? 2 : 0),
            });
        }
    }

    return resultados.sort((a, b) => b.puntos - a.puntos).map((r) => r.valor);
}

/** Todos los valores: primero los curados (por categoría), luego el resto. */
export function todos(datos: Catalogo, incluirRedes = true): string[] {
    const vistos = new Set<string>();

    for (const categoria of datos.categorias) {
        for (const clave of categoria.iconos) {
            if (incluirRedes || !clave.startsWith('red:')) {
                vistos.add(valorDe(clave));
            }
        }
    }

    return [...vistos];
}
