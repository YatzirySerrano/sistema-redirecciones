<?php

namespace App\Actions\Negocios;

use App\Enums\EstadoCuenta;
use App\Models\Negocio;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * Suspende o reactiva un negocio. Mientras está suspendido, sus enlaces
 * muestran la página de «no disponible» y sus usuarios no pueden operar.
 */
class CambiarEstadoNegocio
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function ejecutar(Negocio $negocio, EstadoCuenta $estado, ?string $motivo = null): Negocio
    {
        if ($negocio->estado === $estado) {
            return $negocio;
        }

        $anterior = $negocio->estado;
        $negocio->estado = $estado;
        $negocio->suspendido_en = $estado === EstadoCuenta::Suspendido ? now() : null;
        $negocio->save();

        $suspendido = $estado === EstadoCuenta::Suspendido;

        $this->auditoria->registrar(
            accion: $suspendido ? 'negocio.suspendido' : 'negocio.reactivado',
            descripcion: ($suspendido ? 'Suspensión' : 'Reactivación')." del negocio «{$negocio->nombre_comercial}»",
            recurso: $negocio,
            negocio: $negocio,
            anteriores: ['estado' => $anterior],
            nuevos: array_filter(['estado' => $estado, 'motivo' => $motivo]),
        );

        return $negocio;
    }
}
