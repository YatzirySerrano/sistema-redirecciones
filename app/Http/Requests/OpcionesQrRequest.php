<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Services\Qr\OpcionesQr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Opciones de diseño y exportación del QR (descarga, vista previa o
 * guardado).
 *
 * La autorización se resuelve antes de validar, para que un producto ajeno
 * responda siempre 404 sin importar los parámetros enviados.
 */
class OpcionesQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        $producto = $this->route('producto');
        abort_unless($producto instanceof Producto, 404);

        Gate::authorize($this->isMethod('GET') ? 'descargarQr' : 'disenarQr', $producto);

        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan los booleanos presentes: los ausentes conservan
        // el diseño guardado del producto.
        foreach (['fondo_transparente', 'incluir_logo', 'descargar'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => $this->boolean($campo)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fondo' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fondo_transparente' => ['boolean'],
            'margen' => ['sometimes', Rule::in(array_keys(OpcionesQr::MARGENES))],
            'incluir_logo' => ['boolean'],
            'formato' => ['sometimes', Rule::in(['png', 'svg'])],
            'tamano' => ['sometimes', 'integer', Rule::in(OpcionesQr::TAMANOS)],
            'descargar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'El color del código debe tener el formato #RRGGBB.',
            'fondo.regex' => 'El color de fondo debe tener el formato #RRGGBB.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $guardadas
     */
    public function opciones(?array $guardadas = null): OpcionesQr
    {
        $datos = $this->safe()->only(['color', 'fondo', 'fondo_transparente', 'margen', 'incluir_logo']);

        return OpcionesQr::desdeArreglo([...($guardadas ?? []), ...$datos]);
    }
}
