<?php

namespace App\Services\Servicios;

use App\Enums\EstadoContratacion;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Servicio;

/**
 * Derecho de un negocio a usar servicios adicionales (contratación
 * comercial). Es independiente de los permisos: para operar un servicio un
 * usuario necesita el permiso Y que su negocio lo tenga contratado. Cada
 * servicio (Fidelidad, Página de enlaces) se evalúa por separado: contratar
 * uno nunca habilita otro.
 *
 * - Activa y vigente: operación completa.
 * - Restringida (disputa) o vencida: solo lectura.
 * - Suspendida, cancelada o sin contratación: sin acceso (los datos se
 *   conservan).
 */
class AccesoServicios
{
    /**
     * @var array<string, array{activo: bool, solo_lectura: bool, estado: string|null, vence_en: string|null}>
     */
    private array $cache = [];

    /**
     * @return array{activo: bool, solo_lectura: bool, estado: string|null, vence_en: string|null}
     */
    public function estado(Negocio $negocio, string $clave): array
    {
        $llave = $negocio->id.'|'.$clave;

        if (isset($this->cache[$llave])) {
            return $this->cache[$llave];
        }

        $contrataciones = Contratacion::query()
            ->where('negocio_id', $negocio->id)
            ->whereHas('servicio', fn ($q) => $q->where('clave', $clave))
            ->whereNotIn('estado', [EstadoContratacion::Pendiente, EstadoContratacion::Cancelada])
            ->latest('id')
            ->get();

        $vigente = $contrataciones->first(fn (Contratacion $c) => $c->otorgaAcceso());

        if ($vigente !== null) {
            return $this->cache[$llave] = [
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

        return $this->cache[$llave] = [
            'activo' => false,
            'solo_lectura' => in_array($estado, [EstadoContratacion::Restringida->value, EstadoContratacion::Vencida->value], true),
            'estado' => $estado,
            'vence_en' => $ultima?->vence_en?->toIso8601String(),
        ];
    }

    public function tiene(Negocio $negocio, string $clave): bool
    {
        return $this->estado($negocio, $clave)['activo'];
    }

    public function puedeOperar(Negocio $negocio, string $clave): bool
    {
        return $this->tiene($negocio, $clave) && $negocio->estaActivo();
    }

    public function puedeConsultar(Negocio $negocio, string $clave): bool
    {
        $estado = $this->estado($negocio, $clave);

        return $estado['activo'] || $estado['solo_lectura'];
    }

    /**
     * Estado de todos los servicios adicionales (navegación del panel).
     *
     * @return array<string, array{activo: bool, solo_lectura: bool, estado: string|null}>
     */
    public function resumen(Negocio $negocio): array
    {
        $resumen = [];

        foreach (Servicio::ADICIONALES as $clave) {
            $e = $this->estado($negocio, $clave);
            $resumen[$clave] = ['activo' => $e['activo'], 'solo_lectura' => $e['solo_lectura'], 'estado' => $e['estado']];
        }

        return $resumen;
    }

    // ---- Atajos de Fidelidad (usados por el módulo existente) ----

    public function tieneFidelidad(Negocio $negocio): bool
    {
        return $this->tiene($negocio, Servicio::FIDELIDAD);
    }

    public function puedeOperarFidelidad(Negocio $negocio): bool
    {
        return $this->puedeOperar($negocio, Servicio::FIDELIDAD);
    }

    public function puedeConsultarFidelidad(Negocio $negocio): bool
    {
        return $this->puedeConsultar($negocio, Servicio::FIDELIDAD);
    }

    /**
     * @return array{activo: bool, solo_lectura: bool, estado: string|null, vence_en: string|null}
     */
    public function fidelidad(Negocio $negocio): array
    {
        return $this->estado($negocio, Servicio::FIDELIDAD);
    }

    public function olvidar(): void
    {
        $this->cache = [];
    }
}
