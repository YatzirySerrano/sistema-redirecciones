<?php

namespace App\Services\Pagos;

use App\Enums\EstadoPago;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Inicio de pagos en línea. El importe y la moneda salen SIEMPRE de la
 * configuración del servicio en el servidor; el navegador solo pide
 * «contratar».
 */
class ServicioPagos
{
    public function __construct(
        private readonly PasarelaPago $pasarela,
        private readonly AccesoServicios $acceso,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function motivoNoDisponible(Servicio $servicio, Negocio $negocio): ?string
    {
        if (! $this->pasarela->configurada()) {
            return 'El pago en línea aún no está configurado. Contacta a DASTI para contratar.';
        }

        if (! $negocio->estaActivo()) {
            return 'Tu negocio está suspendido. Contacta a DASTI.';
        }

        if ($servicio->clave === Servicio::FIDELIDAD) {
            $estado = $this->acceso->fidelidad($negocio);

            if ($estado['activo'] && $estado['vence_en'] === null) {
                return 'Tu negocio ya tiene Fidelidad activa sin vencimiento.';
            }

            if ($estado['estado'] === 'restringida') {
                return 'Fidelidad está en revisión. Contacta a DASTI.';
            }
        }

        return $servicio->motivoNoContratable();
    }

    /**
     * @return array{pago: Pago, url: string}
     */
    public function iniciarCheckout(Servicio $servicio, Negocio $negocio, User $usuario): array
    {
        if (($motivo = $this->motivoNoDisponible($servicio, $negocio)) !== null) {
            throw ValidationException::withMessages(['servicio' => $motivo]);
        }

        $pago = DB::transaction(fn () => Pago::query()->create([
            'negocio_id' => $negocio->id,
            'servicio_id' => $servicio->id,
            'proveedor' => Pago::PROVEEDOR_STRIPE,
            'estado' => EstadoPago::Pendiente,
            'importe_centavos' => (int) $servicio->precio_centavos,
            'moneda' => $servicio->moneda,
            'livemode' => $this->pasarela->modoReal(),
            'iniciado_por' => $usuario->id,
        ]));

        try {
            $sesion = $this->pasarela->crearCheckout(
                $pago,
                "{$servicio->nombre} · {$negocio->nombre_comercial}",
                route('cliente.fidelidad.contratacion.exito').'?session_id={CHECKOUT_SESSION_ID}',
                route('cliente.fidelidad.contratacion.cancelado', ['pago' => $pago->id]),
            );
        } catch (Throwable $e) {
            report($e);
            $pago->forceFill(['estado' => EstadoPago::Fallido, 'motivo' => 'No se pudo crear la sesión de pago.'])->save();

            throw ValidationException::withMessages(['servicio' => 'No pudimos conectar con el procesador de pagos. Inténtalo nuevamente en unos minutos.']);
        }

        $pago->forceFill(['stripe_checkout_session_id' => $sesion['id']])->save();

        $this->auditoria->registrar(
            accion: 'pago.iniciado',
            descripcion: "Inicio de pago en línea de {$servicio->nombre}",
            recurso: $pago,
            negocio: $negocio,
            nuevos: ['importe_centavos' => $pago->importe_centavos, 'moneda' => $pago->moneda],
            actor: $usuario,
        );

        return ['pago' => $pago, 'url' => $sesion['url']];
    }

    /**
     * El usuario canceló en Stripe: el pago pendiente queda cancelado (solo
     * si sigue pendiente; nunca revierte un pago confirmado).
     */
    public function marcarCancelado(Pago $pago): void
    {
        Pago::query()->whereKey($pago->id)->where('estado', EstadoPago::Pendiente)
            ->update(['estado' => EstadoPago::Cancelado->value, 'motivo' => 'Cancelado por el usuario en el checkout.']);
    }
}
