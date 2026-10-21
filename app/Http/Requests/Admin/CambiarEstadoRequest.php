<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoCuenta;
use Illuminate\Validation\Rule;

/**
 * Suspensión/reactivación de negocios, usuarios y productos (los tres usan
 * los valores activo/suspendido).
 */
class CambiarEstadoRequest extends SolicitudAdministrativa
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoCuenta::class)],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
