<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoCuenta;
use Illuminate\Validation\Rule;

/**
 * Suspensión/reactivación de negocios, usuarios y productos (los tres usan
 * los valores activo/suspendido).
 */
class CambiarEstadoRequest extends SolicitudAdministrativa
{
    /**
     * Suspender y reactivar son permisos distintos en negocios y productos;
     * en usuarios ambos forman parte de «gestionar accesos».
     */
    protected function permiso(): string
    {
        $suspender = $this->input('estado') === EstadoCuenta::Suspendido->value;

        return match (true) {
            $this->routeIs('admin.negocios.estado') => $suspender ? 'negocios.suspender' : 'negocios.reactivar',
            $this->routeIs('admin.productos.estado') => $suspender ? 'productos.suspender' : 'productos.reactivar',
            default => 'usuarios.gestionar_accesos',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoCuenta::class)],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
