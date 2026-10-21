<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use Carbon\CarbonImmutable;
use Database\Factories\NegocioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Negocio cliente de DASTI. Es la unidad de aislamiento multiempresa.
 *
 * @property int $id
 * @property string $nombre_comercial
 * @property string|null $responsable
 * @property string|null $email_contacto
 * @property string|null $telefono
 * @property string|null $notas_internas
 * @property string|null $logo_path
 * @property EstadoCuenta $estado
 * @property CarbonImmutable|null $suspendido_en
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $productos_count
 * @property-read int|null $productos_qr_count
 * @property-read int|null $productos_nfc_count
 * @property-read int|null $productos_activos_count
 * @property-read int|null $usuarios_count
 * @property-read string|null $ultima_actividad
 */
#[Fillable(['nombre_comercial', 'responsable', 'email_contacto', 'telefono', 'notas_internas'])]
class Negocio extends Model
{
    /** @use HasFactory<NegocioFactory> */
    use HasFactory;

    protected $table = 'negocios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoCuenta::class,
            'suspendido_en' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoCuenta::Activo;
    }

    /**
     * Agrega los contadores usados en listados y fichas.
     *
     * @param  Builder<Negocio>  $query
     * @return Builder<Negocio>
     */
    public function scopeConResumen(Builder $query): Builder
    {
        return $query->withCount([
            'productos',
            'usuarios',
            'productos as productos_qr_count' => fn (Builder $q) => $q->whereIn('tipo', [TipoProducto::Qr, TipoProducto::Combinado]),
            'productos as productos_nfc_count' => fn (Builder $q) => $q->whereIn('tipo', [TipoProducto::Nfc, TipoProducto::Combinado]),
            'productos as productos_activos_count' => fn (Builder $q) => $q->where('estado', EstadoProducto::Activo),
        ])->withMax('productos as ultima_actividad', 'ultimo_acceso_en');
    }
}
