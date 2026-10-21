<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste clave/valor de la plataforma. Se accede a través de
 * App\Services\Configuracion\AjustesPlataforma.
 *
 * @property int $id
 * @property string $clave
 * @property mixed $valor
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'json',
        ];
    }
}
