<?php

namespace App\Http\Resources;

use App\Models\RegistroAuditoria;
use App\Services\Auditoria\CatalogoAcciones;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistroAuditoria
 */
class RegistroAuditoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accion' => $this->accion,
            'accion_etiqueta' => CatalogoAcciones::etiqueta($this->accion),
            'descripcion' => $this->descripcion,
            'usuario' => $this->usuario === null ? null : ['id' => $this->usuario->id, 'name' => $this->usuario->name, 'email' => $this->usuario->email],
            'negocio' => $this->negocio === null ? null : ['id' => $this->negocio->id, 'nombre_comercial' => $this->negocio->nombre_comercial],
            'recurso' => $this->auditable_type === null ? null : ['tipo' => $this->auditable_type, 'id' => $this->auditable_id],
            'valores_anteriores' => $this->valores_anteriores,
            'valores_nuevos' => $this->valores_nuevos,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
