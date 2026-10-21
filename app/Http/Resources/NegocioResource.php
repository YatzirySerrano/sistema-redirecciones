<?php

namespace App\Http\Resources;

use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación administrativa de un negocio (solo panel DASTI).
 *
 * @mixin Negocio
 */
class NegocioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_comercial' => $this->nombre_comercial,
            'responsable' => $this->responsable,
            'email_contacto' => $this->email_contacto,
            'telefono' => $this->telefono,
            'notas_internas' => $this->notas_internas,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'tiene_logo' => $this->logo_path !== null,
            'logo_url' => $this->logo_path !== null
                ? route('admin.negocios.logo.show', ['negocio' => $this->id, 'v' => substr(md5($this->logo_path), 0, 8)])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'suspendido_en' => $this->suspendido_en?->toIso8601String(),
            'productos_count' => $this->whenCounted('productos'),
            'productos_qr_count' => $this->productos_qr_count,
            'productos_nfc_count' => $this->productos_nfc_count,
            'productos_activos_count' => $this->productos_activos_count,
            'usuarios_count' => $this->whenCounted('usuarios'),
            'ultima_actividad' => $this->ultima_actividad,
            'usuario_principal' => $this->whenLoaded('usuarios', function () {
                $usuario = $this->usuarios->first();

                return $usuario === null ? null : ['id' => $usuario->id, 'name' => $usuario->name, 'email' => $usuario->email];
            }),
        ];
    }
}
