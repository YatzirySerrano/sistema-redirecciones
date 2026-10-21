<?php

namespace App\Actions\Usuarios;

use App\Enums\ResultadoInvitacion;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\EnlaceInvitacion;
use Throwable;

/**
 * Envía (o reenvía) el enlace para que el usuario defina su contraseña.
 *
 * Se llama siempre fuera de transacciones (CrearUsuario lo difiere con
 * DB::afterCommit), de modo que un fallo del servidor de correo nunca revierte
 * el alta del negocio ni del usuario: el fallo queda registrado en el usuario
 * y en la auditoría, y el administrador puede reenviar el enlace.
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

        // Nueva fecha de envío: invalida cualquier enlace enviado antes.
        $usuario->forceFill(['invitacion_enviada_en' => now(), 'invitacion_aceptada_en' => null])->save();

        try {
            $usuario->notify(new InvitacionAcceso(
                url: $this->enlaces->generar($usuario),
                horasValidez: $this->enlaces->horasValidez(),
            ));
        } catch (Throwable $e) {
            report($e);

            $usuario->forceFill(['invitacion_fallida_en' => now()])->save();

            $this->auditoria->registrar(
                accion: 'usuario.invitacion_fallida',
                descripcion: "No se pudo enviar el enlace de acceso a {$usuario->email}",
                recurso: $usuario,
                negocio: $usuario->negocio_id,
                nuevos: ['error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 200)],
            );

            return ResultadoInvitacion::Fallida;
        }

        $usuario->forceFill(['invitacion_fallida_en' => null])->save();

        $this->auditoria->registrar(
            accion: 'usuario.invitacion_enviada',
            descripcion: "Envío de enlace de acceso a {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
        );

        return ResultadoInvitacion::Enviada;
    }
}
