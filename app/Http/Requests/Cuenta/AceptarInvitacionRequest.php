<?php

namespace App\Http\Requests\Cuenta;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contraseña definida por el usuario al aceptar su invitación. La validez
 * del enlace se comprueba en el controlador (firma, caducidad y uso único).
 */
class AceptarInvitacionRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => $this->passwordRules()];
    }
}
