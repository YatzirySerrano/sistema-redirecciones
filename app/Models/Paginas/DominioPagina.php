<?php

namespace App\Models\Paginas;

use App\Enums\EstadoDominio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dominio personalizado de una página. El token se publica en un registro
 * TXT (_dasti-verificacion.<host>) para demostrar la propiedad.
 *
 * @property int $id
 * @property int $pagina_id
 * @property int $negocio_id
 * @property string $host
 * @property string $host_unicode
 * @property string|null $host_verificado
 * @property EstadoDominio $estado
 * @property string $token
 * @property bool $es_principal
 * @property int|null $reemplaza_a_id
 * @property CarbonImmutable|null $dns_verificado_en
 * @property CarbonImmutable|null $https_verificado_en
 * @property CarbonImmutable|null $ultima_revision_en
 * @property CarbonImmutable|null $proxima_revision_en
 * @property string|null $ultimo_error
 * @property int $fallos_consecutivos
 * @property array<string, mixed>|null $diagnostico
 * @property int|null $creado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PaginaEnlaces $pagina
 */
class DominioPagina extends Model
{
    protected $table = 'paginas_enlaces_dominios';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDominio::class,
            'es_principal' => 'boolean',
            'dns_verificado_en' => 'datetime',
            'https_verificado_en' => 'datetime',
            'ultima_revision_en' => 'datetime',
            'proxima_revision_en' => 'datetime',
            'fallos_consecutivos' => 'integer',
            'diagnostico' => 'array',
        ];
    }

    /** @return BelongsTo<PaginaEnlaces, $this> */
    public function pagina(): BelongsTo
    {
        return $this->belongsTo(PaginaEnlaces::class, 'pagina_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoDominio::Activo;
    }

    public function nombreRegistroTxt(): string
    {
        return config('dasti.paginas.dominios.registro_txt', '_dasti-verificacion').'.'.$this->host;
    }
}
