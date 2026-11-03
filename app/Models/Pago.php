<?php

namespace App\Models;

use App\Enums\EstadoPago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Pago de un servicio. El importe y la moneda los fija el servidor; nunca se
 * guardan datos de tarjeta.
 *
 * @property int $id
 * @property string|null $folio
 * @property int $negocio_id
 * @property int $servicio_id
 * @property int|null $contratacion_id
 * @property string $proveedor
 * @property EstadoPago $estado
 * @property int $importe_centavos
 * @property string $moneda
 * @property string|null $concepto
 * @property int $reembolsado_centavos
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property bool $livemode
 * @property string|null $referencia
 * @property string|null $motivo
 * @property bool $requiere_revision
 * @property int|null $revisado_por
 * @property CarbonImmutable|null $revisado_en
 * @property CarbonImmutable|null $pagado_en
 * @property CarbonImmutable|null $ultimo_evento_en
 * @property int|null $iniciado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 * @property-read Servicio $servicio
 * @property-read Contratacion|null $contratacion
 * @property-read User|null $iniciador
 * @property-read Collection<int, IntentoPago> $intentos
 * @property-read Collection<int, AjustePago> $ajustes
 */
class Pago extends Model
{
    public const PROVEEDOR_OPENPAY = 'openpay';

    // Histórico: pagos de la pasarela anterior (solo lectura).
    public const PROVEEDOR_STRIPE = 'stripe';

    public const PROVEEDOR_MANUAL = 'manual';

    protected $table = 'pagos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPago::class,
            'importe_centavos' => 'integer',
            'reembolsado_centavos' => 'integer',
            'livemode' => 'boolean',
            'requiere_revision' => 'boolean',
            'pagado_en' => 'datetime',
            'ultimo_evento_en' => 'datetime',
            'revisado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Folio público no secuencial (no revela el volumen de ventas).
        static::creating(function (Pago $pago) {
            $pago->folio ??= 'DST-'.strtoupper(Str::random(10));
        });
    }

    /** @return HasMany<IntentoPago, $this> */
    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoPago::class);
    }

    /** @return HasMany<AjustePago, $this> */
    public function ajustes(): HasMany
    {
        return $this->hasMany(AjustePago::class);
    }

    public function etiquetaProveedor(): string
    {
        return match ($this->proveedor) {
            self::PROVEEDOR_OPENPAY => 'Openpay',
            self::PROVEEDOR_STRIPE => 'Stripe (histórico)',
            default => 'Fuera de línea',
        };
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return BelongsTo<Servicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /** @return BelongsTo<Contratacion, $this> */
    public function contratacion(): BelongsTo
    {
        return $this->belongsTo(Contratacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function iniciador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por');
    }
}
