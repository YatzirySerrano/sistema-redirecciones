<?php

namespace App\Models\Paginas;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mensaje enviado por un visitante desde el formulario de contacto. Solo lo
 * ve el negocio (permiso paginas.mensajes); DASTI no lo expone.
 *
 * @property int $id
 * @property int $pagina_id
 * @property int $negocio_id
 * @property string|null $bloque_id
 * @property string $nombre
 * @property string|null $email
 * @property string|null $telefono
 * @property string $mensaje
 * @property CarbonImmutable|null $leido_en
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MensajePagina extends Model
{
    protected $table = 'paginas_enlaces_mensajes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['leido_en' => 'datetime'];
    }
}
