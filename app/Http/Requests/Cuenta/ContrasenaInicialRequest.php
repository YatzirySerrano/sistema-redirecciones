<?php

namespace App\Http\Requests\Cuenta;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ContrasenaInicialRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Solo aplica mientras la contraseña sea temporal; para cambiarla en otro
     * momento se usa Ajustes › Seguridad.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->debe_cambiar_password;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['password.different' => 'La nueva contraseña debe ser distinta de la temporal.'];
    }
}
