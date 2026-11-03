<?php

namespace App\Models\Paginas;

use App\Enums\EstadoPagina;
use App\Models\Negocio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Página de enlaces de un negocio (una por negocio, con subpáginas dentro
 * del documento). `borrador` y `publicado` son documentos ya validados por
 * DocumentoPagina; los visitantes solo ven `publicado`.
 *
 * @property int $id
 * @property int $negocio_id
 * @property string $slug
 * @property EstadoPagina $estado
 * @property array<string, mixed> $borrador
 * @property array<string, mixed>|null $publicado
 * @property int $version
 * @property CarbonImmutable|null $borrador_actualizado_en
 * @property int|null $borrador_actualizado_por
 * @property CarbonImmutable|null $publicado_en
 * @property int|null $publicado_por
 * @property CarbonImmutable|null $despublicado_en
 * @property CarbonImmutable|null $slug_cambiado_en
 * @property string|null $incidencia
 * @property CarbonImmutable|null $incidencia_en
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 * @property-read User|null $editor
 * @property-read Collection<int, DominioPagina> $dominios
 * @property-read Collection<int, AliasPagina> $alias
 */
class PaginaEnlaces extends Model
{
    protected $table = 'paginas_enlaces';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPagina::class,
            'borrador' => 'array',
            'publicado' => 'array',
            'version' => 'integer',
            'borrador_actualizado_en' => 'datetime',
            'publicado_en' => 'datetime',
            'despublicado_en' => 'datetime',
            'slug_cambiado_en' => 'datetime',
            'incidencia_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrador_actualizado_por');
    }

    /** @return HasMany<DominioPagina, $this> */
    public function dominios(): HasMany
    {
        return $this->hasMany(DominioPagina::class, 'pagina_id');
    }

    /** @return HasMany<AliasPagina, $this> */
    public function alias(): HasMany
    {
        return $this->hasMany(AliasPagina::class, 'pagina_id');
    }

    /** @return HasMany<VersionPagina, $this> */
    public function versiones(): HasMany
    {
        return $this->hasMany(VersionPagina::class, 'pagina_id');
    }

    public function estaPublicada(): bool
    {
        return $this->estado === EstadoPagina::Publicada && $this->publicado !== null;
    }

    /**
     * Ruta pública relativa en el dominio de DASTI: /l/{slug}.
     */
    public function rutaPublica(): string
    {
        return '/'.trim((string) config('dasti.paginas.prefijo', 'l'), '/').'/'.$this->slug;
    }

    /**
     * Dirección pública permanente en el dominio de DASTI (siempre
     * funciona como respaldo aunque exista un dominio propio).
     */
    public function urlPublica(): string
    {
        return config('dasti.url_publica').$this->rutaPublica();
    }

    /**
     * Dominio principal operativo (HTTPS verificado), si existe.
     */
    public function dominioPrincipal(): ?DominioPagina
    {
        $dominios = $this->relationLoaded('dominios')
            ? $this->dominios
            : $this->dominios()->get();

        $activos = $dominios->filter(fn (DominioPagina $d) => $d->estaActivo());

        return $activos->firstWhere('es_principal', true) ?? $activos->first();
    }

    /**
     * URL canónica: el dominio principal activo o la dirección de DASTI.
     */
    public function urlCanonica(): string
    {
        $dominio = $this->dominioPrincipal();

        return $dominio !== null ? 'https://'.$dominio->host.'/' : $this->urlPublica();
    }
}
