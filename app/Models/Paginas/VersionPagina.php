<?php

namespace App\Models\Paginas;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copia del documento en un momento relevante (publicación, restauración,
 * plantilla o guardado importante). Se conservan las últimas versiones.
 *
 * @property int $id
 * @property int $pagina_id
 * @property int $negocio_id
 * @property string $motivo
 * @property array<string, mixed> $documento
 * @property int|null $version_borrador
 * @property int|null $autor_id
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $autor
 */
class VersionPagina extends Model
{
    public const UPDATED_AT = null;

    public const MOTIVOS = [
        'publicacion' => 'Publicación',
        'restauracion' => 'Versión restaurada',
        'antes_de_restaurar' => 'Copia antes de restaurar',
        'guardado' => 'Borrador guardado',
        'despublicacion' => 'Página despublicada',
    ];

    protected $table = 'paginas_enlaces_versiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['documento' => 'array', 'version_borrador' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
