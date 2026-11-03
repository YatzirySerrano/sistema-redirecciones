<?php

namespace App\Services\Fidelidad;

use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Recuperacion;
use App\Notifications\Fidelidad\RenovarEnlaceFidelidad;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;

/**
 * Acceso del consumidor a su progreso, sin contraseña y de SOLO LECTURA.
 *
 * Nada de lo que hace esta clase registra visitas, modifica saldos ni
 * canjea recompensas. La vista pública (Diseno\PresentacionPublica) nunca
 * incluye teléfono ni correo.
 */
class AccesoConsumidor
{
    public function __construct(
        private readonly GestorConsumidores $consumidores,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function porToken(string $token): ?Consumidor
    {
        if (! Codigos::esTokenValido($token)) {
            return null;
        }

        $consumidor = Consumidor::query()->where('token_acceso_hash', Codigos::hash($token))->first();

        return $consumidor?->estaActivo() ? $consumidor : null;
    }

    public function porCredencial(string $codigo): ?Credencial
    {
        if (! Codigos::esCodigoValido(strtolower($codigo))) {
            return null;
        }

        return Credencial::query()->with('consumidor.negocio')->where('codigo', strtolower($codigo))->first();
    }

    /**
     * Solicitud de recuperación por correo. Responde igual exista o no la
     * cuenta: solo se envía a correos VERIFICADOS (uno por negocio).
     */
    public function solicitarRecuperacion(string $email): void
    {
        $consumidores = Consumidor::query()->with('negocio')
            ->where('email', mb_strtolower(trim($email)))
            ->whereNotNull('email_verificado_en')
            ->where('estado', Consumidor::ACTIVO)
            ->get();

        foreach ($consumidores as $consumidor) {
            $token = Codigos::token();

            DB::transaction(function () use ($consumidor, $token) {
                // Una sola solicitud viva: las anteriores dejan de servir.
                Recuperacion::query()->where('consumidor_id', $consumidor->id)
                    ->where('proposito', Recuperacion::RENOVAR)->whereNull('usado_en')
                    ->update(['usado_en' => now()]);
                Recuperacion::query()->create([
                    'consumidor_id' => $consumidor->id,
                    'token_hash' => Codigos::hash($token),
                    'proposito' => Recuperacion::RENOVAR,
                    'expira_en' => now()->addMinutes(60),
                ]);
            });

            $consumidor->notify(new RenovarEnlaceFidelidad(route('fidelidad.publico.renovar', $token), $consumidor->negocio->nombre_comercial));
        }
    }

    /**
     * Consume un enlace de un solo uso. Devuelve el consumidor y, si era de
     * renovación, el token del nuevo enlace de consulta.
     *
     * @return array{consumidor: Consumidor, token: string|null}|null
     */
    public function usarEnlace(string $token, string $proposito): ?array
    {
        if (! Codigos::esTokenValido($token)) {
            return null;
        }

        return DB::transaction(function () use ($token, $proposito) {
            $recuperacion = Recuperacion::query()->where('token_hash', Codigos::hash($token))
                ->where('proposito', $proposito)->lockForUpdate()->first();

            if ($recuperacion === null || $recuperacion->usado_en !== null || $recuperacion->expira_en->isPast()) {
                return null;
            }

            $recuperacion->forceFill(['usado_en' => now()])->save();
            $consumidor = $recuperacion->consumidor;

            if (! $consumidor->estaActivo()) {
                return null;
            }

            if ($proposito === Recuperacion::VERIFICAR) {
                $consumidor->forceFill(['email_verificado_en' => now()])->save();
                $this->auditoria->registrar(
                    accion: 'fidelidad.correo_verificado',
                    descripcion: 'Correo del consumidor verificado',
                    recurso: $consumidor,
                    negocio: $consumidor->negocio_id,
                );

                return ['consumidor' => $consumidor, 'token' => null];
            }

            return [
                'consumidor' => $consumidor,
                'token' => $this->consumidores->emitirEnlaceDigital($consumidor, null, 'Recuperación por correo verificado'),
            ];
        });
    }
}
