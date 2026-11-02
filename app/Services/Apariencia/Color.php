<?php

namespace App\Services\Apariencia;

/**
 * Utilidades de color (#rrggbb) para la personalización: luminancia y
 * contraste según WCAG 2.x, mezcla y aclarado.
 */
final class Color
{
    public const TEXTO_OSCURO = '#17141f';

    public const TEXTO_CLARO = '#ffffff';

    public static function esValido(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^#[0-9a-f]{6}$/', $valor) === 1;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    public static function hex(float $r, float $g, float $b): string
    {
        $c = fn (float $v) => str_pad(dechex((int) round(max(0, min(255, $v)))), 2, '0', STR_PAD_LEFT);

        return '#'.$c($r).$c($g).$c($b);
    }

    public static function luminancia(string $hex): float
    {
        $canal = function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
    }

    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Texto legible (claro u oscuro) sobre el color indicado.
     */
    public static function primerPlano(string $fondo): string
    {
        return self::contraste($fondo, self::TEXTO_CLARO) >= self::contraste($fondo, self::TEXTO_OSCURO)
            ? self::TEXTO_CLARO
            : self::TEXTO_OSCURO;
    }

    /**
     * Mezcla lineal en sRGB: $peso = proporción de $a.
     */
    public static function mezclar(string $a, string $b, float $peso): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        return self::hex($r1 * $peso + $r2 * (1 - $peso), $g1 * $peso + $g2 * (1 - $peso), $b1 * $peso + $b2 * (1 - $peso));
    }

    /**
     * Aclara (hacia blanco) u oscurece (hacia negro) el color hasta alcanzar
     * el contraste pedido frente a $fondo, sin cambiar su tono.
     */
    public static function ajustarContraste(string $color, string $fondo, float $minimo): string
    {
        if (self::contraste($color, $fondo) >= $minimo) {
            return $color;
        }

        $destino = self::luminancia($fondo) < 0.5 ? '#ffffff' : '#000000';

        for ($paso = 0.05; $paso <= 1.0; $paso += 0.05) {
            $candidato = self::mezclar($destino, $color, $paso);

            if (self::contraste($candidato, $fondo) >= $minimo) {
                return $candidato;
            }
        }

        return $destino;
    }
}
