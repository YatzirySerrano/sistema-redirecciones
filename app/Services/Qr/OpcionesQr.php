<?php

namespace App\Services\Qr;

/**
 * Opciones de diseño de un QR, normalizadas y con valores seguros.
 */
final readonly class OpcionesQr
{
    public const MARGENES = ['ninguno' => 0.0, 'reducido' => 0.04, 'estandar' => 0.08, 'amplio' => 0.12];

    public const TAMANOS = [256, 512, 1024, 2048];

    public const CONTRASTE_MINIMO = 4.0;

    public function __construct(
        public string $color = '#000000',
        public string $fondo = '#FFFFFF',
        public bool $fondoTransparente = false,
        public string $margen = 'estandar',
        public bool $incluirLogo = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $datos
     */
    public static function desdeArreglo(?array $datos): self
    {
        $datos ??= [];

        $margen = (string) ($datos['margen'] ?? 'estandar');

        return new self(
            color: self::hexValido($datos['color'] ?? null) ?? '#000000',
            fondo: self::hexValido($datos['fondo'] ?? null) ?? '#FFFFFF',
            fondoTransparente: (bool) ($datos['fondo_transparente'] ?? false),
            margen: array_key_exists($margen, self::MARGENES) ? $margen : 'estandar',
            incluirLogo: (bool) ($datos['incluir_logo'] ?? false),
        );
    }

    /**
     * @return array{color: string, fondo: string, fondo_transparente: bool, margen: string, incluir_logo: bool}
     */
    public function aArreglo(): array
    {
        return [
            'color' => $this->color,
            'fondo' => $this->fondo,
            'fondo_transparente' => $this->fondoTransparente,
            'margen' => $this->margen,
            'incluir_logo' => $this->incluirLogo,
        ];
    }

    /**
     * Relación de contraste WCAG entre el color del código y el fondo.
     */
    public function contraste(): float
    {
        $l1 = self::luminancia($this->color);
        $l2 = self::luminancia($this->fondo);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * Los lectores esperan módulos oscuros sobre fondo claro con contraste
     * suficiente; los códigos invertidos o de bajo contraste fallan en
     * muchos dispositivos.
     */
    public function esLegible(): bool
    {
        if ($this->fondoTransparente) {
            return self::luminancia($this->color) <= 0.2;
        }

        return self::luminancia($this->color) < self::luminancia($this->fondo)
            && $this->contraste() >= self::CONTRASTE_MINIMO;
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        /** @var array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>} $rgb */
        $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return $rgb;
    }

    private static function luminancia(string $hex): float
    {
        $canales = array_map(function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
    }

    private static function hexValido(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) ? strtoupper($valor) : null;
    }
}
