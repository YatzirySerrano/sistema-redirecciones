<?php

namespace App\Services\Fidelidad;

use App\Enums\TipoCredencial;
use App\Enums\TipoMovimiento;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Fidelidad\Recuperacion;
use App\Models\Negocio;
use App\Models\User;
use App\Notifications\Fidelidad\VerificarCorreoFidelidad;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Consumidores de Fidelidad: alta con consentimiento, credenciales
 * (tarjetas, QR y enlace digital), reemplazo sin perder progreso,
 * detección de duplicados y fusión controlada.
 */
class GestorConsumidores
{
    public const VERSION_AVISO = '2026-11';

    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @param  array{nombre: string, alias?: string|null, telefono?: string|null, email?: string|null}  $datos
     */
    public function registrar(Negocio $negocio, array $datos, User $actor): Consumidor
    {
        $consumidor = DB::transaction(function () use ($negocio, $datos, $actor) {
            $consumidor = Consumidor::query()->create([
                'negocio_id' => $negocio->id,
                'nombre' => $datos['nombre'],
                'alias' => $datos['alias'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'email' => $datos['email'] ?? null,
                'consentimiento_en' => now(),
                'consentimiento_version' => self::VERSION_AVISO,
                'estado' => Consumidor::ACTIVO,
                'registrado_por' => $actor->id,
            ]);

            $this->auditoria->registrar(
                accion: 'fidelidad.consumidor_registrado',
                descripcion: 'Alta de consumidor con aviso de privacidad aceptado',
                recurso: $consumidor,
                negocio: $negocio,
                nuevos: ['consentimiento_version' => self::VERSION_AVISO, 'con_telefono' => $consumidor->telefono !== null, 'con_correo' => $consumidor->email !== null],
                actor: $actor,
            );

            return $consumidor;
        });

        if ($consumidor->email !== null) {
            $this->enviarVerificacion($consumidor);
        }

        return $consumidor;
    }

    /**
     * @param  array{nombre: string, alias?: string|null, telefono?: string|null, email?: string|null}  $datos
     */
    public function actualizar(Consumidor $consumidor, array $datos, User $actor): Consumidor
    {
        $anteriores = $consumidor->only(['nombre', 'alias', 'telefono', 'email']);
        $consumidor->fill(array_intersect_key($datos, $anteriores));
        $cambioCorreo = $consumidor->isDirty('email');

        if ($cambioCorreo) {
            // Un correo nuevo debe verificarse antes de usarse para recuperar.
            $consumidor->email_verificado_en = null;
        }

        if (! $consumidor->isDirty()) {
            return $consumidor;
        }

        $cambiados = array_keys($consumidor->getDirty());
        $consumidor->save();

        $this->auditoria->registrar(
            accion: 'fidelidad.consumidor_actualizado',
            descripcion: 'Datos del consumidor actualizados',
            recurso: $consumidor,
            negocio: $consumidor->negocio_id,
            // Sin valores personales en la bitácora: solo qué campos cambiaron.
            nuevos: ['campos' => array_values(array_diff($cambiados, ['email_verificado_en']))],
            actor: $actor,
        );

        if ($cambioCorreo && $consumidor->email !== null) {
            $this->enviarVerificacion($consumidor);
        }

        return $consumidor;
    }

    public function enviarVerificacion(Consumidor $consumidor): void
    {
        if ($consumidor->email === null || $consumidor->email_verificado_en !== null) {
            return;
        }

        $token = Codigos::token();
        Recuperacion::query()->create([
            'consumidor_id' => $consumidor->id,
            'token_hash' => Codigos::hash($token),
            'proposito' => Recuperacion::VERIFICAR,
            'expira_en' => now()->addHours(48),
        ]);

        $consumidor->notify(new VerificarCorreoFidelidad(route('fidelidad.publico.verificar', $token), $consumidor->negocio->nombre_comercial));
    }

    public function emitirCredencial(Consumidor $consumidor, TipoCredencial $tipo, User $actor, ?string $motivo = null): Credencial
    {
        $this->exigirActivo($consumidor);

        $credencial = Credencial::query()->create([
            'negocio_id' => $consumidor->negocio_id,
            'consumidor_id' => $consumidor->id,
            'tipo' => $tipo,
            'codigo' => Codigos::credencial(),
            'estado' => Credencial::ACTIVA,
            'motivo' => $motivo,
            'emitida_por' => $actor->id,
        ]);

        $this->auditoria->registrar(
            accion: 'fidelidad.credencial_emitida',
            descripcion: "{$tipo->etiqueta()} emitida para un consumidor",
            recurso: $consumidor,
            negocio: $consumidor->negocio_id,
            nuevos: array_filter(['credencial_id' => $credencial->id, 'tipo' => $tipo->value, 'motivo' => $motivo]),
            actor: $actor,
        );

        return $credencial;
    }

    public function revocarCredencial(Credencial $credencial, string $motivo, User $actor): Credencial
    {
        if (! $credencial->estaActiva()) {
            return $credencial;
        }

        $credencial->forceFill(['estado' => Credencial::REVOCADA, 'revocada_en' => now(), 'motivo' => $motivo])->save();

        $this->auditoria->registrar(
            accion: 'fidelidad.credencial_revocada',
            descripcion: "{$credencial->tipo->etiqueta()} revocada",
            recurso: $credencial->consumidor,
            negocio: $credencial->negocio_id,
            nuevos: ['credencial_id' => $credencial->id, 'motivo' => $motivo],
            actor: $actor,
        );

        return $credencial;
    }

    /**
     * Pérdida o daño: revoca la credencial y emite otra. Las visitas y
     * recompensas pertenecen al consumidor, así que no se pierden.
     */
    public function reemplazarCredencial(Credencial $anterior, TipoCredencial $tipo, string $motivo, User $actor): Credencial
    {
        return DB::transaction(function () use ($anterior, $tipo, $motivo, $actor) {
            $this->revocarCredencial($anterior, "Reemplazo: {$motivo}", $actor);

            return $this->emitirCredencial($anterior->consumidor, $tipo, $actor, "Reemplaza a la credencial #{$anterior->id}");
        });
    }

    /**
     * Enlace digital de consulta (solo lectura). Emitir uno nuevo invalida
     * el anterior. Devuelve el token en claro UNA sola vez.
     */
    public function emitirEnlaceDigital(Consumidor $consumidor, ?User $actor, string $motivo): string
    {
        $this->exigirActivo($consumidor);
        $token = Codigos::token();
        $habiaEnlace = $consumidor->token_acceso_hash !== null;

        $consumidor->forceFill(['token_acceso_hash' => Codigos::hash($token), 'token_acceso_emitido_en' => now()])->save();

        $this->auditoria->registrar(
            accion: 'fidelidad.enlace_emitido',
            descripcion: $habiaEnlace ? 'Enlace digital renovado (el anterior dejó de funcionar)' : 'Enlace digital emitido',
            recurso: $consumidor,
            negocio: $consumidor->negocio_id,
            nuevos: ['motivo' => $motivo],
            actor: $actor,
        );

        return $token;
    }

    public function revocarEnlaceDigital(Consumidor $consumidor, string $motivo, User $actor): void
    {
        if ($consumidor->token_acceso_hash === null) {
            return;
        }

        $consumidor->forceFill(['token_acceso_hash' => null])->save();
        $this->auditoria->registrar(
            accion: 'fidelidad.enlace_revocado',
            descripcion: 'Enlace digital revocado',
            recurso: $consumidor,
            negocio: $consumidor->negocio_id,
            nuevos: ['motivo' => $motivo],
            actor: $actor,
        );
    }

    /**
     * Posibles duplicados de un consumidor dentro de su negocio (mismo
     * nombre normalizado). Teléfono y correo ya son únicos por negocio.
     *
     * @return Collection<int, Consumidor>
     */
    public function posiblesDuplicados(Consumidor $consumidor): Collection
    {
        $nombre = Str::of($consumidor->nombre)->ascii()->lower()->squish()->toString();

        return Consumidor::query()
            ->deNegocio($consumidor->negocio_id)
            ->whereKeyNot($consumidor->id)
            ->where('estado', '!=', Consumidor::FUSIONADO)
            ->get(['id', 'nombre', 'alias', 'telefono', 'email', 'created_at'])
            ->filter(fn (Consumidor $c) => Str::of($c->nombre)->ascii()->lower()->squish()->toString() === $nombre)
            ->values();
    }

    /**
     * Fusiona una cuenta duplicada (`$origen`) en `$destino`, del mismo
     * negocio. No borra nada: mueve credenciales, participaciones y
     * recompensas; si ambas participan en la misma campaña, el saldo del
     * origen se suma al destino con un ajuste trazable.
     */
    public function fusionar(Consumidor $origen, Consumidor $destino, string $motivo, User $actor): Consumidor
    {
        if ($origen->is($destino) || $origen->negocio_id !== $destino->negocio_id) {
            throw ValidationException::withMessages(['destino' => 'Solo se pueden fusionar dos cuentas distintas del mismo negocio.']);
        }

        if (! $origen->estaActivo() || ! $destino->estaActivo()) {
            throw ValidationException::withMessages(['destino' => 'Ambas cuentas deben estar activas.']);
        }

        return DB::transaction(function () use ($origen, $destino, $motivo, $actor) {
            Credencial::query()->where('consumidor_id', $origen->id)->update(['consumidor_id' => $destino->id]);
            Recompensa::query()->where('consumidor_id', $origen->id)->update(['consumidor_id' => $destino->id]);

            foreach (Participacion::query()->where('consumidor_id', $origen->id)->lockForUpdate()->get() as $participacion) {
                $existente = Participacion::query()->where('consumidor_id', $destino->id)
                    ->where('campana_id', $participacion->campana_id)->lockForUpdate()->first();

                if ($existente === null) {
                    Movimiento::query()->where('participacion_id', $participacion->id)->update(['consumidor_id' => $destino->id]);
                    $participacion->forceFill(['consumidor_id' => $destino->id])->save();

                    continue;
                }

                if ($participacion->visitas_ciclo > 0) {
                    Movimiento::query()->create([
                        'negocio_id' => $destino->negocio_id,
                        'participacion_id' => $existente->id,
                        'consumidor_id' => $destino->id,
                        'tipo' => TipoMovimiento::Ajuste,
                        'cantidad' => $participacion->visitas_ciclo,
                        'ciclo' => $existente->ciclo,
                        'operacion_uuid' => (string) Str::uuid(),
                        'motivo' => "Fusión de cuentas: saldo de la cuenta #{$origen->id}",
                        'autorizado_por' => $actor->id,
                    ]);
                    $existente->forceFill([
                        'visitas_ciclo' => $existente->visitas_ciclo + $participacion->visitas_ciclo,
                        'visitas_totales' => $existente->visitas_totales + $participacion->visitas_totales,
                    ])->save();
                }

                $participacion->forceFill(['estado' => Participacion::FUSIONADA])->save();
            }

            $origen->forceFill([
                'estado' => Consumidor::FUSIONADO,
                'fusionado_en_id' => $destino->id,
                'token_acceso_hash' => null,
                // Libera teléfono y correo para que no bloqueen al destino.
                'telefono' => null,
                'email' => null,
            ])->save();

            $this->auditoria->registrar(
                accion: 'fidelidad.consumidores_fusionados',
                descripcion: "Cuenta #{$origen->id} fusionada en #{$destino->id}",
                recurso: $destino,
                negocio: $destino->negocio_id,
                nuevos: ['origen_id' => $origen->id, 'motivo' => $motivo],
                actor: $actor,
            );

            return $destino->refresh();
        });
    }

    private function exigirActivo(Consumidor $consumidor): void
    {
        if (! $consumidor->estaActivo()) {
            throw ValidationException::withMessages(['consumidor' => 'La cuenta del consumidor no está activa.']);
        }
    }
}
