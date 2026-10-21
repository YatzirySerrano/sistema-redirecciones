<?php

namespace App\Http\Requests\Admin;

use App\Models\Negocio;
use Illuminate\Validation\Rule;

class TransferirProductoRequest extends SolicitudAdministrativa
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'negocio_id' => ['required', 'integer', Rule::exists(Negocio::class, 'id')],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
            'conservar_destino' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['negocio_id' => 'negocio destino', 'motivo' => 'motivo de la transferencia'];
    }
}
