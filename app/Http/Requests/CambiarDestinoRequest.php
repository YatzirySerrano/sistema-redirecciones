<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Rules\UrlDestinoSegura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Cambio de destino, compartido por administrador y cliente. La política
 * decide quién puede hacerlo; el cliente siempre debe indicar un destino.
 */
class CambiarDestinoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Producto $producto */
        $producto = $this->route('producto');

        Gate::authorize('cambiarDestino', $producto);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $obligatorio = $this->user()?->esAdministrador() ? 'nullable' : 'required';

        return [
            'url_destino' => [$obligatorio, 'string', new UrlDestinoSegura],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['url_destino' => 'URL de destino'];
    }
}
