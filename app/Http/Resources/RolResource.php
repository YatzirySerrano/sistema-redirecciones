<?php

namespace App\Http\Resources;

use App\Models\Rol;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rol
 */
class RolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'clave' => $this->clave,
            'descripcion' => $this->descripcion,
            'ambito' => $this->ambito,
            'ambito_etiqueta' => CatalogoPermisos::etiquetaAmbito($this->ambito),
            'del_sistema' => $this->esDelSistema(),
            'protegido' => $this->esSuperadministrador(),
            'usuarios_count' => $this->users_count,
            'permisos_count' => $this->whenLoaded('permissions', fn () => count($this->nombresPermisos()), $this->permissions_count),
            'permisos' => $this->whenLoaded('permissions', fn () => $this->nombresPermisos()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
