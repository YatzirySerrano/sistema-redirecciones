<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        // Igual que en el alta de usuarios: el correo se guarda en minúsculas,
        // que es como se busca al iniciar sesión.
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * El correo es la identidad de acceso: solo el administrador de DASTI puede
     * cambiar el suyo desde aquí; el de los clientes lo gestiona DASTI.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (! $this->user()->esAdministrador()) {
            return ['name' => $this->nameRules()];
        }

        return $this->profileRules($this->user()->id);
    }
}
