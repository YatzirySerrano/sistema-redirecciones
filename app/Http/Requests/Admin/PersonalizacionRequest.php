<?php

namespace App\Http\Requests\Admin;

use App\Services\Apariencia\TemaVisual;
use Illuminate\Validation\Validator;

/**
 * Colores de la interfaz: formato #rrggbb y combinaciones legibles (las
 * mismas comprobaciones de contraste que muestra la vista previa).
 */
class PersonalizacionRequest extends SolicitudAdministrativa
{
    protected function prepareForValidation(): void
    {
        $datos = [];

        foreach (array_keys(TemaVisual::PREDETERMINADOS) as $campo) {
            $valor = $this->input($campo);
            $datos[$campo] = is_string($valor) ? strtolower(trim($valor)) : $valor;
        }

        $this->merge($datos);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (array_keys(TemaVisual::PREDETERMINADOS) as $campo) {
            $reglas[$campo] = ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'];
        }

        return $reglas;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, string> $colores */
            $colores = $validator->validated();

            foreach (TemaVisual::problemasDeContraste($colores) as $campo => $mensaje) {
                $validator->errors()->add($campo, $mensaje);
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(fn (array $c) => mb_strtolower($c['etiqueta']), TemaVisual::CAMPOS);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.regex' => 'Elige un color válido (formato #RRGGBB).'];
    }
}
