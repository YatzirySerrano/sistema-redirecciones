<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rules\Password;

class ContrasenaTemporalRequest extends SolicitudAdministrativa
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'contraseña temporal'];
    }
}
