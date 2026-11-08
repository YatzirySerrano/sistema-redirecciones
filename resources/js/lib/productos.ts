import type { Producto } from "@/types";

/**
 * Utilidades de los enlaces de un producto físico. Un producto puede tener
 * varios enlaces permanentes: el principal y sus enlaces secundarios (reverso
 * QR de un combinado con destinos independientes, o QR 1 / QR 2).
 */

/** Todos los enlaces vigentes del producto físico, en orden. */
export const enlacesDe = (p: Producto): Producto[] => [
    p,
    ...(p.enlaces_secundarios ?? []),
];

/**
 * Enlaces impresos como QR (QR 1, QR 2): todos salvo el del NFC. En un
 * producto de un solo enlace es él mismo.
 */
export const enlacesQr = (p: Producto): Producto[] => {
    const qr = enlacesDe(p).filter((e) => e.medio !== "nfc");

    return qr.length > 0 ? qr : [p];
};

/** Destinos independientes: NFC + QR o dos QR, cada uno con su enlace. */
export const tieneVariosEnlaces = (p: Producto): boolean =>
    p.modalidad_destino === "independiente";

/** Etiqueta corta de un enlace para listados: «NFC», «QR», «QR 1», «QR 2». */
export const etiquetaCorta = (e: Producto): string => {
    if (e.medio === "nfc") {
        return "NFC";
    }

    return e.numero_qr ? `QR ${e.numero_qr}` : "QR";
};
