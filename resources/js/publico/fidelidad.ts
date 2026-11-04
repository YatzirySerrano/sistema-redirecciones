/**
 * Página pública de la tarjeta de Fidelidad (/f/{codigo} y
 * /mi-fidelidad/{token}). Monta el MISMO renderizador que la vista previa
 * del editor a partir del JSON incrustado por el servidor (datos mínimos y
 * no sensibles; nunca teléfono ni correo). Solo lectura.
 */
import { createApp, h } from 'vue';
import { cargarFuentes } from '@/components/fidelidad-diseno/catalogo';
import PaginaConsumidor from '@/components/fidelidad-diseno/PaginaConsumidor.vue';
import type { DatosPublicos } from '@/components/fidelidad-diseno/tipos';
import { proveerIconos } from '@/components/iconos/contexto';

function leerDatos(): DatosPublicos | null {
    const nodo = document.getElementById('datos-fidelidad');

    if (!nodo?.textContent) {
        return null;
    }

    try {
        const datos = JSON.parse(nodo.textContent) as DatosPublicos;

        // Solo enlaces relativos del propio sitio.
        if (
            datos.recuperar_url &&
            !/^\/[a-z0-9/-]*$/i.test(datos.recuperar_url)
        ) {
            datos.recuperar_url = undefined;
        }

        return datos;
    } catch {
        return null;
    }
}

const destino = document.getElementById('tarjeta-fidelidad');
const datos = leerDatos();

if (destino && datos) {
    cargarFuentes([datos.disenos.general, ...datos.disenos.variantes]);
    document.body.style.background = datos.disenos.general.pagina.color1;
    createApp({
        setup() {
            // Solo los trazos de los íconos usados (incrustados por el servidor).
            proveerIconos(datos.iconos);

            return () => h(PaginaConsumidor, { datos });
        },
    }).mount(destino);
    destino.removeAttribute('style');
    destino.classList.remove('min-h-screen');
}
