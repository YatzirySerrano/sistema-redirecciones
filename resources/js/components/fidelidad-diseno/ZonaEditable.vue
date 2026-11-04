<script lang="ts">
import { defineComponent, h, inject } from 'vue';
import type { PropType } from 'vue';
import { CLAVE_EDICION } from './edicion';
import type { ClaveZona } from './edicion';

/**
 * Zona seleccionable de la vista previa del editor.
 *
 * Sin contexto de edición (página pública) devuelve su contenido TAL CUAL:
 * no agrega envoltorio, atributos ni eventos. Dentro del editor envuelve el
 * contenido en un elemento enfocable: clic/toque o Intro lo selecciona y el
 * editor abre las herramientas correspondientes. Los estilos de resaltado
 * viven en el editor (VistaPrevia.vue).
 */
export default defineComponent({
    name: 'ZonaEditable',
    props: {
        zona: { type: String as PropType<ClaveZona>, required: true },
        /** Contiene otras zonas (se anuncia como grupo, no como botón). */
        contenedor: { type: Boolean, default: false },
        /** En el editor, texto que se muestra cuando la zona está vacía. */
        vacia: { type: String as PropType<string | null>, default: null },
    },
    setup(props, { slots }) {
        const edicion = inject(CLAVE_EDICION, null);

        return () => {
            const hijos = slots.default?.();

            if (!edicion || !edicion.disponible(props.zona)) {
                return hijos;
            }

            const nombre = edicion.nombre(props.zona);
            const activa = edicion.seleccionada.value === props.zona;
            const elegir = (e: Event) => {
                e.stopPropagation();
                edicion.seleccionar(props.zona);
            };

            return h(
                'div',
                {
                    class: 'zona-editable',
                    'data-zona': props.zona,
                    'data-seleccionada': activa ? '' : undefined,
                    tabindex: 0,
                    role: props.contenedor ? 'group' : 'button',
                    'aria-label': `${nombre}. Pulsa Intro para editar`,
                    'aria-pressed': props.contenedor ? undefined : activa,
                    onClick: elegir,
                    onKeydown: (e: KeyboardEvent) => {
                        if (
                            (e.key === 'Enter' || e.key === ' ') &&
                            e.target === e.currentTarget
                        ) {
                            e.preventDefault();
                            elegir(e);
                        }
                    },
                },
                [
                    props.vacia
                        ? h('span', { class: 'zona-vacia' }, props.vacia)
                        : hijos,
                    h(
                        'span',
                        { class: 'zona-etiqueta', 'aria-hidden': 'true' },
                        nombre,
                    ),
                ],
            );
        };
    },
});
</script>
