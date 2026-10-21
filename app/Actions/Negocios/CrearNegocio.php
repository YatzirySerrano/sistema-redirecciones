<?php

namespace App\Actions\Negocios;

use App\Actions\Usuarios\CrearUsuario;
use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Negocio;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta un negocio y, opcionalmente, el acceso de su usuario principal.
 */
class CrearNegocio
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly CrearUsuario $crearUsuario,
    ) {}

    /**
     * @param  array{nombre_comercial: string, responsable?: string|null, email_contacto?: string|null, telefono?: string|null, notas_internas?: string|null, estado?: string}  $datos
     * @param  array{name: string, email: string, password?: string|null}|null  $acceso
     */
    public function ejecutar(array $datos, ?array $acceso = null): Negocio
    {
        return DB::transaction(function () use ($datos, $acceso) {
            $negocio = new Negocio($datos);
            $estado = EstadoCuenta::from($datos['estado'] ?? EstadoCuenta::Activo->value);
            $negocio->estado = $estado;
            $negocio->suspendido_en = $estado === EstadoCuenta::Suspendido ? now() : null;
            $negocio->save();

            $this->auditoria->registrar(
                accion: 'negocio.creado',
                descripcion: "Alta del negocio «{$negocio->nombre_comercial}»",
                recurso: $negocio,
                negocio: $negocio,
                nuevos: $negocio->only(['nombre_comercial', 'responsable', 'email_contacto', 'telefono', 'estado']),
            );

            if ($acceso !== null) {
                $this->crearUsuario->ejecutar(RolUsuario::Cliente, $acceso, $negocio);
            }

            return $negocio;
        });
    }
}
