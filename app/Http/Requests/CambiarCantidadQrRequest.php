<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Rules\UrlDestinoSegura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Cambio del número de QR (1 o 2), compartido por administrador y cliente.
 * La política (ProductoPolicy::cambiarCantidadQr) decide quién puede.
 */
class CambiarCantidadQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Producto $producto */
        $producto = $this->route('producto');

        Gate::authorize('cambiarCantidadQr', $producto);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cantidad_qr' => ['required', 'integer', 'in:1,2'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'url_destino_qr2' => ['exclude_unless:cantidad_qr,2', 'nullable', 'string', new UrlDestinoSegura],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cantidad_qr' => 'número de QR',
            'url_destino_qr2' => 'destino del QR 2',
        ];
    }
}
