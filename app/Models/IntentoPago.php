<?php

namespace App\Models;

use App\Enums\EstadoIntentoPago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de cobro: un cargo creado en el proveedor para una orden de pago.
 * Solo guarda identificadores y datos no sensibles (marca, tipo y los
 * últimos 4 dígitos que devuelve el proveedor); nunca el número completo ni
 * el código de seguridad.
 *
 * @property int $id
 * @property int $pago_id
 * @property string $proveedor
 * @property string $orden_externa
 * @property string|null $transaccion_id
 * @property EstadoIntentoPago $estado
 * @property string|null $estado_externo
 * @property int $importe_centavos
 * @property string $moneda
 * @property bool $livemode
 * @property string|null $url_pago
 * @property string|null $codigo_error
 * @property string|null $mensaje_error
 * @property string|null $autorizacion
 * @property string|null $marca_tarjeta
 * @property string|null $tipo_tarjeta
 * @property string|null $terminacion_tarjeta
 * @property CarbonImmutable|null $consultado_en
 * @property CarbonImmutable|null $completado_en
 * @property int|null $iniciado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Pago $pago
 */
class IntentoPago extends Model
{
    protected $table = 'intentos_pago';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoIntentoPago::class,
            'importe_centavos' => 'integer',
            'livemode' => 'boolean',
            'consultado_en' => 'datetime',
            'completado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
