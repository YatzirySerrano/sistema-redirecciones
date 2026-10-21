<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol->value,
            'rol_etiqueta' => $this->rol->etiqueta(),
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'negocio' => $this->whenLoaded('negocio', fn () => $this->negocio === null ? null : [
                'id' => $this->negocio->id,
                'nombre_comercial' => $this->negocio->nombre_comercial,
                'estado' => $this->negocio->estado->value,
            ]),
            'debe_cambiar_password' => $this->debe_cambiar_password,
            'two_factor' => $this->two_factor_confirmed_at !== null,
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'es_actual' => $request->user()?->is($this->resource) ?? false,
        ];
    }
}
