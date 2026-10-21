<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends SolicitudAdministrativa
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')->ignore($usuario?->id)],
        ];

        if ($usuario !== null) {
            return $reglas;
        }

        return [
            ...$reglas,
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'negocio_id' => ['exclude_unless:rol,cliente', 'required', 'integer', Rule::exists(Negocio::class, 'id')->where('estado', EstadoCuenta::Activo->value)],
            'metodo' => ['required', Rule::in(['invitacion', 'temporal'])],
            'password' => ['exclude_unless:metodo,temporal', 'required', 'string', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'negocio_id' => 'negocio',
            'metodo' => 'método de acceso',
            'password' => 'contraseña temporal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'negocio_id.exists' => 'Selecciona un negocio activo.',
        ];
    }
}
