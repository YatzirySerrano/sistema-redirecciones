<?php

namespace App\Services\Fidelidad;

use App\Models\Fidelidad\Credencial;

/**
 * Códigos de credenciales y tokens de acceso de Fidelidad.
 *
 * - Credencial: 16 caracteres de un alfabeto de 31 símbolos sin ambiguos
 *   (≈79 bits, CSPRNG): no se pueden adivinar ni recorrer.
 * - Tokens de enlace/recuperación: 48 caracteres aleatorios; en la base
 *   solo se guarda su SHA-256.
 */
final class Codigos
{
    private const ALFABETO = '23456789abcdefghjkmnpqrstuvwxyz';

    public static function credencial(): string
    {
        do {
            $codigo = '';

            for ($i = 0; $i < 16; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
        } while (Credencial::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    public static function token(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function esCodigoValido(string $codigo): bool
    {
        return preg_match('/^['.self::ALFABETO.']{16}$/', $codigo) === 1;
    }

    public static function esTokenValido(string $token): bool
    {
        return preg_match('/^[0-9a-f]{48}$/', $token) === 1;
    }
}
