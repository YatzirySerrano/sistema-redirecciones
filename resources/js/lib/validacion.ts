/**
 * Validaciones progresivas de formularios (solo orientativas): Laravel
 * conserva siempre la validación final con las mismas reglas.
 */

export type EstadoCampo = {
    /** El valor cumple el formato esperado. */
    valido: boolean;
    /** Mensaje para la persona usuaria (vacío si no hay nada que decir). */
    mensaje: string;
};

export const DIGITOS_TELEFONO = 10;

/**
 * Deja solo los dígitos de un teléfono mexicano. Acepta lo que suele pegarse:
 * espacios, guiones, puntos, paréntesis y el prefijo internacional +52.
 * Debe coincidir con GuardarNegocioRequest::normalizarTelefono().
 */
export function normalizarTelefono(valor: string): string {
    const digitos = valor.replace(/\D+/g, '');

    if (digitos.length === 12 && digitos.startsWith('52')) {
        return digitos.slice(2);
    }

    return digitos;
}

/** Retroalimentación del teléfono mientras se escribe. */
export function estadoTelefono(valor: string): EstadoCampo {
    const digitos = normalizarTelefono(valor);

    if (digitos.length === 0) {
        return { valido: false, mensaje: '' };
    }

    if (/[^0-9+()\-.\s]/.test(valor)) {
        return {
            valido: false,
            mensaje:
                'El teléfono solo puede contener números (se permiten espacios, guiones y paréntesis).',
        };
    }

    if (digitos.length < DIGITOS_TELEFONO) {
        const faltan = DIGITOS_TELEFONO - digitos.length;

        return {
            valido: false,
            mensaje: `Faltan ${faltan} ${faltan === 1 ? 'dígito' : 'dígitos'} para completar el número.`,
        };
    }

    if (digitos.length > DIGITOS_TELEFONO) {
        const sobran = digitos.length - DIGITOS_TELEFONO;

        return {
            valido: false,
            mensaje: `El número tiene ${sobran} ${sobran === 1 ? 'dígito' : 'dígitos'} de más. Debe tener ${DIGITOS_TELEFONO} dígitos.`,
        };
    }

    return { valido: true, mensaje: 'El número tiene el formato correcto.' };
}

/** Presentación legible: 55 1234 5678. */
export function formatearTelefono(digitos: string): string {
    if (digitos.length !== DIGITOS_TELEFONO) {
        return digitos;
    }

    return `${digitos.slice(0, 2)} ${digitos.slice(2, 6)} ${digitos.slice(6)}`;
}

export const LONGITUD_MAXIMA_CORREO = 255;

/** Retroalimentación del formato de un correo electrónico. */
export function validarCorreo(valor: string): EstadoCampo {
    if (valor === '') {
        return { valido: false, mensaje: '' };
    }

    // Los espacios al inicio o al final se ignoran (el servidor los recorta).
    const correo = valor.trim();

    if (/\s/.test(correo)) {
        return {
            valido: false,
            mensaje: 'El correo no debe contener espacios.',
        };
    }

    if (correo.length > LONGITUD_MAXIMA_CORREO) {
        return {
            valido: false,
            mensaje: `El correo no debe tener más de ${LONGITUD_MAXIMA_CORREO} caracteres.`,
        };
    }

    const arrobas = correo.split('@').length - 1;

    if (arrobas === 0) {
        return {
            valido: false,
            mensaje: 'Falta la arroba (@). Ejemplo: nombre@negocio.com',
        };
    }

    if (arrobas > 1) {
        return {
            valido: false,
            mensaje: 'El correo solo debe tener una arroba (@).',
        };
    }

    const [usuario, dominio] = correo.split('@');

    if (usuario === '') {
        return {
            valido: false,
            mensaje: 'Escribe el nombre antes de la arroba (@).',
        };
    }

    if (
        !dominio ||
        !/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/.test(
            dominio,
        )
    ) {
        return {
            valido: false,
            mensaje:
                'El dominio no es válido. Revisa lo que va después de la arroba, por ejemplo: negocio.com',
        };
    }

    if (
        !/^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+$/.test(usuario) ||
        usuario.startsWith('.') ||
        usuario.endsWith('.') ||
        usuario.includes('..')
    ) {
        return {
            valido: false,
            mensaje: 'Ingresa un correo electrónico válido.',
        };
    }

    return { valido: true, mensaje: 'El formato del correo es correcto.' };
}

export type RequisitoContrasena = {
    clave: string;
    texto: string;
    cumplido: boolean;
};

/**
 * Traduce las reglas que envía Laravel (Password::toPasswordRulesString(),
 * p. ej. «minlength: 12; required: lower; required: upper; …») a requisitos
 * en español evaluados sobre el valor actual. Nunca expone el texto técnico.
 */
export function requisitosContrasena(
    valor: string,
    reglas: string | null | undefined,
): RequisitoContrasena[] {
    const partes = (reglas ?? '')
        .split(';')
        .map((parte) => parte.trim())
        .filter(Boolean);

    let minimo = 8;
    let maximo: number | null = null;
    const obligatorios = new Set<string>();

    for (const parte of partes) {
        const [nombre, dato] = parte.split(':').map((t) => t.trim());

        if (nombre === 'minlength' && Number(dato) > 0) {
            minimo = Number(dato);
        } else if (nombre === 'maxlength' && Number(dato) > 0) {
            maximo = Number(dato);
        } else if (nombre === 'required' && dato) {
            obligatorios.add(dato);
        }
    }

    const requisitos: RequisitoContrasena[] = [
        {
            clave: 'longitud',
            texto: `Tu contraseña debe tener al menos ${minimo} caracteres.`,
            cumplido: Array.from(valor).length >= minimo,
        },
    ];

    if (maximo !== null) {
        requisitos.push({
            clave: 'maximo',
            texto: `No debe superar ${maximo} caracteres.`,
            cumplido: Array.from(valor).length <= maximo,
        });
    }

    if (obligatorios.has('upper')) {
        requisitos.push(
            {
                clave: 'minuscula',
                texto: 'Incluye al menos una letra minúscula.',
                cumplido: /\p{Ll}/u.test(valor),
            },
            {
                clave: 'mayuscula',
                texto: 'Incluye al menos una letra mayúscula.',
                cumplido: /\p{Lu}/u.test(valor),
            },
        );
    } else if (obligatorios.has('lower')) {
        requisitos.push({
            clave: 'letra',
            texto: 'Incluye al menos una letra.',
            cumplido: /\p{L}/u.test(valor),
        });
    }

    if (obligatorios.has('digit')) {
        requisitos.push({
            clave: 'numero',
            texto: 'Incluye al menos un número.',
            cumplido: /\p{N}/u.test(valor),
        });
    }

    if (obligatorios.has('special')) {
        requisitos.push({
            clave: 'simbolo',
            texto: 'Incluye al menos un símbolo, por ejemplo ! @ # $.',
            cumplido: /[^\p{L}\p{N}\s]/u.test(valor),
        });
    }

    return requisitos;
}

/** Coincidencia entre la contraseña y su confirmación. */
export function estadoConfirmacion(
    contrasena: string,
    confirmacion: string,
): EstadoCampo {
    if (confirmacion === '') {
        return { valido: false, mensaje: '' };
    }

    return contrasena === confirmacion
        ? { valido: true, mensaje: 'Las contraseñas coinciden.' }
        : { valido: false, mensaje: 'Las contraseñas no coinciden.' };
}
