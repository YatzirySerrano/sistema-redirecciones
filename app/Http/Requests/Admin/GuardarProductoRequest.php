<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoPreparacion;
use App\Enums\ModalidadDestino;
use App\Enums\TipoProducto;
use App\Models\Negocio;
use App\Models\Producto;
use App\Rules\UrlDestinoSegura;
use Illuminate\Validation\Rule;

class GuardarProductoRequest extends SolicitudAdministrativa
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esAlta = $this->isMethod('POST');

        $reglas = [
            'nombre' => ['required', 'string', 'max:110'],
            'tipo' => ['required', Rule::enum(TipoProducto::class)],
            'estado_preparacion' => ['required', Rule::enum(EstadoPreparacion::class)],
            'fecha_entrega' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'referencia_comercial' => ['nullable', 'string', 'max:80'],
            'notas_internas' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $esAlta) {
            /** @var Producto|null $producto */
            $producto = $this->route('producto');

            // Un producto con destinos independientes (NFC + QR, o 2 QR)
            // tiene varios enlaces físicos: no puede convertirse en otro tipo.
            if ($producto?->tieneDestinosIndependientes() || $producto?->cantidadQr() === 2) {
                $reglas['tipo'][] = Rule::in([$producto->tipo->value]);
            }

            return $reglas;
        }

        return [
            ...$reglas,
            'negocio_id' => ['required', 'integer', Rule::exists(Negocio::class, 'id')],
            'modalidad_destino' => ['exclude_unless:tipo,combinado', 'nullable', Rule::enum(ModalidadDestino::class)], // sin valor: compartido
            'url_destino' => ['nullable', 'string', new UrlDestinoSegura],
            'url_destino_qr' => ['exclude_unless:modalidad_destino,independiente', 'nullable', 'string', new UrlDestinoSegura],
            // Número de QR impresos (QR y combinados); sin valor: 1.
            'cantidad_qr' => ['exclude_if:tipo,nfc', 'nullable', 'integer', 'in:1,2'],
            'url_destino_qr2' => ['exclude_unless:cantidad_qr,2', 'exclude_if:tipo,nfc', 'nullable', 'string', new UrlDestinoSegura],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Dos QR implican destinos independientes: un combinado con 2 QR siempre
     * es «independiente» (NFC, QR 1 y QR 2).
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST') && $this->input('tipo') === TipoProducto::Combinado->value && (int) $this->input('cantidad_qr') === 2) {
            $this->merge(['modalidad_destino' => ModalidadDestino::Independiente->value]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['tipo.in' => 'Este producto tiene varios enlaces (destinos independientes o 2 QR); su tipo no puede cambiar.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'negocio_id' => 'negocio',
            'url_destino' => 'URL de destino',
            'url_destino_qr' => 'destino del QR',
            'cantidad_qr' => 'número de QR',
            'url_destino_qr2' => 'destino del QR 2',
            'modalidad_destino' => 'modalidad de destino',
            'estado_preparacion' => 'estado de preparación',
            'fecha_entrega' => 'fecha de entrega',
            'referencia_comercial' => 'referencia comercial',
            'notas_internas' => 'notas internas',
        ];
    }
}
