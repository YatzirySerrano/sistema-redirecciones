<?php

namespace App\Services\Servicios;

use App\Enums\EstadoContratacion;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Servicio;

/**
 * Derecho de un negocio a usar servicios adicionales (contratación
 * comercial). Es independiente de los permisos: para operar Fidelidad un
 * usuario necesita permiso Y que su negocio tenga el servicio contratado.
 *
 * - Activa y vigente: operación completa.
 * - Restringida (disputa) o vencida: solo lectura.
 * - Suspendida, cancelada o sin contratación: sin acceso (los datos se
 *   conservan).
 */
class AccesoServicios
{
    /**
     * @var array<int, array{activo: bool, solo_lectura: bool, estado: string|null, vence_en: string|null}>
     */
    private array $cache = [];

    public function tieneFidelidad(Negocio $negocio): bool
    {
        return $this->fidelidad($negocio)['activo'];
    }

    public function puedeOperarFidelidad(Negocio $negocio): bool
    {
        return $this->tieneFidelidad($negocio) && $negocio->estaActivo();
    }

    public function puedeConsultarFidelidad(Negocio $negocio): bool
    {
        $estado = $this->fidelidad($negocio);

        return $estado['activo'] || $estado['solo_lectura'];
    }

    /**
     * @return array{activo: bool, solo_lectura: bool, estado: string|null, vence_en: string|null}
     */
    public function fidelidad(Negocio $negocio): array
    {
        if (isset($this->cache[$negocio->id])) {
            return $this->cache[$negocio->id];
        }

        $contrataciones = Contratacion::query()
            ->where('negocio_id', $negocio->id)
            ->whereHas('servicio', fn ($q) => $q->where('clave', Servicio::FIDELIDAD))
            ->whereNotIn('estado', [EstadoContratacion::Pendiente, EstadoContratacion::Cancelada])
            ->latest('id')
            ->get();

        $vigente = $contrataciones->first(fn (Contratacion $c) => $c->otorgaAcceso());

        if ($vigente !== null) {
            return $this->cache[$negocio->id] = [
                'activo' => true, 'solo_lectura' => false, 'estado' => 'activa',
                'vence_en' => $vigente->vence_en?->toIso8601String(),
            ];
        }

        $ultima = $contrataciones->first();
        $estado = match (true) {
            $ultima === null => null,
            $ultima->estado === EstadoContratacion::Activa && $ultima->estaVencida() => EstadoContratacion::Vencida->value,
            default => $ultima->estado->value,
        };

        return $this->cache[$negocio->id] = [
            'activo' => false,
            'solo_lectura' => in_array($estado, [EstadoContratacion::Restringida->value, EstadoContratacion::Vencida->value], true),
            'estado' => $estado,
            'vence_en' => $ultima?->vence_en?->toIso8601String(),
        ];
    }

    public function olvidar(): void
    {
        $this->cache = [];
    }
}
