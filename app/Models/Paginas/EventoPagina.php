<?php

namespace App\Models\Paginas;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Visita, clic o envío de contacto. Datos mínimos: fecha y hora, elemento,
 * subpágina, categoría de dispositivo y solo el HOST de procedencia. Nunca
 * IP, User-Agent ni URLs completas.
 *
 * @property int $id
 * @property int $pagina_id
 * @property int $negocio_id
 * @property string $tipo
 * @property string|null $bloque_id
 * @property string|null $subpagina
 * @property CarbonImmutable $ocurrido_en
 * @property CarbonImmutable $fecha
 * @property int $hora
 * @property string $dispositivo
 * @property string|null $referente
 * @property bool $es_bot
 * @property string|null $huella_diaria
 */
class EventoPagina extends Model
{
    public const VISITA = 'visita';

    public const CLIC = 'clic';

    public const CONTACTO = 'contacto';

    public $timestamps = false;

    protected $table = 'paginas_enlaces_eventos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
            'fecha' => 'date',
            'hora' => 'integer',
            'es_bot' => 'boolean',
        ];
    }
}
