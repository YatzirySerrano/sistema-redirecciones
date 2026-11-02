<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\MedioEnlace;
use App\Enums\ModalidadDestino;
use App\Enums\TipoProducto;
use Carbon\CarbonImmutable;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto físico vendido (QR, NFC o combinado) con su enlace permanente.
 *
 * El negocio propietario, el código, el destino y el estado no son
 * asignables masivamente: se modifican mediante acciones auditadas.
 *
 * @property int $id
 * @property int $negocio_id
 * @property int|null $producto_principal_id
 * @property string $codigo
 * @property string $nombre
 * @property TipoProducto $tipo
 * @property ModalidadDestino $modalidad_destino
 * @property MedioEnlace|null $medio
 * @property string|null $url_destino
 * @property EstadoProducto $estado
 * @property EstadoPreparacion $estado_preparacion
 * @property CarbonImmutable|null $fecha_entrega
 * @property string|null $referencia_comercial
 * @property string|null $notas_internas
 * @property array<string, mixed>|null $opciones_qr
 * @property int $total_accesos
 * @property CarbonImmutable|null $ultimo_acceso_en
 * @property CarbonImmutable|null $destino_actualizado_en
 * @property CarbonImmutable|null $suspendido_en
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $url_permanente
 * @property-read Negocio $negocio
 * @property-read Producto|null $principal
 * @property-read Collection<int, Producto> $enlacesSecundarios
 */
#[Fillable(['nombre', 'tipo', 'estado_preparacion', 'fecha_entrega', 'referencia_comercial', 'notas_internas'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected $table = 'productos';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'modalidad_destino' => 'compartido',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoProducto::class,
            'modalidad_destino' => ModalidadDestino::class,
            'medio' => MedioEnlace::class,
            'estado' => EstadoProducto::class,
            'estado_preparacion' => EstadoPreparacion::class,
            'fecha_entrega' => 'date',
            'opciones_qr' => 'array',
            'total_accesos' => 'integer',
            'ultimo_acceso_en' => 'datetime',
            'destino_actualizado_en' => 'datetime',
            'suspendido_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Negocio, $this>
     */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /**
     * Producto físico al que pertenece un enlace secundario (reverso QR de
     * un combinado con destinos independientes).
     *
     * @return BelongsTo<Producto, $this>
     */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_principal_id');
    }

    /**
     * @return HasMany<Producto, $this>
     */
    public function enlacesSecundarios(): HasMany
    {
        return $this->hasMany(Producto::class, 'producto_principal_id')->orderBy('id');
    }

    public function esEnlaceSecundario(): bool
    {
        return $this->producto_principal_id !== null;
    }

    public function tieneDestinosIndependientes(): bool
    {
        return $this->modalidad_destino === ModalidadDestino::Independiente;
    }

    /**
     * Productos físicos (excluye los enlaces secundarios, que no son
     * unidades vendidas).
     *
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopePrincipales(Builder $query): Builder
    {
        return $query->whereNull('producto_principal_id');
    }

    /**
     * @return HasMany<CambioDestino, $this>
     */
    public function cambiosDestino(): HasMany
    {
        return $this->hasMany(CambioDestino::class);
    }

    /**
     * @return HasMany<EventoAcceso, $this>
     */
    public function eventosAcceso(): HasMany
    {
        return $this->hasMany(EventoAcceso::class);
    }

    public static function construirUrlPermanente(string $codigo): string
    {
        return config('dasti.url_publica').'/'.config('dasti.prefijo_redireccion').'/'.$codigo;
    }

    public function getUrlPermanenteAttribute(): string
    {
        return self::construirUrlPermanente($this->codigo);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoProducto::Activo;
    }

    /**
     * El cliente solo puede administrar el destino mientras el producto y su
     * negocio estén activos.
     */
    public function admiteCambiosDelCliente(): bool
    {
        return $this->estaActivo() && $this->negocio->estado === EstadoCuenta::Activo;
    }

    /**
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopeDeNegocio(Builder $query, int $negocioId): Builder
    {
        return $query->where('negocio_id', $negocioId);
    }

    /**
     * Búsqueda por nombre, código y destino; la referencia comercial (dato
     * interno de DASTI) solo se incluye para el administrador.
     *
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopeBuscar(Builder $query, ?string $termino, bool $incluirInternos = true): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        $like = '%'.addcslashes($termino, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like, $incluirInternos) {
            $q->where('nombre', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('url_destino', 'like', $like)
                ->when($incluirInternos, fn (Builder $w) => $w->orWhere('referencia_comercial', 'like', $like));
        });
    }
}
