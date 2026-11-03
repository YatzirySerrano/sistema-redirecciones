/**
 * Resaltado seguro de coincidencias en los resultados del mostrador: no usa
 * HTML (se pinta con <mark> desde la plantilla) e ignora mayúsculas y
 * acentos, igual que la búsqueda del servidor.
 */

export type Fragmento = { texto: string; marca: boolean };

const normalizar = (c: string) =>
    c.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

export function fragmentos(texto: string, consulta: string): Fragmento[] {
    const palabras = consulta
        .split(/\s+/)
        .map(normalizar)
        .filter((p) => p.length >= 2)
        .slice(0, 5);

    if (palabras.length === 0 || !texto) {
        return [{ texto, marca: false }];
    }

    const caracteres = Array.from(texto);
    let plano = '';
    const origen: number[] = [];

    caracteres.forEach((c, i) => {
        for (const n of normalizar(c)) {
            plano += n;
            origen.push(i);
        }
    });

    const marcado: boolean[] = Array.from(
        { length: caracteres.length },
        () => false,
    );

    for (const p of palabras) {
        let desde = plano.indexOf(p);

        while (desde !== -1) {
            for (let k = desde; k < desde + p.length; k++) {
                marcado[origen[k]] = true;
            }

            desde = plano.indexOf(p, desde + p.length);
        }
    }

    const salida: Fragmento[] = [];

    caracteres.forEach((c, i) => {
        const ultimo = salida[salida.length - 1];

        if (ultimo && ultimo.marca === marcado[i]) {
            ultimo.texto += c;
        } else {
            salida.push({ texto: c, marca: marcado[i] });
        }
    });

    return salida;
}
