/**
 * Comprobación preliminar en el navegador de una URL de destino. Solo sirve
 * para orientar al usuario mientras escribe; la validación definitiva (y
 * más estricta) la hace el servidor.
 */
export type ComprobacionUrl =
    | { valida: true; dominio: string; segura: boolean }
    | { valida: false; motivo: string };

export function comprobarUrl(valor: string): ComprobacionUrl | null {
    const texto = valor.trim();

    if (texto === '') {
        return null;
    }

    if (!/^https?:\/\//i.test(texto)) {
        return {
            valida: false,
            motivo: 'La dirección debe comenzar con https://',
        };
    }

    try {
        const url = new URL(texto);

        if (url.username || url.password) {
            return {
                valida: false,
                motivo: 'No incluyas usuarios ni contraseñas en la dirección.',
            };
        }

        if (!url.hostname.includes('.')) {
            return {
                valida: false,
                motivo: 'Falta el dominio (por ejemplo, instagram.com).',
            };
        }

        return {
            valida: true,
            dominio: url.hostname.replace(/^www\./, ''),
            segura: url.protocol === 'https:',
        };
    } catch {
        return { valida: false, motivo: 'La dirección no es válida.' };
    }
}
