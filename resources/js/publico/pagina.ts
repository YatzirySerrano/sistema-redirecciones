/**
 * Página de enlaces pública. Monta el MISMO renderizador que usa la vista
 * previa del editor con el documento publicado que el servidor incrusta
 * (ya validado y filtrado). Sin cookies ni almacenamiento local.
 */
import { createApp, h } from 'vue';
import { proveerIconos } from '@/components/iconos/contexto';
import RenderizadorPagina from '@/components/paginas/RenderizadorPagina.vue';
import type { DatosPublicos } from '@/components/paginas/tipos';

const nodo = document.getElementById('pagina-datos');
const raiz = document.getElementById('pagina-enlaces');

if (nodo && raiz) {
    const datos = JSON.parse(nodo.textContent ?? '{}') as DatosPublicos;

    createApp({
        setup() {
            // Solo los trazos de los íconos usados (incrustados por el servidor).
            proveerIconos(datos.iconos);

            return () =>
                h(RenderizadorPagina, {
                    documento: datos.documento,
                    pagina: datos.pagina,
                    modo: 'publico',
                    base: datos.base,
                    contacto: datos.contacto,
                    qr: datos.qr,
                });
        },
    }).mount(raiz);
}
