<?php

namespace App\Services\Apariencia;

/**
 * Identidad visual de DASTI en la interfaz administrativa.
 *
 * El isotipo oficial (favicon de DASTI) está en `public/marca/dasti-isotipo.png`
 * y se genera desde `public/favicon.png`. Para añadir más variantes basta con
 * copiar los archivos a `public/marca/` con estos nombres (SVG o PNG):
 *
 * - `dasti-logo.svg`           variante completa (sidebar expandido, login).
 * - `dasti-logo-oscuro.svg`    opcional, para modo oscuro.
 * - `dasti-isotipo.svg`        variante compacta (sidebar colapsado, móvil).
 * - `dasti-isotipo-oscuro.svg` opcional, para modo oscuro.
 *
 * Sin logotipo completo, la interfaz muestra el isotipo junto al nombre en
 * texto. Si faltaran todos, usa un recurso neutral (`temporal: true`).
 */
class MarcaDasti
{
    private const ARCHIVOS = [
        'logo' => 'dasti-logo',
        'logo_oscuro' => 'dasti-logo-oscuro',
        'isotipo' => 'dasti-isotipo',
        'isotipo_oscuro' => 'dasti-isotipo-oscuro',
    ];

    /**
     * @return array{logo: string|null, logo_oscuro: string|null, isotipo: string|null, isotipo_oscuro: string|null, temporal: bool}
     */
    public function datos(): array
    {
        $datos = [];

        foreach (self::ARCHIVOS as $clave => $nombre) {
            $datos[$clave] = $this->buscar($nombre);
        }

        return [...$datos, 'temporal' => $datos['logo'] === null && $datos['isotipo'] === null];
    }

    private function buscar(string $nombre): ?string
    {
        foreach (['svg', 'png', 'webp'] as $extension) {
            $ruta = public_path("marca/{$nombre}.{$extension}");

            if (is_file($ruta)) {
                return "/marca/{$nombre}.{$extension}?v=".filemtime($ruta);
            }
        }

        return null;
    }
}
