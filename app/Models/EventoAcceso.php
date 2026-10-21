<?php

namespace App\Models;

use App\Enums\CategoriaDispositivo;
use App\Enums\ResultadoAcceso;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceso individual a un enlace permanente. No almacena IP ni User-Agent
 * completos; solo categorías y una huella diaria irreversible.
 *
 * @property int $id
 * @property int $producto_id
 * @property int $negocio_id
 * @property CarbonImmutable $ocurrido_en
 * @property ResultadoAcceso $resultado
 * @property CategoriaDispositivo $dispositivo
 * @property string|null $navegador
 * @property string|null $sistema_operativo
 * @property bool $es_bot
 * @property string|null $huella_diaria
 */
class EventoAcceso extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_acceso';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
            'resultado' => ResultadoAcceso::class,
            'dispositivo' => CategoriaDispositivo::class,
            'es_bot' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
