<?php

namespace App\Models\Paginas;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección anterior de una página: redirige (301) a la actual y queda
 * reservada para que nadie más la ocupe (anti suplantación).
 *
 * @property int $id
 * @property int $pagina_id
 * @property int $negocio_id
 * @property string $slug
 * @property CarbonImmutable $retirado_en
 * @property-read PaginaEnlaces $pagina
 */
class AliasPagina extends Model
{
    protected $table = 'paginas_enlaces_alias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['retirado_en' => 'datetime'];
    }

    /** @return BelongsTo<PaginaEnlaces, $this> */
    public function pagina(): BelongsTo
    {
        return $this->belongsTo(PaginaEnlaces::class, 'pagina_id');
    }
}
