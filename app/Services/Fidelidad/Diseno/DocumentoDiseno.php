<?php

namespace App\Services\Fidelidad\Diseno;

use App\Models\ArchivoPublico;
use App\Models\Negocio;
use App\Services\Medios\AlmacenMedios;

/**
 * Documento de apariencia de la tarjeta digital (esquema 1).
 *
 * Es la ÚNICA puerta de entrada de un diseño: el borrador y la publicación
 * pasan por `sanear()`. Reglas:
 *
 * - Lista blanca: solo existen los campos de ESPECIFICACION; cualquier otro
 *   se descarta. Los valores inválidos vuelven al predeterminado.
 * - Colores solo `#rrggbb`; números acotados a su rango; opciones de listas
 *   cerradas (tipografías, iconos, presentaciones, decoraciones…).
 * - Textos sin etiquetas, sin caracteres de control ni de dirección y con
 *   longitud máxima. No hay campos de URL, HTML ni CSS libre.
 * - Imágenes solo por UUID de un ArchivoPublico del MISMO negocio y del
 *   módulo de Fidelidad; cualquier otra referencia se elimina.
 *
 * El documento nunca contiene metas, visitas ni recompensas: esos datos
 * provienen siempre de las reglas reales de cada campaña.
 *
 * El catálogo se replica en resources/js/components/fidelidad-diseno/catalogo.ts.
 */
final class DocumentoDiseno
{
    public const ESQUEMA = 1;

    /** Tamaño máximo del documento recibido (bytes de JSON). */
    public const MAX_BYTES = 65_536;

    public const PLANTILLAS = ['cafeteria', 'spa', 'salon', 'barberia', 'restaurante', 'panaderia', 'mascotas', 'minimalista'];

    /** @var array<string, array{0: string, 1: string}> clave => [familia CSS, pesos] */
    public const FUENTES = [
        'inter' => ['Inter', '400,500,600,700'],
        'poppins' => ['Poppins', '400,500,600,700'],
        'montserrat' => ['Montserrat', '400,500,600,700'],
        'nunito' => ['Nunito', '400,600,700,800'],
        'quicksand' => ['Quicksand', '400,500,600,700'],
        'dm-sans' => ['DM Sans', '400,500,700'],
        'josefin-sans' => ['Josefin Sans', '400,600,700'],
        'space-grotesk' => ['Space Grotesk', '400,500,700'],
        'playfair-display' => ['Playfair Display', '400,600,700'],
        'lora' => ['Lora', '400,600,700'],
        'fraunces' => ['Fraunces', '400,600,700'],
        'cormorant-garamond' => ['Cormorant Garamond', '400,600,700'],
        'dm-serif-display' => ['DM Serif Display', '400'],
        'bebas-neue' => ['Bebas Neue', '400'],
        'pacifico' => ['Pacifico', '400'],
        'caveat' => ['Caveat', '400,600,700'],
    ];

    public const ICONOS = [
        'cafe', 'estrella', 'corazon', 'tijeras', 'flor', 'gota', 'hoja', 'regalo', 'check', 'huella',
        'cubiertos', 'pastel', 'helado', 'pizza', 'croissant', 'copa', 'diamante', 'corona', 'destello', 'sol', 'pesa', 'pincel',
    ];

    public const PRESENTACIONES = ['sellos', 'cuadricula', 'circulos', 'barra', 'premium', 'ruta', 'boleto'];

    public const DECORACIONES = ['ninguna', 'puntos', 'ondas', 'granos', 'hojas', 'rayas', 'confeti', 'arcos', 'burbujas', 'diamantes'];

    public const SOMBRAS = ['ninguna', 'suave', 'media', 'intensa', 'flotante'];

    public const MAX_SECCIONES = 4;

    public const MAX_ELEMENTOS = 8;

    /**
     * Campos admitidos: ruta => [tipo, ...parámetros].
     *
     * @var array<string, array<int, mixed>>
     */
    private const ESPECIFICACION = [
        'plantilla' => ['opcion', self::PLANTILLAS],

        'programa.nombre' => ['texto', 60],
        'programa.lema' => ['texto', 120],

        'identidad.logo' => ['imagen'],
        'identidad.portada' => ['imagen'],
        'identidad.mostrar_negocio' => ['bool'],

        'tipografia.titulos' => ['fuente'],
        'tipografia.texto' => ['fuente'],

        'colores.primario' => ['color'],
        'colores.secundario' => ['color'],
        'colores.texto' => ['color'],
        'colores.texto_suave' => ['color'],
        'colores.superficie' => ['color'],
        'colores.borde' => ['color'],

        'pagina.fondo_tipo' => ['opcion', ['solido', 'degradado', 'imagen']],
        'pagina.color1' => ['color'],
        'pagina.color2' => ['color'],
        'pagina.angulo' => ['entero', 0, 360],
        'pagina.imagen' => ['imagen'],
        'pagina.decoracion' => ['opcion', self::DECORACIONES],

        'tarjeta.orientacion' => ['opcion', ['vertical', 'horizontal']],
        'tarjeta.fondo_tipo' => ['opcion', ['solido', 'degradado', 'imagen']],
        'tarjeta.color1' => ['color'],
        'tarjeta.color2' => ['color'],
        'tarjeta.angulo' => ['entero', 0, 360],
        'tarjeta.imagen' => ['imagen'],
        'tarjeta.oscurecer' => ['entero', 0, 80],
        'tarjeta.texto' => ['color'],
        'tarjeta.texto_suave' => ['color'],
        'tarjeta.radio' => ['entero', 0, 40],
        'tarjeta.borde_ancho' => ['entero', 0, 6],
        'tarjeta.borde_color' => ['color'],
        'tarjeta.sombra' => ['opcion', self::SOMBRAS],
        'tarjeta.decoracion' => ['opcion', self::DECORACIONES],
        'tarjeta.decoracion_opacidad' => ['entero', 0, 60],

        'progreso.presentacion' => ['opcion', self::PRESENTACIONES],
        'progreso.icono' => ['opcion', self::ICONOS],
        'progreso.completo_fondo' => ['color'],
        'progreso.completo_icono' => ['color'],
        'progreso.pendiente_fondo' => ['color'],
        'progreso.pendiente_icono' => ['color'],
        'progreso.pendiente_estilo' => ['opcion', ['contorno', 'tenue', 'punteado']],
        'progreso.mostrar_contador' => ['bool'],
        'progreso.mostrar_barra' => ['bool'],
        'progreso.barra_color' => ['color'],
        'progreso.barra_fondo' => ['color'],

        'contenido.saludo' => ['opcion', ['alias', 'oculto']],
        'contenido.mostrar_campana' => ['bool'],
        'contenido.mostrar_recompensa' => ['bool'],
        'contenido.mensaje_progreso' => ['texto', 160],
        'contenido.mensaje_meta' => ['texto', 160],
        'contenido.mensaje_canje' => ['texto', 160],
        'contenido.terminos' => ['parrafo', 2000],
    ];

    public function __construct(private readonly AlmacenMedios $medios) {}

    /**
     * Documento predeterminado (elegante y neutro; también es el que se
     * muestra si el negocio aún no publica un diseño).
     *
     * @return array<string, mixed>
     */
    public static function predeterminado(): array
    {
        return [
            'esquema' => self::ESQUEMA,
            'plantilla' => 'minimalista',
            'programa' => ['nombre' => 'Programa de recompensas', 'lema' => 'Gracias por volver. Cada visita suma.'],
            'identidad' => ['logo' => null, 'portada' => null, 'mostrar_negocio' => true],
            'tipografia' => ['titulos' => 'fraunces', 'texto' => 'inter'],
            'colores' => [
                'primario' => '#1f3a5f', 'secundario' => '#c8a25c', 'texto' => '#1c1917', 'texto_suave' => '#57534e',
                'superficie' => '#ffffff', 'borde' => '#e7e5e4',
            ],
            'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#f5f1ea', 'color2' => '#e9e2d6', 'angulo' => 180, 'imagen' => null, 'decoracion' => 'ninguna'],
            'tarjeta' => [
                'orientacion' => 'vertical', 'fondo_tipo' => 'degradado', 'color1' => '#1f3a5f', 'color2' => '#0f1e33', 'angulo' => 135,
                'imagen' => null, 'oscurecer' => 35, 'texto' => '#ffffff', 'texto_suave' => '#d6dde8', 'radio' => 24,
                'borde_ancho' => 0, 'borde_color' => '#c8a25c', 'sombra' => 'media', 'decoracion' => 'arcos', 'decoracion_opacidad' => 14,
            ],
            'progreso' => [
                'presentacion' => 'sellos', 'icono' => 'estrella',
                'completo_fondo' => '#c8a25c', 'completo_icono' => '#1f3a5f',
                'pendiente_fondo' => '#ffffff', 'pendiente_icono' => '#ffffff', 'pendiente_estilo' => 'contorno',
                'mostrar_contador' => true, 'mostrar_barra' => false, 'barra_color' => '#c8a25c', 'barra_fondo' => '#ffffff',
            ],
            'contenido' => [
                'saludo' => 'alias', 'mostrar_campana' => true, 'mostrar_recompensa' => true,
                'mensaje_progreso' => 'Te faltan {faltan} visitas para tu recompensa.',
                'mensaje_meta' => '¡Lo lograste! Tu recompensa te espera: pídela en tu próxima visita.',
                'mensaje_canje' => '¡Disfrútala! Ya empezaste a sumar para la siguiente.',
                'secciones' => [],
                'terminos' => '',
            ],
        ];
    }

    /**
     * Sanea un documento recibido. Devuelve el documento limpio y avisos
     * comprensibles de lo que se corrigió (para mostrarlos en el editor).
     *
     * @param  array<string, mixed>|null  $base
     * @return array{documento: array<string, mixed>, avisos: list<string>}
     */
    public function sanear(mixed $entrada, ?Negocio $negocio, ?array $base = null): array
    {
        $base ??= self::predeterminado();
        $entrada = is_array($entrada) ? $entrada : [];
        $avisos = [];
        $documento = $base;
        $documento['esquema'] = self::ESQUEMA;

        foreach (self::ESPECIFICACION as $ruta => $regla) {
            $existe = self::existe($entrada, $ruta);
            $valor = $existe ? self::obtener($entrada, $ruta) : null;
            $predeterminado = self::obtener($base, $ruta);

            if (! $existe) {
                continue;
            }

            [$limpio, $aviso] = $this->campo($regla, $valor, $predeterminado, $negocio);
            self::asignar($documento, $ruta, $limpio);

            if ($aviso !== null) {
                $avisos[$aviso] = true;
            }
        }

        $documento['contenido']['secciones'] = $this->secciones(
            self::obtener($entrada, 'contenido.secciones') ?? $base['contenido']['secciones'] ?? [],
        );

        return ['documento' => $documento, 'avisos' => array_keys($avisos)];
    }

    /**
     * UUID de imágenes referidas por un documento.
     *
     * @param  array<string, mixed>|null  $documento
     * @return list<string>
     */
    public static function imagenes(?array $documento): array
    {
        if ($documento === null) {
            return [];
        }

        return array_values(array_unique(array_filter([
            self::obtener($documento, 'identidad.logo'),
            self::obtener($documento, 'identidad.portada'),
            self::obtener($documento, 'pagina.imagen'),
            self::obtener($documento, 'tarjeta.imagen'),
        ], 'is_string')));
    }

    /**
     * Hoja de fuentes (fonts.bunny.net, sin rastreo) para los documentos.
     *
     * @param  list<array<string, mixed>>  $documentos
     */
    public static function urlFuentes(array $documentos): ?string
    {
        $familias = [];

        foreach ($documentos as $documento) {
            foreach (['tipografia.titulos', 'tipografia.texto'] as $ruta) {
                $clave = self::obtener($documento, $ruta);

                if (is_string($clave) && isset(self::FUENTES[$clave])) {
                    $familias[$clave] = $clave.':'.self::FUENTES[$clave][1];
                }
            }
        }

        return $familias === [] ? null : 'https://fonts.bunny.net/css?family='.implode('|', $familias).'&display=swap';
    }

    // ---------------------------------------------------------------

    /**
     * @param  array<int, mixed>  $regla
     * @return array{0: mixed, 1: string|null}
     */
    private function campo(array $regla, mixed $valor, mixed $predeterminado, ?Negocio $negocio): array
    {
        return match ($regla[0]) {
            'texto' => [self::texto($valor, (int) $regla[1], false) ?? $predeterminado, null],
            'parrafo' => [self::texto($valor, (int) $regla[1], true) ?? $predeterminado, null],
            'color' => self::color($valor) !== null
                ? [self::color($valor), null]
                : [$predeterminado, 'Algunos colores no eran válidos y se restablecieron.'],
            'bool' => [is_bool($valor) ? $valor : (bool) $predeterminado, null],
            'entero' => is_numeric($valor)
                ? [max((int) $regla[1], min((int) $regla[2], (int) round((float) $valor))), null]
                : [$predeterminado, null],
            'opcion' => in_array($valor, $regla[1], true) ? [$valor, null] : [$predeterminado, 'Algunas opciones no eran válidas y se restablecieron.'],
            'fuente' => is_string($valor) && isset(self::FUENTES[$valor]) ? [$valor, null] : [$predeterminado, 'Algunas opciones no eran válidas y se restablecieron.'],
            'imagen' => $this->imagen($valor, $negocio),
            default => [$predeterminado, null],
        };
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function imagen(mixed $valor, ?Negocio $negocio): array
    {
        if ($valor === null || $valor === '') {
            return [null, null];
        }

        $archivo = is_string($valor) && $negocio !== null
            ? $this->medios->delNegocio($negocio, ArchivoPublico::MODULO_FIDELIDAD, strtolower($valor), ArchivoPublico::TIPO_IMAGEN)
            : null;

        return $archivo !== null
            ? [$archivo->uuid, null]
            : [null, 'Una imagen no está disponible para tu negocio y se quitó del diseño.'];
    }

    /**
     * @return list<array{titulo: string, elementos: list<string>}>
     */
    private function secciones(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $secciones = [];

        foreach (array_slice(array_values($valor), 0, self::MAX_SECCIONES) as $seccion) {
            if (! is_array($seccion)) {
                continue;
            }

            $titulo = self::texto($seccion['titulo'] ?? null, 60, false) ?? '';
            $elementos = [];

            foreach (array_slice(is_array($seccion['elementos'] ?? null) ? array_values($seccion['elementos']) : [], 0, self::MAX_ELEMENTOS) as $elemento) {
                $texto = self::texto($elemento, 160, false);

                if ($texto !== null && $texto !== '') {
                    $elementos[] = $texto;
                }
            }

            if ($titulo !== '' || $elementos !== []) {
                $secciones[] = ['titulo' => $titulo, 'elementos' => $elementos];
            }
        }

        return $secciones;
    }

    /**
     * Texto plano: sin etiquetas HTML, sin caracteres de control ni marcas
     * de dirección (evitan suplantaciones visuales), recortado.
     */
    public static function texto(mixed $valor, int $max, bool $multilinea): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $texto = mb_scrub((string) $valor, 'UTF-8');
        $texto = strip_tags($texto);
        $texto = str_replace(['<', '>'], '', $texto);
        $texto = (string) preg_replace('/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $texto);
        $texto = $multilinea
            ? (string) preg_replace("/\n{3,}/", "\n\n", (string) preg_replace('/[ \t]+/', ' ', str_replace("\r", '', $texto)))
            : (string) preg_replace('/\s+/u', ' ', $texto);

        return mb_substr(trim($texto), 0, $max);
    }

    public static function color(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = strtolower(trim($valor));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $valor, $m) === 1) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $valor) === 1 ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function existe(array $datos, string $ruta): bool
    {
        $nodo = $datos;

        foreach (explode('.', $ruta) as $parte) {
            if (! is_array($nodo) || ! array_key_exists($parte, $nodo)) {
                return false;
            }

            $nodo = $nodo[$parte];
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function obtener(array $datos, string $ruta): mixed
    {
        $nodo = $datos;

        foreach (explode('.', $ruta) as $parte) {
            if (! is_array($nodo) || ! array_key_exists($parte, $nodo)) {
                return null;
            }

            $nodo = $nodo[$parte];
        }

        return $nodo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function asignar(array &$datos, string $ruta, mixed $valor): void
    {
        $partes = explode('.', $ruta);
        $ultima = array_pop($partes);
        $nodo = &$datos;

        foreach ($partes as $parte) {
            if (! isset($nodo[$parte]) || ! is_array($nodo[$parte])) {
                $nodo[$parte] = [];
            }

            $nodo = &$nodo[$parte];
        }

        $nodo[$ultima] = $valor;
    }
}
