<?php

namespace App\Services\Apariencia;

use App\Models\Configuracion;
use App\Services\Configuracion\AjustesPlataforma;

/**
 * Colores de la interfaz administrativa, editables desde «Apariencia y
 * personalización» y guardados en MariaDB (tabla `configuraciones`, clave
 * `tema_visual`).
 *
 * Se aplican como variables CSS (las mismas que usan Tailwind y
 * shadcn-vue), así que cambiar los colores no requiere recompilar Vite. Sin
 * registro guardado se usa la apariencia original (resources/css/app.css).
 *
 * - Modo claro: se aplican todos los colores.
 * - Modo oscuro: solo los colores de marca (principal, botones y enlaces),
 *   ajustados para conservar el contraste; fondos y barra lateral mantienen
 *   la paleta oscura.
 *
 * No afecta a los QR de los negocios (sus colores son independientes).
 */
class TemaVisual
{
    public const CLAVE = 'tema_visual';

    private const FONDO_OSCURO = '#0f0d17';

    private const TARJETA_CLARA = '#ffffff';

    /**
     * Apariencia original (equivalente hexadecimal de resources/css/app.css).
     *
     * @var array<string, string>
     */
    public const PREDETERMINADOS = [
        'principal' => '#6d2cdd',
        'secundario' => '#f1f0f5',
        'acento' => '#f6f0fe',
        'botones' => '#6d2cdd',
        'enlaces' => '#6d2cdd',
        'navegacion' => '#13101e',
        'fondo' => '#f9f9fb',
    ];

    /**
     * @var array<string, array{etiqueta: string, ayuda: string}>
     */
    public const CAMPOS = [
        'principal' => ['etiqueta' => 'Color principal', 'ayuda' => 'Elementos activos, selección, gráficas y foco.'],
        'secundario' => ['etiqueta' => 'Color secundario', 'ayuda' => 'Botones secundarios y superficies neutras.'],
        'acento' => ['etiqueta' => 'Color de acento', 'ayuda' => 'Resaltados suaves y fondo al pasar el cursor.'],
        'botones' => ['etiqueta' => 'Color de botones', 'ayuda' => 'Botones de acción principal.'],
        'enlaces' => ['etiqueta' => 'Color de enlaces', 'ayuda' => 'Enlaces de texto.'],
        'navegacion' => ['etiqueta' => 'Navegación lateral', 'ayuda' => 'Fondo de la barra lateral.'],
        'fondo' => ['etiqueta' => 'Color de fondo', 'ayuda' => 'Fondo general de las pantallas (solo modo claro).'],
    ];

    /**
     * @var array<string, string>|null
     */
    private ?array $guardados = null;

    private bool $leido = false;

    public function __construct(private readonly AjustesPlataforma $ajustes) {}

    /**
     * @return array<string, string>|null
     */
    public function guardados(): ?array
    {
        if ($this->leido) {
            return $this->guardados;
        }

        $valor = $this->ajustes->guardado(self::CLAVE);
        $this->leido = true;

        if (! is_array($valor)) {
            return $this->guardados = null;
        }

        $colores = [];

        foreach (array_keys(self::PREDETERMINADOS) as $campo) {
            $colores[$campo] = Color::esValido($valor[$campo] ?? null) ? (string) $valor[$campo] : self::PREDETERMINADOS[$campo];
        }

        return $this->guardados = $colores;
    }

    /**
     * @return array<string, string>
     */
    public function colores(): array
    {
        return $this->guardados() ?? self::PREDETERMINADOS;
    }

    public function personalizado(): bool
    {
        return $this->guardados() !== null;
    }

    /**
     * Variables CSS por modo, o null con la apariencia original.
     *
     * @return array{claro: array<string, string>, oscuro: array<string, string>}|null
     */
    public function tokens(): ?array
    {
        $colores = $this->guardados();

        return $colores === null ? null : self::calcularTokens($colores);
    }

    /**
     * Hoja de estilo con los colores personalizados ('' si no hay).
     */
    public function css(): string
    {
        $tokens = $this->tokens();

        return $tokens === null ? '' : self::hojaDeEstilo($tokens);
    }

    /**
     * @param  array{claro: array<string, string>, oscuro: array<string, string>}  $tokens
     */
    public static function hojaDeEstilo(array $tokens): string
    {
        $bloque = fn (array $vars) => implode('', array_map(fn ($k, $v) => "{$k}:{$v};", array_keys($vars), $vars));

        return ':root{'.$bloque($tokens['claro']).'}.dark{'.$bloque($tokens['oscuro']).'}';
    }

    /**
     * @param  array<string, string>  $c
     * @return array{claro: array<string, string>, oscuro: array<string, string>}
     */
    public static function calcularTokens(array $c): array
    {
        $navegacionTexto = Color::primerPlano($c['navegacion']);
        $navegacionClara = $navegacionTexto === Color::TEXTO_OSCURO;
        $acentoTexto = Color::contraste($c['principal'], $c['acento']) >= 4.5
            ? $c['principal']
            : Color::primerPlano($c['acento']);
        $activoNavegacion = Color::ajustarContraste($c['principal'], $c['navegacion'], 2);

        $claro = [
            '--background' => $c['fondo'],
            '--primary' => $c['principal'],
            '--primary-foreground' => Color::primerPlano($c['principal']),
            '--ring' => $c['principal'],
            '--chart-1' => $c['principal'],
            '--chart-2' => Color::mezclar($c['principal'], '#ffffff', 0.55),
            '--secondary' => $c['secundario'],
            '--secondary-foreground' => Color::primerPlano($c['secundario']),
            '--accent' => $c['acento'],
            '--accent-foreground' => $acentoTexto,
            '--boton' => $c['botones'],
            '--boton-foreground' => Color::primerPlano($c['botones']),
            '--enlace' => $c['enlaces'],
            '--sidebar-background' => $c['navegacion'],
            '--sidebar' => $c['navegacion'],
            '--sidebar-foreground' => Color::mezclar($navegacionTexto, $c['navegacion'], 0.78),
            '--sidebar-accent' => Color::mezclar($navegacionTexto, $c['navegacion'], $navegacionClara ? 0.07 : 0.1),
            '--sidebar-accent-foreground' => $navegacionTexto,
            '--sidebar-border' => Color::mezclar($navegacionTexto, $c['navegacion'], 0.1),
            '--sidebar-primary' => $activoNavegacion,
            '--sidebar-primary-foreground' => Color::primerPlano($activoNavegacion),
            '--sidebar-ring' => $activoNavegacion,
        ];

        $principalOscuro = Color::ajustarContraste($c['principal'], self::FONDO_OSCURO, 4.5);
        $botonOscuro = Color::ajustarContraste($c['botones'], self::FONDO_OSCURO, 3);

        $oscuro = [
            '--primary' => $principalOscuro,
            '--primary-foreground' => Color::primerPlano($principalOscuro),
            '--ring' => $principalOscuro,
            '--chart-1' => $principalOscuro,
            '--sidebar-primary' => $principalOscuro,
            '--sidebar-primary-foreground' => Color::primerPlano($principalOscuro),
            '--sidebar-ring' => $principalOscuro,
            '--boton' => $botonOscuro,
            '--boton-foreground' => Color::primerPlano($botonOscuro),
            '--enlace' => Color::ajustarContraste($c['enlaces'], self::FONDO_OSCURO, 4.5),
        ];

        return ['claro' => $claro, 'oscuro' => $oscuro];
    }

    /**
     * Combinaciones que harían ilegible la interfaz (mensajes por campo).
     *
     * @param  array<string, string>  $c
     * @return array<string, string>
     */
    public static function problemasDeContraste(array $c): array
    {
        $problemas = [];

        if (Color::contraste(Color::TEXTO_OSCURO, $c['fondo']) < 7) {
            $problemas['fondo'] = 'El fondo es demasiado oscuro para leer el texto. Elige un tono más claro.';
        }

        if (min(Color::contraste($c['enlaces'], $c['fondo']), Color::contraste($c['enlaces'], self::TARJETA_CLARA)) < 4.5) {
            $problemas['enlaces'] = 'Los enlaces no se distinguen bien sobre el fondo. Elige un tono más intenso.';
        }

        if (min(Color::contraste($c['principal'], $c['fondo']), Color::contraste($c['principal'], self::TARJETA_CLARA)) < 3) {
            $problemas['principal'] = 'El color principal no se distingue bien sobre el fondo. Elige un tono más intenso.';
        }

        if (Color::contraste($c['botones'], $c['fondo']) < 3 || Color::contraste($c['botones'], self::TARJETA_CLARA) < 3) {
            $problemas['botones'] = 'Los botones no se distinguen bien sobre el fondo. Elige un tono más intenso.';
        }

        if (Color::contraste($c['navegacion'], $c['fondo']) < 1.25) {
            $problemas['navegacion'] = 'La barra lateral se confunde con el fondo. Elige un tono distinto.';
        }

        return $problemas;
    }

    /**
     * @param  array<string, string>  $colores
     * @return array{anteriores: array<string, string>, nuevos: array<string, string>}
     */
    public function guardar(array $colores): array
    {
        $actuales = $this->colores();
        $nuevos = array_intersect_key($colores, self::PREDETERMINADOS) + $actuales;
        $cambios = array_diff_assoc($nuevos, $actuales);

        if ($cambios !== [] || ! $this->personalizado()) {
            Configuracion::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => $nuevos]);
            $this->olvidar();
        }

        return [
            'anteriores' => array_intersect_key($actuales, $cambios),
            'nuevos' => $cambios,
        ];
    }

    /**
     * Vuelve a la apariencia original. Devuelve los colores que había.
     *
     * @return array<string, string>|null
     */
    public function restablecer(): ?array
    {
        $anteriores = $this->guardados();
        Configuracion::query()->where('clave', self::CLAVE)->delete();
        $this->olvidar();

        return $anteriores;
    }

    private function olvidar(): void
    {
        $this->ajustes->olvidar();
        $this->guardados = null;
        $this->leido = false;
    }
}
