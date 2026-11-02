<?php

namespace App\Services\Servicios;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Enums\OrigenContratacion;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto que cambia contrataciones. Todas las transiciones se
 * auditan; ninguna borra datos del servicio ni afecta a las redirecciones
 * ni al estado del negocio.
 */
class GestorContrataciones
{
    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly AccesoServicios $acceso,
    ) {}

    /**
     * Activa un periodo nuevo. Si ya hay uno vigente con vencimiento, el
     * nuevo empieza al terminar el actual (no se pierde tiempo pagado).
     */
    public function activar(Negocio $negocio, Servicio $servicio, OrigenContratacion $origen, ?Pago $pago = null, ?User $actor = null, ?string $motivo = null): Contratacion
    {
        return DB::transaction(function () use ($negocio, $servicio, $origen, $pago, $actor, $motivo) {
            if ($pago !== null && $pago->contratacion_id !== null) {
                // El pago ya activó su periodo (evento duplicado o tardío).
                return Contratacion::query()->findOrFail($pago->contratacion_id);
            }

            $vigente = Contratacion::query()->vigentes()
                ->where('negocio_id', $negocio->id)->where('servicio_id', $servicio->id)
                ->lockForUpdate()->latest('vence_en')->first();

            $inicio = CarbonImmutable::now();

            if ($vigente !== null && $vigente->vence_en !== null && $vigente->vence_en->isFuture()) {
                $inicio = $vigente->vence_en;
            }

            $contratacion = Contratacion::query()->create([
                'negocio_id' => $negocio->id,
                'servicio_id' => $servicio->id,
                'estado' => EstadoContratacion::Activa,
                'origen' => $origen,
                'pago_id' => $pago?->id,
                'modalidad' => 'unico',
                'inicia_en' => $inicio,
                'vence_en' => $servicio->vigencia_meses ? $inicio->addMonths($servicio->vigencia_meses) : null,
                'motivo' => $motivo,
                'estado_cambiado_en' => now(),
                'creado_por' => $actor?->id,
            ]);

            if ($pago !== null) {
                $pago->forceFill(['contratacion_id' => $contratacion->id])->save();
            }

            $this->auditoria->registrar(
                accion: 'servicio.activado',
                descripcion: "{$servicio->nombre} activado para «{$negocio->nombre_comercial}» ({$origen->etiqueta()})",
                recurso: $contratacion,
                negocio: $negocio,
                nuevos: array_filter([
                    'origen' => $origen->value,
                    'vence_en' => $contratacion->vence_en?->toDateString(),
                    'pago_id' => $pago?->id,
                    'motivo' => $motivo,
                ], fn ($v) => $v !== null),
                actor: $actor,
            );
            $this->acceso->olvidar();

            return $contratacion;
        });
    }

    /**
     * Pago recibido fuera de línea (transferencia, efectivo): se registra
     * el pago con su referencia y se activa el periodo.
     */
    public function registrarPagoFueraDeLinea(Negocio $negocio, Servicio $servicio, int $importeCentavos, string $referencia, User $actor, ?string $motivo = null): Contratacion
    {
        return DB::transaction(function () use ($negocio, $servicio, $importeCentavos, $referencia, $actor, $motivo) {
            $pago = Pago::query()->create([
                'negocio_id' => $negocio->id,
                'servicio_id' => $servicio->id,
                'proveedor' => Pago::PROVEEDOR_MANUAL,
                'estado' => EstadoPago::Pagado,
                'importe_centavos' => $importeCentavos,
                'moneda' => $servicio->moneda,
                'referencia' => $referencia,
                'motivo' => $motivo,
                'pagado_en' => now(),
                'iniciado_por' => $actor->id,
            ]);

            return $this->activar($negocio, $servicio, OrigenContratacion::FueraDeLinea, $pago, $actor, $motivo);
        });
    }

    public function suspender(Contratacion $contratacion, string $motivo, ?User $actor = null, bool $automatica = false): Contratacion
    {
        return $this->cambiarEstado($contratacion, EstadoContratacion::Suspendida, $motivo, $actor, $automatica ? 'servicio.suspendido_automaticamente' : 'servicio.suspendido');
    }

    public function reactivar(Contratacion $contratacion, string $motivo, User $actor): Contratacion
    {
        if ($contratacion->estaVencida()) {
            throw ValidationException::withMessages(['contratacion' => 'El periodo ya venció. Registra una nueva contratación para reactivarlo.']);
        }

        $contratacion->restriccion_manual = false;

        return $this->cambiarEstado($contratacion, EstadoContratacion::Activa, $motivo, $actor, 'servicio.reactivado');
    }

    /**
     * Disputa o revisión: solo lectura hasta que se resuelva. Con
     * `$manual` DASTI decide mantenerla aunque Stripe falle a favor.
     */
    public function restringir(Contratacion $contratacion, string $motivo, ?User $actor = null, bool $manual = false): Contratacion
    {
        if ($contratacion->estado === EstadoContratacion::Suspendida || $contratacion->estado === EstadoContratacion::Cancelada) {
            return $contratacion;
        }

        $contratacion->restriccion_manual = $manual || $contratacion->restriccion_manual;

        return $this->cambiarEstado($contratacion, EstadoContratacion::Restringida, $motivo, $actor, 'servicio.restringido');
    }

    public function levantarRestriccion(Contratacion $contratacion, string $motivo, ?User $actor = null): Contratacion
    {
        if ($contratacion->estado !== EstadoContratacion::Restringida) {
            return $contratacion;
        }

        $contratacion->restriccion_manual = false;

        return $this->cambiarEstado($contratacion, EstadoContratacion::Activa, $motivo, $actor, 'servicio.restriccion_levantada');
    }

    public function cancelar(Contratacion $contratacion, string $motivo, ?User $actor = null): Contratacion
    {
        return $this->cambiarEstado($contratacion, EstadoContratacion::Cancelada, $motivo, $actor, 'servicio.cancelado');
    }

    /**
     * Marca como vencidos los periodos activos cuya vigencia terminó.
     */
    public function vencerPeriodos(): int
    {
        $vencidas = Contratacion::query()
            ->where('estado', EstadoContratacion::Activa)
            ->whereNotNull('vence_en')->where('vence_en', '<=', now())
            ->get();

        foreach ($vencidas as $contratacion) {
            $this->cambiarEstado($contratacion, EstadoContratacion::Vencida, 'Fin de la vigencia contratada', null, 'servicio.vencido');
        }

        return $vencidas->count();
    }

    /**
     * ¿Tiene el negocio otro periodo que otorgue acceso además de este?
     */
    public function tieneOtroDerechoVigente(Contratacion $contratacion): bool
    {
        return Contratacion::query()->vigentes()
            ->where('negocio_id', $contratacion->negocio_id)
            ->where('servicio_id', $contratacion->servicio_id)
            ->whereKeyNot($contratacion->id)
            ->exists();
    }

    private function cambiarEstado(Contratacion $contratacion, EstadoContratacion $estado, string $motivo, ?User $actor, string $accion): Contratacion
    {
        if ($contratacion->estado === $estado) {
            if ($contratacion->isDirty('restriccion_manual')) {
                $contratacion->save();
            }

            return $contratacion;
        }

        $anterior = $contratacion->estado;
        $contratacion->forceFill([
            'estado' => $estado,
            'motivo' => mb_substr($motivo, 0, 255),
            'estado_cambiado_en' => now(),
        ])->save();
        $contratacion->loadMissing(['negocio', 'servicio']);

        $this->auditoria->registrar(
            accion: $accion,
            descripcion: "{$contratacion->servicio->nombre} de «{$contratacion->negocio->nombre_comercial}»: {$anterior->etiqueta()} → {$estado->etiqueta()}",
            recurso: $contratacion,
            negocio: $contratacion->negocio_id,
            anteriores: ['estado' => $anterior->value],
            nuevos: ['estado' => $estado->value, 'motivo' => $motivo],
            actor: $actor,
        );
        $this->acceso->olvidar();

        return $contratacion;
    }
}
