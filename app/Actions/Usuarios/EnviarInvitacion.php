<?php

namespace App\Actions\Usuarios;

use App\Enums\ResultadoInvitacion;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\EnlaceInvitacion;
use Throwable;

/**
 * Envía (en cola) el enlace de acceso de un usuario. Cada envío invalida los
 * enlaces anteriores (la huella incluye la fecha de envío).
 */
class EnviarInvitacion
{
    public function __construct(
        private readonly EnlaceInvitacion $enlaces,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function ejecutar(User $usuario): ResultadoInvitacion
    {
        $usuario->loadMissing('negocio');

        if (! $usuario->puedeOperar()) {
            return ResultadoInvitacion::NoPermitida;
        }

        $usuario->forceFill([
            'invitacion_enviada_en' => now(),
            'invitacion_aceptada_en' => null,
            'invitacion_fallida_en' => null,
        ])->save();

        try {
            $usuario->notify(new InvitacionAcceso(
                url: $this->enlaces->generar($usuario),
                horasValidez: $this->enlaces->horasValidez(),
                usuarioId: $usuario->id,
                emitidaEn: $usuario->invitacion_enviada_en?->getTimestamp(),
            ));
        } catch (Throwable $e) {
            // Cola síncrona o fallo al encolar: InvitacionAcceso::failed() ya
            // pudo registrarlo; registrarFallo() no lo duplica.
            report($e);
            $this->registrarFallo($usuario, $e);

            return ResultadoInvitacion::Fallida;
        }

        $this->auditoria->registrar(
            accion: 'usuario.invitacion_enviada',
            descripcion: "Envío de enlace de acceso a {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
        );

        return ResultadoInvitacion::Enviada;
    }

    /**
     * Marca la invitación vigente como fallida y lo audita una sola vez.
     */
    public function registrarFallo(User $usuario, Throwable $error): void
    {
        $usuario->refresh();

        if ($usuario->invitacion_fallida_en !== null || $usuario->invitacion_aceptada_en !== null) {
            return;
        }

        $usuario->forceFill(['invitacion_fallida_en' => now()])->save();

        $this->auditoria->registrar(
            accion: 'usuario.invitacion_fallida',
            descripcion: "No se pudo enviar el enlace de acceso a {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            nuevos: ['error' => mb_substr(class_basename($error).': '.$error->getMessage(), 0, 200)],
        );
    }
}
