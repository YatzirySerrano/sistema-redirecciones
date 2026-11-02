<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoCuenta;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarNegocioRequest extends SolicitudAdministrativa
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono' => self::normalizarTelefono($this->input('telefono')),
            'email_contacto' => $this->filled('email_contacto') ? strtolower(trim((string) $this->input('email_contacto'))) : null,
            'crear_acceso' => $this->boolean('crear_acceso'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esAlta = $this->isMethod('POST');

        $reglas = [
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'email_contacto' => ['nullable', 'email:rfc', 'max:255'],
            'telefono' => ['bail', 'nullable', 'string', 'max:30', 'not_regex:/[^0-9+()\-\s.]/', 'regex:/^\d{10}$/'],
            'notas_internas' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $esAlta) {
            return $reglas;
        }

        return [
            ...$reglas,
            'estado' => ['required', Rule::enum(EstadoCuenta::class)],
            'crear_acceso' => ['boolean'],
            'acceso_nombre' => ['exclude_unless:crear_acceso,true', 'required', 'string', 'max:255'],
            'acceso_email' => ['exclude_unless:crear_acceso,true', 'required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'acceso_metodo' => ['exclude_unless:crear_acceso,true', 'required', Rule::in(['invitacion', 'temporal'])],
            'acceso_password' => ['exclude_unless:acceso_metodo,temporal', 'exclude_unless:crear_acceso,true', 'required', 'string', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre_comercial' => 'nombre comercial',
            'email_contacto' => 'correo de contacto',
            'notas_internas' => 'notas internas',
            'acceso_nombre' => 'nombre del usuario',
            'acceso_email' => 'correo del usuario',
            'acceso_metodo' => 'método de acceso',
            'acceso_password' => 'contraseña temporal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telefono.not_regex' => 'El teléfono solo puede contener números, espacios y los signos + ( ) - .',
            'telefono.regex' => 'El teléfono debe tener 10 dígitos (por ejemplo, 55 1234 5678).',
            'acceso_email.unique' => 'Ya existe un usuario con ese correo.',
        ];
    }

    /**
     * @return array{nombre_comercial: string, responsable: string|null, email_contacto: string|null, telefono: string|null, notas_internas: string|null, estado?: string}
     */
    public function datosNegocio(): array
    {
        /** @var array{nombre_comercial: string, responsable: string|null, email_contacto: string|null, telefono: string|null, notas_internas: string|null, estado?: string} $datos */
        $datos = $this->safe()->only(['nombre_comercial', 'responsable', 'email_contacto', 'telefono', 'notas_internas', 'estado']);

        if (isset($datos['telefono'])) {
            $datos['telefono'] = self::formatearTelefono($datos['telefono']);
        }

        return $datos;
    }

    /**
     * Teléfono mexicano a 10 dígitos: descarta espacios, guiones, puntos,
     * paréntesis y el prefijo internacional +52. Si contiene otros caracteres
     * se conserva tal cual para que la validación lo rechace. Debe coincidir
     * con normalizarTelefono() de resources/js/lib/validacion.ts.
     */
    public static function normalizarTelefono(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        if (preg_match('/[^0-9+()\-\s.]/', $valor) === 1) {
            return $valor;
        }

        $digitos = (string) preg_replace('/\D+/', '', $valor);

        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            $digitos = substr($digitos, 2);
        }

        return $digitos === '' ? $valor : $digitos;
    }

    /**
     * Formato de almacenamiento y presentación: «55 1234 5678».
     */
    public static function formatearTelefono(string $digitos): string
    {
        return strlen($digitos) === 10
            ? substr($digitos, 0, 2).' '.substr($digitos, 2, 4).' '.substr($digitos, 6)
            : $digitos;
    }

    /**
     * @return array{name: string, email: string, password: string|null}|null
     */
    public function datosAcceso(): ?array
    {
        if (! $this->boolean('crear_acceso')) {
            return null;
        }

        return [
            'name' => (string) $this->validated('acceso_nombre'),
            'email' => (string) $this->validated('acceso_email'),
            'password' => $this->validated('acceso_metodo') === 'temporal' ? (string) $this->validated('acceso_password') : null,
        ];
    }
}
