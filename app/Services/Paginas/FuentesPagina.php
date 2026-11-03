<?php

namespace App\Services\Paginas;

/**
 * Hoja de estilos de fonts.bunny.net (sin rastreo) para las tipografías
 * elegidas. Las claves están en CatalogoPagina::FUENTES.
 */
final class FuentesPagina
{
    /**
     * @param  array<string, mixed>  $tipografia
     */
    public static function url(array $tipografia): ?string
    {
        $familias = [];
        $texto = (string) ($tipografia['fuente_texto'] ?? 'sistema');
        $titulos = (string) ($tipografia['fuente_titulos'] ?? 'sistema');
        $pesoTexto = (int) ($tipografia['peso_texto'] ?? 400);
        $pesoTitulos = (int) ($tipografia['peso_titulos'] ?? 700);

        if ($texto !== 'sistema' && in_array($texto, CatalogoPagina::FUENTES, true)) {
            $familias[$texto] = [$pesoTexto, 600, 700];
        }

        if ($titulos !== 'sistema' && in_array($titulos, CatalogoPagina::FUENTES, true)) {
            $familias[$titulos] = [...($familias[$titulos] ?? []), $pesoTitulos];
        }

        if ($familias === []) {
            return null;
        }

        $partes = [];

        foreach ($familias as $clave => $pesos) {
            $pesos = array_unique($pesos);
            sort($pesos);
            $partes[] = $clave.':'.implode(',', $pesos);
        }

        return 'https://fonts.bunny.net/css?family='.implode('|', $partes).'&display=swap';
    }
}
