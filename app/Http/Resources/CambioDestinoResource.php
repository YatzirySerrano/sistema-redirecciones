<?php

namespace App\Http\Resources;

use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CambioDestino
 */
class CambioDestinoResource extends JsonResource
{
    /**
     * Para el cliente, los cambios del personal de DASTI se firman como
     * «DASTI» sin exponer el nombre del administrador.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $usuario */
        $usuario = $request->user();
        $esAdmin = $usuario?->esAdministrador() ?? false;

        $autor = match (true) {
            $this->origen === OrigenCambio::Sistema => 'Sistema',
            $this->origen === OrigenCambio::Admin && ! $esAdmin => 'DASTI',
            default => $this->usuario->name ?? 'Usuario eliminado',
        };

        return [
            'id' => $this->id,
            'url_anterior' => $this->url_anterior,
            'url_nueva' => $this->url_nueva,
            'origen' => $this->origen->value,
            'origen_etiqueta' => $this->origen->etiqueta(),
            'autor' => $autor,
            'producto' => $this->whenLoaded('producto', fn () => [
                'id' => $this->producto->id,
                'nombre' => $this->producto->nombre,
                'codigo' => $this->producto->codigo,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
