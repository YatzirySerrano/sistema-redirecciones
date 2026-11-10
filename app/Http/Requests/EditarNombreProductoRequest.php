<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Nombre descriptivo de un producto (clientes con «productos.editar_nombre»).
 * Solo valida «nombre»; el controlador usa únicamente ese campo validado,
 * así que cualquier otro dato enviado se ignora.
 */
class EditarNombreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Producto $producto */
        $producto = $this->route('producto');

        Gate::authorize('editarNombre', $producto);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Mismo límite que el alta y la edición administrativa. Sin unicidad:
        // un negocio puede tener dos productos con el mismo nombre.
        return [
            'nombre' => ['required', 'string', 'max:110'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre'];
    }
}
