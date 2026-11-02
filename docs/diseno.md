# Sistema de diseño

Base: Tailwind CSS 4 + shadcn-vue (Reka UI) + Lucide. No se usan otras bibliotecas visuales.

## Tokens (variables CSS)

Definidos en `resources/css/app.css` (apariencia original) y sustituibles desde **Configuración › Apariencia** (`App\Services\Apariencia\TemaVisual`), sin recompilar:

| Token                                                 | Uso                                         |
| ----------------------------------------------------- | ------------------------------------------- |
| `--primary` / `--primary-foreground`                  | Selección, estado activo, foco, gráficas    |
| `--boton` / `--boton-foreground`                      | Botón de acción principal                   |
| `--enlace`                                            | Enlaces de texto                            |
| `--secondary`, `--accent`, `--muted`                  | Superficies secundarias y resaltados suaves |
| `--sidebar*`                                          | Navegación lateral                          |
| `--success`, `--warning`, `--destructive` (+ `-soft`) | Estados                                     |

Nunca se usan colores fijos (`text-white`, hexadecimales) en componentes: siempre tokens.

La hoja personalizada se inyecta en `<style id="tema-personalizado">` **después** de `@vite` y con selectores `html:root:not(.dark)` / `html:root.dark` (especificidad 0,2,1), para ganar a `:root`/`.dark` de app.css sin depender del orden de carga (causa del defecto de la fase 3).

## Escala

- Tipografía: Instrument Sans. Título de página `text-xl sm:text-2xl font-semibold`; título de sección `text-sm font-semibold`; cuerpo `text-sm`; metadatos `text-xs`; cifras `tabular-nums`.
- Espaciado: retícula de 4 px. Márgenes de página `px-4 sm:px-6 lg:px-8`; separación entre secciones `gap-6`.
- Radios: tarjetas y paneles `rounded-xl`; controles `rounded-lg`; insignias `rounded-full`.
- Sombras: `shadow-xs` en tarjetas, `shadow-sm` al pasar el cursor, `shadow-lg` en menús y diálogos.

## Componentes compartidos (`resources/js/components/comun`)

| Componente                                     | Para qué                                                                                                                                                             |
| ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `EncabezadoPagina`                             | Título, finalidad breve, ayuda (`ayuda`) y acciones de cada pantalla                                                                                                 |
| `Panel`                                        | Sección con título, descripción y acciones                                                                                                                           |
| `TarjetaIndicador`                             | Indicador que responde a una pregunta; detalle, ayuda, enlace y tono de alerta                                                                                       |
| `AccionesRegistro`                             | Acciones de filas y tarjetas en una sola línea: principales visibles (Ver, Editar), el resto en «Más acciones»; las peligrosas separadas, en rojo y con confirmación |
| `Ayuda`                                        | Ayuda contextual accesible: cursor, teclado y toque (Popover, no solo _hover_)                                                                                       |
| `SelectorVista` + `useVistaListado`            | Tabla o tarjetas, preferencia por usuario                                                                                                                            |
| `SelectorBuscable`                             | Selector con búsqueda local o remota paginada                                                                                                                        |
| `DialogoConfirmacion`, `CambiarEstadoRegistro` | Confirmaciones (controlables con `v-model:open`)                                                                                                                     |
| `IndicadorConexion`                            | Aviso sin conexión (PWA)                                                                                                                                             |

## Listados

- **Tabla**: comparar muchos registros. Columnas prioritarias, filas de una sola línea de acciones, `min-w` con desplazamiento horizontal controlado en pantallas pequeñas.
- **Tarjetas**: lo esencial de cada registro (identidad, estado, destino, uso) y acciones al pie. En la cuadrícula se usa `grid-cols-1` y `min-w-0` para que nunca desborde en 320 px.

## Logotipo

No existe un logotipo oficial: `AppLogo` es tratamiento tipográfico. Los íconos de la PWA (`public/iconos`, `favicon.*`, `apple-touch-icon.png`) son **temporales y neutrales** (motivo genérico de QR) y deben sustituirse por los oficiales antes de publicar.
