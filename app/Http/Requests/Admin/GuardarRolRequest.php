<?php

namespace App\Http\Requests\Admin;

use App\Models\Rol;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Validation\Rule;

/**
 * Alta o edición de un rol. Las reglas contra escalamiento (permisos que el
 * actor no tiene, rol propio, superadministrador) se aplican en
 * App\Actions\Roles\GuardarRol, que también se usa fuera de HTTP.
 */
class GuardarRolRequest extends SolicitudAdministrativa
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? preg_replace('/\s+/', ' ', trim($this->input('name'))) : $this->input('name'),
            'descripcion' => is_string($this->input('descripcion')) && trim($this->input('descripcion')) !== '' ? trim($this->input('descripcion')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Rol|null $rol */
        $rol = $this->route('rol');

        return [
            'name' => ['required', 'string', 'min:3', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($rol?->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'ambito' => $rol === null
                ? ['required', Rule::in([CatalogoPermisos::AMBITO_PLATAFORMA, CatalogoPermisos::AMBITO_NEGOCIO])]
                : ['prohibited'],
            'permisos' => ['present', 'array', 'max:'.count(CatalogoPermisos::todos())],
            'permisos.*' => ['string', 'distinct', Rule::in(CatalogoPermisos::todos())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del rol',
            'descripcion' => 'descripción',
            'ambito' => 'tipo de cuenta',
            'permisos' => 'permisos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'ambito.prohibited' => 'El tipo de cuenta de un rol no se puede cambiar.',
            'permisos.*.in' => 'Uno de los permisos seleccionados no existe.',
        ];
    }
}
