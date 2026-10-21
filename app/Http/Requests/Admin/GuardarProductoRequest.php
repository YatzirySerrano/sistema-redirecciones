<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoPreparacion;
use App\Enums\TipoProducto;
use App\Models\Negocio;
use App\Rules\UrlDestinoSegura;
use Illuminate\Validation\Rule;

class GuardarProductoRequest extends SolicitudAdministrativa
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esAlta = $this->isMethod('POST');

        $reglas = [
            'nombre' => ['required', 'string', 'max:110'],
            'tipo' => ['required', Rule::enum(TipoProducto::class)],
            'estado_preparacion' => ['required', Rule::enum(EstadoPreparacion::class)],
            'fecha_entrega' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'referencia_comercial' => ['nullable', 'string', 'max:80'],
            'notas_internas' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $esAlta) {
            return $reglas;
        }

        return [
            ...$reglas,
            'negocio_id' => ['required', 'integer', Rule::exists(Negocio::class, 'id')],
            'url_destino' => ['nullable', 'string', new UrlDestinoSegura],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'negocio_id' => 'negocio',
            'url_destino' => 'URL de destino',
            'estado_preparacion' => 'estado de preparación',
            'fecha_entrega' => 'fecha de entrega',
            'referencia_comercial' => 'referencia comercial',
            'notas_internas' => 'notas internas',
        ];
    }
}
