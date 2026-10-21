import { ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Copia texto al portapapeles con respaldo para contextos sin la API
 * asíncrona (HTTP sin TLS en algunos navegadores).
 */
export function useCopiar() {
    const copiado = ref(false);
    let temporizador: ReturnType<typeof setTimeout> | undefined;

    const copiar = async (
        texto: string,
        mensaje = 'Copiado al portapapeles',
    ) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(texto);
            } else {
                const area = document.createElement('textarea');
                area.value = texto;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
            }

            copiado.value = true;
            toast.success(mensaje);
            clearTimeout(temporizador);
            temporizador = setTimeout(() => (copiado.value = false), 2000);
        } catch {
            toast.error(
                'No se pudo copiar. Selecciona el texto y cópialo manualmente.',
            );
        }
    };

    return { copiado, copiar };
}
