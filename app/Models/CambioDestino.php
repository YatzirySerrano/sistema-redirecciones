<?php

namespace App\Models;

use App\Enums\OrigenCambio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial inmutable de destinos de un producto.
 *
 * @property int $id
 * @property int $producto_id
 * @property int $negocio_id
 * @property int|null $user_id
 * @property OrigenCambio $origen
 * @property string|null $url_anterior
 * @property string|null $url_nueva
 * @property CarbonImmutable $created_at
 * @property-read Producto $producto
 * @property-read User|null $usuario
 */
class CambioDestino extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cambios_destino';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen' => OrigenCambio::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
