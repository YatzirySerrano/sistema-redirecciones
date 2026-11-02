<?php

namespace App\Services\Pagos;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Enums\OrigenContratacion;
use App\Models\Contratacion;
use App\Models\EventoStripe;
use App\Models\Pago;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Servicios\GestorContrataciones;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Stripe\Event;
use Stripe\StripeObject;

/**
 * Procesa eventos de Stripe YA verificados (firma comprobada por el
 * controlador).
 *
 * - Idempotente: cada `event.id` se procesa una sola vez (fila única en
 *   `eventos_stripe`, creada dentro de la misma transacción).
 * - Tolerante al orden: cada transición solo ocurre desde estados
 *   compatibles; un evento atrasado nunca revierte uno más reciente (p. ej.
 *   «expirado» no deshace «pagado»; los reembolsos usan el acumulado).
 * - La activación solo ocurre aquí, nunca desde la página de éxito.
 * - Si algo falla se lanza la excepción: la transacción se revierte, el
 *   evento no queda marcado y Stripe lo reintenta.
 */
class ProcesadorWebhookStripe
{
    public function __construct(
        private readonly GestorContrataciones $contrataciones,
        private readonly RegistradorAuditoria $auditoria,
        private readonly PasarelaPago $pasarela,
    ) {}

    /**
     * @return string Resultado legible (también se guarda en el evento).
     */
    public function procesar(Event $evento): string
    {
        try {
            return DB::transaction(function () use ($evento) {
                $registro = EventoStripe::query()->where('stripe_event_id', $evento->id)->lockForUpdate()->first();

                if ($registro?->procesado_en !== null) {
                    return 'duplicado';
                }

                $registro ??= EventoStripe::query()->create([
                    'stripe_event_id' => $evento->id,
                    'tipo' => $evento->type,
                    'creado_stripe_en' => CarbonImmutable::createFromTimestamp($evento->created),
                ]);

                [$resultado, $pago] = $this->despachar($evento);

                $registro->forceFill([
                    'resultado' => mb_substr($resultado, 0, 255),
                    'pago_id' => $pago?->id,
                    'procesado_en' => now(),
                ])->save();

                return $resultado;
            });
        } catch (QueryException $e) {
            // Dos entregas simultáneas del mismo evento: la segunda choca con
            // la clave única y se trata como duplicada.
            if (str_contains($e->getMessage(), 'eventos_stripe_stripe_event_id_unique')) {
                return 'duplicado';
            }

            throw $e;
        }
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function despachar(Event $evento): array
    {
        $objeto = $evento->data->object;

        if ((bool) $evento->livemode !== $this->pasarela->modoReal()) {
            return ['ignorado: entorno distinto (prueba/producción)', null];
        }

        return match ($evento->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->sesionPagada($objeto, $evento),
            'checkout.session.async_payment_failed' => $this->sesionTerminada($objeto, EstadoPago::Fallido, 'El pago fue rechazado.'),
            'checkout.session.expired' => $this->sesionTerminada($objeto, EstadoPago::Expirado, 'La sesión de pago caducó.'),
            'payment_intent.payment_failed' => $this->intentoFallido($objeto),
            'charge.refunded' => $this->reembolso($objeto),
            'charge.dispute.created' => $this->disputaAbierta($objeto, $evento),
            'charge.dispute.closed' => $this->disputaCerrada($objeto),
            default => ['ignorado: tipo no manejado', null],
        };
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function sesionPagada(StripeObject $sesion, Event $evento): array
    {
        $pago = $this->pagoDeSesion($sesion);

        if ($pago === null) {
            return ['ignorado: sesión desconocida', null];
        }

        if (($error = $this->validarSesion($sesion, $pago)) !== null) {
            $pago->forceFill(['requiere_revision' => true, 'motivo' => $error])->save();
            $this->auditoria->registrar(
                accion: 'pago.rechazado_validacion',
                descripcion: "Webhook de Stripe rechazado: {$error}",
                recurso: $pago,
                negocio: $pago->negocio_id,
                nuevos: ['evento' => $evento->id],
            );

            return ["rechazado: {$error}", $pago];
        }

        if ($this->campo($sesion, 'payment_status') !== 'paid') {
            // Pago diferido (p. ej. OXXO o transferencia): queda pendiente
            // hasta async_payment_succeeded.
            $pago->forceFill(['stripe_payment_intent_id' => $this->idDe($this->campo($sesion, 'payment_intent'))])->save();

            return ['pendiente de confirmación', $pago];
        }

        if (! in_array($pago->estado, [EstadoPago::Pendiente, EstadoPago::Fallido, EstadoPago::Expirado, EstadoPago::Cancelado], true)) {
            return ['sin cambios: el pago ya estaba confirmado', $pago];
        }

        $pago->forceFill([
            'estado' => EstadoPago::Pagado,
            'stripe_payment_intent_id' => $this->idDe($this->campo($sesion, 'payment_intent')),
            'pagado_en' => now(),
            'ultimo_evento_en' => CarbonImmutable::createFromTimestamp($evento->created),
            'motivo' => null,
        ])->save();
        $pago->loadMissing(['negocio', 'servicio']);

        $this->auditoria->registrar(
            accion: 'pago.confirmado',
            descripcion: "Pago confirmado por Stripe ({$this->importe($pago)})",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['evento' => $evento->id],
        );

        $this->contrataciones->activar($pago->negocio, $pago->servicio, OrigenContratacion::Stripe, $pago);

        return ['pagado y servicio activado', $pago];
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function sesionTerminada(StripeObject $sesion, EstadoPago $estado, string $motivo): array
    {
        $pago = $this->pagoDeSesion($sesion);

        if ($pago === null) {
            return ['ignorado: sesión desconocida', null];
        }

        $cambio = Pago::query()->whereKey($pago->id)
            ->whereIn('estado', [EstadoPago::Pendiente->value, EstadoPago::Cancelado->value])
            ->update(['estado' => $estado->value, 'motivo' => $motivo]);

        return [$cambio ? $estado->value : 'sin cambios: estado más reciente conservado', $pago];
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function intentoFallido(StripeObject $intento): array
    {
        $pago = $this->pagoDeIntento($intento);

        if ($pago === null) {
            return ['ignorado: pago desconocido', null];
        }

        $cambio = Pago::query()->whereKey($pago->id)->where('estado', EstadoPago::Pendiente->value)
            ->update(['estado' => EstadoPago::Fallido->value, 'motivo' => 'La tarjeta fue rechazada.']);

        return [$cambio ? 'fallido' : 'sin cambios', $pago];
    }

    /**
     * Reembolso total: suspende Fidelidad si no hay otro derecho vigente.
     * Parcial: se marca para revisión de DASTI y se mantiene el acceso.
     *
     * @return array{0: string, 1: Pago|null}
     */
    private function reembolso(StripeObject $cargo): array
    {
        $pago = $this->pagoDeIntento($cargo, 'payment_intent');

        if ($pago === null) {
            return ['ignorado: pago desconocido', null];
        }

        $reembolsado = max($pago->reembolsado_centavos, (int) $this->campo($cargo, 'amount_refunded'));
        $total = $reembolsado >= $pago->importe_centavos;

        if ($reembolsado === $pago->reembolsado_centavos && in_array($pago->estado, [EstadoPago::Reembolsado, EstadoPago::ReembolsoParcial], true)) {
            return ['sin cambios: reembolso ya registrado', $pago];
        }

        $pago->forceFill([
            'reembolsado_centavos' => $reembolsado,
            'estado' => $total ? EstadoPago::Reembolsado : EstadoPago::ReembolsoParcial,
            'requiere_revision' => ! $total,
        ])->save();

        $this->auditoria->registrar(
            accion: $total ? 'pago.reembolsado' : 'pago.reembolso_parcial',
            descripcion: $total ? 'Reembolso total confirmado por Stripe' : 'Reembolso parcial confirmado por Stripe: requiere revisión de DASTI',
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['reembolsado_centavos' => $reembolsado, 'importe_centavos' => $pago->importe_centavos],
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if (! $total || $contratacion === null) {
            return [$total ? 'reembolsado' : 'reembolso parcial: revisión de DASTI', $pago];
        }

        if ($this->contrataciones->tieneOtroDerechoVigente($contratacion)) {
            $this->contrataciones->cancelar($contratacion, 'Pago reembolsado; el negocio conserva otro periodo vigente.');

            return ['reembolsado: periodo cancelado, otro derecho vigente', $pago];
        }

        $this->contrataciones->suspender($contratacion, 'Reembolso total del pago confirmado por Stripe.', automatica: true);

        return ['reembolsado: servicio suspendido', $pago];
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function disputaAbierta(StripeObject $disputa, Event $evento): array
    {
        $pago = $this->pagoDeIntento($disputa, 'payment_intent');

        if ($pago === null) {
            return ['ignorado: pago desconocido', null];
        }

        // Evento atrasado: la disputa ya se cerró antes de recibir su apertura.
        if (EventoStripe::query()->where('pago_id', $pago->id)->where('tipo', 'charge.dispute.closed')->whereNotNull('procesado_en')->exists()) {
            return ['ignorado: la disputa ya estaba cerrada', $pago];
        }

        $pago->forceFill(['estado' => EstadoPago::EnDisputa, 'requiere_revision' => true, 'motivo' => 'Contracargo o disputa abierta.'])->save();
        $this->auditoria->registrar(
            accion: 'pago.disputa_abierta',
            descripcion: 'Disputa o contracargo abierto en Stripe: Fidelidad restringida para revisión',
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['evento' => $evento->id],
        );

        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        if ($contratacion !== null) {
            $this->contrataciones->restringir($contratacion, 'Disputa o contracargo en revisión.');
        }

        return ['disputa: servicio restringido', $pago];
    }

    /**
     * @return array{0: string, 1: Pago|null}
     */
    private function disputaCerrada(StripeObject $disputa): array
    {
        $pago = $this->pagoDeIntento($disputa, 'payment_intent');

        if ($pago === null) {
            return ['ignorado: pago desconocido', null];
        }

        $ganada = $this->campo($disputa, 'status') === 'won';
        $contratacion = $pago->contratacion_id ? Contratacion::query()->lockForUpdate()->find($pago->contratacion_id) : null;

        $pago->forceFill([
            'estado' => $ganada ? EstadoPago::Pagado : EstadoPago::Reembolsado,
            'reembolsado_centavos' => $ganada ? $pago->reembolsado_centavos : $pago->importe_centavos,
            'motivo' => $ganada ? 'Disputa resuelta a favor.' : 'Disputa perdida: fondos devueltos al cliente.',
        ])->save();

        $this->auditoria->registrar(
            accion: $ganada ? 'pago.disputa_ganada' : 'pago.disputa_perdida',
            descripcion: $ganada ? 'Disputa resuelta a favor de DASTI' : 'Disputa resuelta en contra: fondos devueltos',
            recurso: $pago,
            negocio: $pago->negocio_id,
        );

        if ($contratacion === null) {
            return [$ganada ? 'disputa ganada' : 'disputa perdida', $pago];
        }

        if (! $ganada) {
            $this->contrataciones->suspender($contratacion, 'Disputa resuelta en contra.', automatica: true);

            return ['disputa perdida: servicio suspendido', $pago];
        }

        if ($contratacion->estado === EstadoContratacion::Restringida && ! $contratacion->restriccion_manual) {
            $this->contrataciones->levantarRestriccion($contratacion, 'Disputa resuelta a favor.');

            return ['disputa ganada: restricción levantada', $pago];
        }

        return ['disputa ganada: DASTI mantiene la restricción', $pago];
    }

    /**
     * Comprueba que la sesión corresponde exactamente al pago registrado
     * (negocio, servicio, importe y moneda fijados por el servidor).
     */
    private function validarSesion(StripeObject $sesion, Pago $pago): ?string
    {
        $metadata = $sesion->metadata?->toArray() ?? [];

        return match (true) {
            ($metadata['pago_id'] ?? null) !== (string) $pago->id || (string) $this->campo($sesion, 'client_reference_id') !== (string) $pago->id => 'la sesión no corresponde al pago registrado',
            ($metadata['negocio_id'] ?? null) !== (string) $pago->negocio_id => 'el negocio de la sesión no coincide',
            ($metadata['servicio_id'] ?? null) !== (string) $pago->servicio_id => 'el servicio de la sesión no coincide',
            strtolower((string) $this->campo($sesion, 'currency')) !== strtolower($pago->moneda) => 'la moneda no coincide',
            (int) $this->campo($sesion, 'amount_total') !== $pago->importe_centavos => 'el importe no coincide',
            default => null,
        };
    }

    private function pagoDeSesion(StripeObject $sesion): ?Pago
    {
        return Pago::query()->where('stripe_checkout_session_id', (string) $sesion->id)->lockForUpdate()->first();
    }

    private function pagoDeIntento(StripeObject $objeto, string $campo = 'id'): ?Pago
    {
        $id = $this->idDe($this->campo($objeto, $campo));

        if ($id === null) {
            return null;
        }

        $pago = Pago::query()->where('stripe_payment_intent_id', $id)->lockForUpdate()->first();

        if ($pago !== null) {
            return $pago;
        }

        // Respaldo: el intento de pago lleva el pago_id en metadata.
        $metadata = $campo === 'id' ? ($objeto->metadata?->toArray() ?? []) : [];

        return isset($metadata['pago_id']) ? Pago::query()->lockForUpdate()->find((int) $metadata['pago_id']) : null;
    }

    /**
     * Lectura segura de un campo de un objeto de Stripe.
     */
    private function campo(StripeObject $objeto, string $nombre): mixed
    {
        return $objeto[$nombre] ?? null;
    }

    private function idDe(mixed $valor): ?string
    {
        return match (true) {
            is_string($valor) && $valor !== '' => $valor,
            $valor instanceof StripeObject && isset($valor->id) => (string) $valor->id,
            default => null,
        };
    }

    private function importe(Pago $pago): string
    {
        return number_format($pago->importe_centavos / 100, 2).' '.$pago->moneda;
    }
}
