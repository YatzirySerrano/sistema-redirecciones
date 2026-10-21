<?php

namespace App\Http\Resources;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Producto
 */
class ProductoResource extends JsonResource
{
    /**
     * Los campos internos de DASTI (referencia comercial, notas, estado de
     * preparación) solo se envían al administrador.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $usuario */
        $usuario = $request->user();
        $esAdmin = $usuario?->esAdministrador() ?? false;

        $datos = [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'url_permanente' => $this->url_permanente,
            'url_destino' => $this->url_destino,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'total_accesos' => $this->total_accesos,
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->toIso8601String(),
            'destino_actualizado_en' => $this->destino_actualizado_en?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'negocio' => $this->whenLoaded('negocio', fn () => [
                'id' => $this->negocio->id,
                'nombre_comercial' => $this->negocio->nombre_comercial,
                'estado' => $this->negocio->estado->value,
                'tiene_logo' => $this->negocio->logo_path !== null,
            ]),
            'permisos' => [
                'cambiar_destino' => $usuario?->can('cambiarDestino', $this->resource) ?? false,
                'descargar_qr' => $usuario?->can('descargarQr', $this->resource) ?? false,
            ],
        ];

        if (! $esAdmin) {
            return $datos;
        }

        return [
            ...$datos,
            'estado_preparacion' => $this->estado_preparacion->value,
            'estado_preparacion_etiqueta' => $this->estado_preparacion->etiqueta(),
            'fecha_entrega' => $this->fecha_entrega?->toDateString(),
            'referencia_comercial' => $this->referencia_comercial,
            'notas_internas' => $this->notas_internas,
            'suspendido_en' => $this->suspendido_en?->toIso8601String(),
        ];
    }
}
