<?php

namespace App\Services\Fidelidad;

use App\Enums\EstadoCampana;
use App\Enums\EstadoRecompensa;
use App\Enums\TipoMovimiento;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Operaciones que cambian saldos de Fidelidad. Todas:
 *
 * - Requieren un usuario autorizado del negocio (lo comprueba quien llama:
 *   permiso + contratación activa); identificar al consumidor (escaneo,
 *   enlace o búsqueda) nunca basta.
 * - Son idempotentes por `operacion_uuid` (lo genera la interfaz al abrir la
 *   confirmación): doble clic, reintentos de red o solicitudes repetidas
 *   devuelven el resultado original sin duplicar.
 * - Se ejecutan en una transacción con bloqueo de fila (`FOR UPDATE`) de la
 *   participación o recompensa, así que dos registros simultáneos se
 *   serializan y respetan las reglas de frecuencia.
 * - Nunca borran movimientos: los errores se corrigen con ajustes o
 *   anulaciones con motivo, auditados.
 */
class RegistroVisitas
{
    private const ESPACIO_UUID = '6f2a8f0e-3c7a-4f1b-9d4e-2b7c1e5a9d10';

    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @return array{movimiento: Movimiento, participacion: Participacion, recompensa: Recompensa|null, repetida: bool}
     */
    public function registrarVisita(User $autorizador, Consumidor $consumidor, Campana $campana, string $uuid, ?Credencial $credencial = null): array
    {
        $this->exigirMismoNegocio($autorizador, $consumidor, $campana, $credencial);

        try {
            return DB::transaction(function () use ($autorizador, $consumidor, $campana, $uuid, $credencial) {
                $participacion = $this->participacionBloqueada($consumidor, $campana);

                if (($previa = $this->operacionPrevia($uuid, $consumidor)) !== null) {
                    return $previa;
                }

                $this->validarVisita($consumidor, $campana, $participacion, $credencial);
                $ahora = CarbonImmutable::now();
                $version = $participacion->version;

                // Vigencia de visitas: tras N días sin visitas, el saldo del
                // ciclo caduca (con ajuste trazable).
                if ($version->vigencia_visitas_dias && $participacion->visitas_ciclo > 0
                    && $participacion->ultima_visita_en?->addDays($version->vigencia_visitas_dias)->isPast()) {
                    $this->movimiento($participacion, TipoMovimiento::Ajuste, -$participacion->visitas_ciclo, (string) Uuid::uuid4(), 'Visitas caducadas por inactividad', null);
                    $participacion->visitas_ciclo = 0;
                }

                $movimiento = $this->movimiento($participacion, TipoMovimiento::Visita, 1, $uuid, null, $autorizador, $credencial);
                $participacion->visitas_ciclo++;
                $participacion->visitas_totales++;
                $participacion->ultima_visita_en = $ahora;
                $recompensa = null;

                if ($participacion->visitas_ciclo >= $version->meta_visitas) {
                    $recompensa = Recompensa::query()->create([
                        'negocio_id' => $participacion->negocio_id,
                        'participacion_id' => $participacion->id,
                        'consumidor_id' => $consumidor->id,
                        'ciclo' => $participacion->ciclo,
                        'descripcion' => $version->recompensa,
                        'estado' => EstadoRecompensa::Disponible,
                        'vence_en' => $version->vigencia_recompensa_dias ? $ahora->addDays($version->vigencia_recompensa_dias) : null,
                    ]);

                    if ($version->repetir_ciclo) {
                        $participacion->ciclo++;
                        $participacion->visitas_ciclo = 0;
                    } else {
                        $participacion->estado = Participacion::COMPLETADA;
                    }
                }

                $participacion->save();

                $this->auditoria->registrar(
                    accion: $recompensa ? 'fidelidad.meta_completada' : 'fidelidad.visita_registrada',
                    descripcion: $recompensa
                        ? "Visita registrada en «{$campana->nombre}»: meta alcanzada, recompensa disponible"
                        : "Visita registrada en «{$campana->nombre}»",
                    recurso: $consumidor,
                    negocio: $consumidor->negocio_id,
                    nuevos: array_filter(['movimiento_id' => $movimiento->id, 'recompensa_id' => $recompensa?->id, 'credencial_id' => $credencial?->id]),
                    actor: $autorizador,
                );

                return ['movimiento' => $movimiento, 'participacion' => $participacion, 'recompensa' => $recompensa, 'repetida' => false];
            });
        } catch (QueryException $e) {
            // Carrera con la misma operación: la otra transacción ya la guardó.
            if (str_contains($e->getMessage(), 'operacion_uuid_unique') && ($previa = $this->operacionPrevia($uuid, $consumidor)) !== null) {
                return $previa;
            }

            throw $e;
        }
    }

    /**
     * Canje atómico: una recompensa solo se entrega una vez.
     *
     * @return array{recompensa: Recompensa, repetida: bool}
     */
    public function canjear(User $autorizador, Recompensa $recompensa, string $uuid): array
    {
        if ($recompensa->negocio_id !== $autorizador->negocio_id) {
            throw ValidationException::withMessages(['recompensa' => 'La recompensa no pertenece a tu negocio.']);
        }

        // El vencimiento se registra aunque el canje después se rechace.
        Recompensa::query()->whereKey($recompensa->id)->where('estado', EstadoRecompensa::Disponible)
            ->whereNotNull('vence_en')->where('vence_en', '<=', now())
            ->update(['estado' => EstadoRecompensa::Vencida->value]);

        return DB::transaction(function () use ($autorizador, $recompensa, $uuid) {
            $recompensa = Recompensa::query()->lockForUpdate()->findOrFail($recompensa->id);

            if ($recompensa->canje_uuid === $uuid) {
                return ['recompensa' => $recompensa, 'repetida' => true];
            }

            if ($recompensa->estado !== EstadoRecompensa::Disponible) {
                throw ValidationException::withMessages(['recompensa' => match ($recompensa->estado) {
                    EstadoRecompensa::Canjeada => 'Esta recompensa ya fue canjeada el '.$recompensa->canjeada_en?->format('d/m/Y H:i').'.',
                    EstadoRecompensa::Vencida => 'Esta recompensa venció y ya no se puede canjear.',
                    default => 'Esta recompensa fue anulada.',
                }]);
            }

            if (! $recompensa->consumidor->estaActivo()) {
                throw ValidationException::withMessages(['recompensa' => 'La cuenta del consumidor no está activa.']);
            }

            $recompensa->forceFill([
                'estado' => EstadoRecompensa::Canjeada,
                'canje_uuid' => $uuid,
                'canjeada_en' => now(),
                'canjeada_por' => $autorizador->id,
            ])->save();

            $this->auditoria->registrar(
                accion: 'fidelidad.recompensa_canjeada',
                descripcion: "Recompensa canjeada: {$recompensa->descripcion}",
                recurso: $recompensa,
                negocio: $recompensa->negocio_id,
                nuevos: ['consumidor_id' => $recompensa->consumidor_id],
                actor: $autorizador,
            );

            return ['recompensa' => $recompensa, 'repetida' => false];
        });
    }

    /**
     * Corrección manual del saldo (positiva o negativa) con motivo.
     */
    public function ajustar(User $autorizador, Participacion $participacion, int $cantidad, string $motivo, string $uuid): Movimiento
    {
        if ($participacion->negocio_id !== $autorizador->negocio_id || $cantidad === 0) {
            throw ValidationException::withMessages(['cantidad' => 'Ajuste no válido.']);
        }

        return DB::transaction(function () use ($autorizador, $participacion, $cantidad, $motivo, $uuid) {
            $participacion = Participacion::query()->lockForUpdate()->findOrFail($participacion->id);

            if (($existente = Movimiento::query()->where('operacion_uuid', $uuid)->first()) !== null) {
                return $existente;
            }

            if ($participacion->visitas_ciclo + $cantidad < 0) {
                throw ValidationException::withMessages(['cantidad' => 'El saldo no puede quedar en negativo.']);
            }

            if ($participacion->visitas_ciclo + $cantidad >= $participacion->version->meta_visitas) {
                throw ValidationException::withMessages(['cantidad' => 'Un ajuste no puede completar la meta; registra visitas reales.']);
            }

            $movimiento = $this->movimiento($participacion, TipoMovimiento::Ajuste, $cantidad, $uuid, $motivo, $autorizador);
            $participacion->forceFill(['visitas_ciclo' => $participacion->visitas_ciclo + $cantidad])->save();

            $this->auditoria->registrar(
                accion: 'fidelidad.saldo_ajustado',
                descripcion: 'Ajuste manual de visitas ('.($cantidad > 0 ? '+' : '').$cantidad.')',
                recurso: $participacion->consumidor,
                negocio: $participacion->negocio_id,
                nuevos: ['cantidad' => $cantidad, 'motivo' => $motivo, 'movimiento_id' => $movimiento->id],
                actor: $autorizador,
            );

            return $movimiento;
        });
    }

    /**
     * Anula una visita registrada por error. Una visita solo se anula una
     * vez (el identificador de la anulación se deriva de la visita).
     */
    public function anularVisita(User $autorizador, Movimiento $visita, string $motivo): Movimiento
    {
        if ($visita->negocio_id !== $autorizador->negocio_id || $visita->tipo !== TipoMovimiento::Visita) {
            throw ValidationException::withMessages(['movimiento' => 'Solo se pueden anular visitas de tu negocio.']);
        }

        $uuid = Uuid::uuid5(self::ESPACIO_UUID, 'anulacion-'.$visita->id)->toString();

        return DB::transaction(function () use ($autorizador, $visita, $motivo, $uuid) {
            $participacion = Participacion::query()->lockForUpdate()->findOrFail($visita->participacion_id);

            if (Movimiento::query()->where('operacion_uuid', $uuid)->exists()) {
                throw ValidationException::withMessages(['movimiento' => 'Esta visita ya fue anulada.']);
            }

            if ($visita->ciclo !== $participacion->ciclo || $participacion->visitas_ciclo < 1) {
                throw ValidationException::withMessages(['movimiento' => 'La visita pertenece a un ciclo ya completado; anula la recompensa si corresponde.']);
            }

            $anulacion = $this->movimiento($participacion, TipoMovimiento::Anulacion, -1, $uuid, $motivo, $autorizador);
            $participacion->forceFill([
                'visitas_ciclo' => $participacion->visitas_ciclo - 1,
                'visitas_totales' => max(0, $participacion->visitas_totales - 1),
            ])->save();

            $this->auditoria->registrar(
                accion: 'fidelidad.visita_anulada',
                descripcion: 'Visita anulada',
                recurso: $participacion->consumidor,
                negocio: $participacion->negocio_id,
                nuevos: ['movimiento_id' => $visita->id, 'motivo' => $motivo],
                actor: $autorizador,
            );

            return $anulacion;
        });
    }

    public function anularRecompensa(User $autorizador, Recompensa $recompensa, string $motivo): Recompensa
    {
        return DB::transaction(function () use ($autorizador, $recompensa, $motivo) {
            $recompensa = Recompensa::query()->lockForUpdate()->findOrFail($recompensa->id);

            if ($recompensa->negocio_id !== $autorizador->negocio_id || $recompensa->estado !== EstadoRecompensa::Disponible) {
                throw ValidationException::withMessages(['recompensa' => 'Solo se pueden anular recompensas disponibles de tu negocio.']);
            }

            $recompensa->forceFill(['estado' => EstadoRecompensa::Anulada, 'motivo' => $motivo])->save();
            $this->auditoria->registrar(
                accion: 'fidelidad.recompensa_anulada',
                descripcion: "Recompensa anulada: {$recompensa->descripcion}",
                recurso: $recompensa,
                negocio: $recompensa->negocio_id,
                nuevos: ['motivo' => $motivo],
                actor: $autorizador,
            );

            return $recompensa;
        });
    }

    private function participacionBloqueada(Consumidor $consumidor, Campana $campana): Participacion
    {
        $participacion = Participacion::query()
            ->where('consumidor_id', $consumidor->id)->where('campana_id', $campana->id)
            ->lockForUpdate()->first();

        if ($participacion !== null) {
            return $participacion;
        }

        // Primera visita: inscripción con la versión vigente de la campaña.
        $campana = Campana::query()->lockForUpdate()->findOrFail($campana->id);
        $version = $campana->versiones()->where('version', $campana->version_actual)->firstOrFail();

        if ($version->limite_participantes !== null && $campana->participaciones()->count() >= $version->limite_participantes) {
            throw ValidationException::withMessages(['campana' => 'La campaña alcanzó su límite de participantes.']);
        }

        Participacion::query()->insertOrIgnore([
            'negocio_id' => $consumidor->negocio_id,
            'consumidor_id' => $consumidor->id,
            'campana_id' => $campana->id,
            'campana_version_id' => $version->id,
            'estado' => Participacion::ACTIVA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Participacion::query()
            ->where('consumidor_id', $consumidor->id)->where('campana_id', $campana->id)
            ->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array{movimiento: Movimiento, participacion: Participacion, recompensa: Recompensa|null, repetida: bool}|null
     */
    private function operacionPrevia(string $uuid, Consumidor $consumidor): ?array
    {
        $movimiento = Movimiento::query()->where('operacion_uuid', $uuid)->first();

        if ($movimiento === null) {
            return null;
        }

        if ($movimiento->consumidor_id !== $consumidor->id) {
            throw ValidationException::withMessages(['operacion' => 'El identificador de operación ya se usó para otro consumidor.']);
        }

        $participacion = Participacion::query()->findOrFail($movimiento->participacion_id);
        $recompensa = Recompensa::query()->where('participacion_id', $participacion->id)->where('ciclo', $movimiento->ciclo)
            ->where('created_at', '>=', $movimiento->created_at)->first();

        return ['movimiento' => $movimiento, 'participacion' => $participacion, 'recompensa' => $recompensa, 'repetida' => true];
    }

    private function validarVisita(Consumidor $consumidor, Campana $campana, Participacion $participacion, ?Credencial $credencial): void
    {
        $ahora = CarbonImmutable::now();
        $version = $participacion->version;

        $error = match (true) {
            ! $consumidor->estaActivo() => 'La cuenta del consumidor no está activa.',
            $credencial !== null && ! $credencial->estaActiva() => 'Esta tarjeta fue revocada. Usa la credencial vigente del consumidor.',
            $campana->estado !== EstadoCampana::Activa => 'La campaña no está activa.',
            ! $version->vigenteEn($ahora) => 'Las condiciones de esta campaña no están vigentes hoy.',
            $participacion->estado !== Participacion::ACTIVA => 'El consumidor ya completó esta campaña.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['visita' => $error]);
        }

        $deHoy = Movimiento::query()->where('participacion_id', $participacion->id)
            ->where('tipo', TipoMovimiento::Visita)->where('created_at', '>=', $ahora->startOfDay())->count();

        if ($deHoy >= $version->max_visitas_por_dia) {
            throw ValidationException::withMessages(['visita' => "Ya se registraron {$deHoy} visita(s) hoy para este consumidor; el máximo diario es {$version->max_visitas_por_dia}."]);
        }

        if ($version->intervalo_minimo_minutos > 0 && $participacion->ultima_visita_en !== null
            && $participacion->ultima_visita_en->addMinutes($version->intervalo_minimo_minutos)->isFuture()) {
            throw ValidationException::withMessages(['visita' => "Deben pasar al menos {$version->intervalo_minimo_minutos} minutos entre visitas."]);
        }
    }

    private function movimiento(Participacion $participacion, TipoMovimiento $tipo, int $cantidad, string $uuid, ?string $motivo, ?User $autorizador, ?Credencial $credencial = null): Movimiento
    {
        return Movimiento::query()->create([
            'negocio_id' => $participacion->negocio_id,
            'participacion_id' => $participacion->id,
            'consumidor_id' => $participacion->consumidor_id,
            'credencial_id' => $credencial?->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'ciclo' => $participacion->ciclo,
            'operacion_uuid' => $uuid,
            'motivo' => $motivo,
            'autorizado_por' => $autorizador?->id,
        ]);
    }

    private function exigirMismoNegocio(User $autorizador, Consumidor $consumidor, Campana $campana, ?Credencial $credencial): void
    {
        $negocio = $autorizador->negocio_id;

        if ($negocio === null || $consumidor->negocio_id !== $negocio || $campana->negocio_id !== $negocio
            || ($credencial !== null && ($credencial->consumidor_id !== $consumidor->id || $credencial->negocio_id !== $negocio))) {
            throw ValidationException::withMessages(['visita' => 'El consumidor, la campaña o la credencial no pertenecen a tu negocio.']);
        }
    }
}
