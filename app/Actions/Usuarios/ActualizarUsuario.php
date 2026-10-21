<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Str;

/**
 * Actualiza nombre y correo de un usuario (operación administrativa).
 */
class ActualizarUsuario
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @param  array{name: string, email: string}  $datos
     */
    public function ejecutar(User $usuario, array $datos): User
    {
        $usuario->fill(['name' => $datos['name'], 'email' => Str::lower($datos['email'])]);

        if (! $usuario->isDirty()) {
            return $usuario;
        }

        $cambios = array_keys($usuario->getDirty());
        $anteriores = array_intersect_key($usuario->getOriginal(), array_flip($cambios));
        $usuario->save();

        $this->auditoria->registrar(
            accion: 'usuario.actualizado',
            descripcion: "Actualización de datos de {$usuario->name}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            anteriores: $anteriores,
            nuevos: $usuario->only($cambios),
        );

        return $usuario;
    }
}
