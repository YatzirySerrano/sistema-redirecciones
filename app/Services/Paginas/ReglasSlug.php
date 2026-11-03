<?php

namespace App\Services\Paginas;

use App\Models\Negocio;
use App\Models\Paginas\AliasPagina;
use App\Models\Paginas\PaginaEnlaces;
use Illuminate\Support\Str;

/**
 * Dirección pública de la página (/l/{slug}): formato, palabras reservadas
 * y protección contra suplantación. El slug nunca es el id numérico.
 */
class ReglasSlug
{
    public const PATRON = '/^[a-z0-9](?:[a-z0-9]|-(?!-)){1,38}[a-z0-9]$/';

    public function normalizar(string $slug): string
    {
        return strtolower(trim($slug));
    }

    /**
     * Motivo por el que no se puede usar, o null si está disponible para
     * esa página.
     */
    public function error(string $slug, ?PaginaEnlaces $propia = null): ?string
    {
        $slug = $this->normalizar($slug);

        if (strlen($slug) < 3 || strlen($slug) > 40) {
            return 'Usa entre 3 y 40 caracteres.';
        }

        if (! preg_match(self::PATRON, $slug)) {
            return 'Usa solo letras minúsculas sin acentos, números y guiones (sin espacios ni guiones al inicio o al final).';
        }

        if (ctype_digit(str_replace('-', '', $slug))) {
            return 'Incluye al menos una letra.';
        }

        if ($this->esReservado($slug)) {
            return 'Esa dirección está reservada. Elige otra.';
        }

        $ocupada = PaginaEnlaces::query()->where('slug', $slug)
            ->when($propia, fn ($q) => $q->whereKeyNot($propia->id))
            ->exists();

        $alias = AliasPagina::query()->where('slug', $slug)
            ->when($propia, fn ($q) => $q->where('pagina_id', '!=', $propia->id))
            ->exists();

        return $ocupada || $alias ? 'Esa dirección ya está en uso. Prueba con otra.' : null;
    }

    public function esReservado(string $slug): bool
    {
        return str_contains($slug, 'dasti') || in_array($slug, CatalogoPagina::SLUGS_RESERVADOS, true);
    }

    /**
     * Dirección inicial derivada del nombre del negocio.
     */
    public function sugerir(Negocio $negocio): string
    {
        $base = trim(substr(Str::slug($negocio->nombre_comercial), 0, 32), '-');
        $base = (string) preg_replace('/-{2,}/', '-', str_replace('dasti', '', $base));
        $base = trim($base, '-');

        if (strlen($base) < 3 || $this->esReservado($base) || ctype_digit(str_replace('-', '', $base))) {
            $base = 'negocio-'.strtolower(Str::random(5));
        }

        $candidato = $base;

        for ($i = 2; $this->error($candidato) !== null; $i++) {
            $candidato = $i < 6 ? "{$base}-{$i}" : $base.'-'.strtolower(Str::random(4));
        }

        return $candidato;
    }
}
