<?php

namespace App\Http\Requests\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las solicitudes del panel de DASTI. Las rutas ya exigen el ámbito
 * de plataforma y su permiso; aquí se comprueba de nuevo como defensa en
 * profundidad (y el permiso propio de la solicitud, si lo define).
 */
abstract class SolicitudAdministrativa extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if ($usuario === null || ! $usuario->can('administrar-plataforma')) {
            return false;
        }

        $permiso = $this->permiso();

        return $permiso === null || $usuario->can($permiso);
    }

    /**
     * Permiso dinámico que exige la solicitud (null: el de la ruta basta).
     */
    protected function permiso(): ?string
    {
        return null;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tienes permiso para realizar esta acción.');
    }
}
