<?php

namespace App\Actions\Negocios;

use App\Models\Negocio;
use App\Services\Auditoria\RegistradorAuditoria;

/**
 * Actualiza los datos comerciales de un negocio y audita los cambios.
 */
class ActualizarNegocio
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(Negocio $negocio, array $datos): Negocio
    {
        $negocio->fill($datos);

        if (! $negocio->isDirty()) {
            return $negocio;
        }

        $cambios = array_keys($negocio->getDirty());
        $anteriores = array_intersect_key($negocio->getOriginal(), array_flip($cambios));
        $negocio->save();

        $this->auditoria->registrar(
            accion: 'negocio.actualizado',
            descripcion: "Actualización de datos de «{$negocio->nombre_comercial}»",
            recurso: $negocio,
            negocio: $negocio,
            anteriores: $anteriores,
            nuevos: $negocio->only($cambios),
        );

        return $negocio;
    }
}
