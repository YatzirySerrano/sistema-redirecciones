<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificación (webhook) recibida de un proveedor de pagos. Sirve para la
 * idempotencia (clave única) y para revisión administrativa. No guarda el
 * contenido completo: solo tipo, transacción y resultado. El código de
 * verificación del webhook se guarda cifrado.
 *
 * @property int $id
 * @property string $proveedor
 * @property string $clave_unica
 * @property string $tipo
 * @property string|null $transaccion_id
 * @property int|null $intento_pago_id
 * @property int|null $pago_id
 * @property CarbonImmutable|null $ocurrido_en
 * @property string|null $codigo_verificacion
 * @property string|null $resultado
 * @property CarbonImmutable|null $procesado_en
 * @property CarbonImmutable|null $created_at
 */
class NotificacionPago extends Model
{
    protected $table = 'notificaciones_pago';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
            'procesado_en' => 'datetime',
            'codigo_verificacion' => 'encrypted',
        ];
    }
}
