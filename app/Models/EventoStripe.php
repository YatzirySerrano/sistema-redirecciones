<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento de webhook de Stripe ya procesado (idempotencia). No guarda el
 * contenido del evento.
 *
 * @property int $id
 * @property string $stripe_event_id
 * @property string $tipo
 * @property CarbonImmutable|null $creado_stripe_en
 * @property int|null $pago_id
 * @property string|null $resultado
 * @property CarbonImmutable|null $procesado_en
 */
class EventoStripe extends Model
{
    protected $table = 'eventos_stripe';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['creado_stripe_en' => 'datetime', 'procesado_en' => 'datetime'];
    }
}
