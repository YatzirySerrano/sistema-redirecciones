<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las solicitudes del panel de DASTI. Las rutas ya exigen el gate
 * "administrar-plataforma"; se comprueba de nuevo como defensa en
 * profundidad.
 */
abstract class SolicitudAdministrativa extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-plataforma') ?? false;
    }
}
