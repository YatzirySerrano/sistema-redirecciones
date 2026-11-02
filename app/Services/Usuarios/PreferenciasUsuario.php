<?php

namespace App\Services\Usuarios;

use App\Models\User;

/**
 * Preferencias de interfaz por usuario (columna JSON users.preferencias).
 * Por ahora: vista «tabla» o «tarjetas» de cada listado.
 */
final class PreferenciasUsuario
{
    public const MODULOS = ['negocios', 'productos', 'usuarios', 'enlaces', 'roles', 'cliente_productos'];

    public const VISTAS = ['tabla', 'tarjetas'];

    /**
     * @return array<string, string>
     */
    public static function vistas(User $usuario): array
    {
        $vistas = $usuario->preferencias['vistas'] ?? [];

        if (! is_array($vistas)) {
            return [];
        }

        return array_filter(
            array_intersect_key($vistas, array_flip(self::MODULOS)),
            fn ($vista) => in_array($vista, self::VISTAS, true),
        );
    }

    public static function guardarVista(User $usuario, string $modulo, string $vista): void
    {
        $preferencias = is_array($usuario->preferencias) ? $usuario->preferencias : [];
        $preferencias['vistas'] = [...self::vistas($usuario), $modulo => $vista];

        // Sin tocar updated_at: es una preferencia visual, no un cambio de datos.
        $usuario->timestamps = false;
        $usuario->forceFill(['preferencias' => $preferencias])->save();
        $usuario->timestamps = true;
    }
}
