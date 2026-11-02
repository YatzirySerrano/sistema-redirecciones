<?php

namespace App\Http\Controllers\Fidelidad;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Servicio;
use App\Services\Pagos\ServicioPagos;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 * Contratación de Fidelidad por el negocio. La página de éxito solo
 * MUESTRA el estado registrado: la activación la hace exclusivamente el
 * webhook verificado de Stripe.
 */
class ContratacionController extends ControladorFidelidad
{
    public function show(Request $request, ServicioPagos $pagos): Response
    {
        $usuario = $this->usuario($request);
        abort_unless($usuario->tienePermiso('servicios.contratar') || $usuario->tienePermiso('fidelidad.ver'), 403, 'No tienes permiso para realizar esta acción.');

        return $this->pagina($request, $pagos);
    }

    public function checkout(Request $request, ServicioPagos $pagos): RespuestaHttp
    {
        $resultado = $pagos->iniciarCheckout(Servicio::fidelidad(), $this->negocio($request), $this->usuario($request));

        // Redirección externa a la página segura de Stripe.
        return Inertia::location($resultado['url']);
    }

    public function exito(Request $request, ServicioPagos $pagos): Response
    {
        $sesion = (string) $request->query('session_id', '');
        $pago = $sesion === '' ? null : Pago::query()->where('stripe_checkout_session_id', $sesion)
            ->where('negocio_id', $this->negocio($request)->id)->first();

        return $this->pagina($request, $pagos, $pago, 'exito');
    }

    public function cancelado(Request $request, ServicioPagos $pagos): Response
    {
        $pago = Pago::query()->whereKey($request->integer('pago'))->where('negocio_id', $this->negocio($request)->id)->first();

        if ($pago !== null) {
            $pagos->marcarCancelado($pago);
            $pago->refresh();
        }

        return $this->pagina($request, $pagos, $pago, 'cancelado');
    }

    private function pagina(Request $request, ServicioPagos $pagos, ?Pago $pago = null, ?string $retorno = null): Response
    {
        $negocio = $this->negocio($request);
        $servicio = Servicio::fidelidad();

        return Inertia::render('cliente/fidelidad/Contratacion', [
            ...$this->contexto($request),
            'oferta' => [
                'nombre' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'precio_centavos' => $servicio->precio_centavos,
                'moneda' => $servicio->moneda,
                'vigencia_meses' => $servicio->vigencia_meses,
                'no_disponible' => $pagos->motivoNoDisponible($servicio, $negocio),
                'puede_contratar' => $this->usuario($request)->tienePermiso('servicios.contratar'),
            ],
            'retorno' => $retorno,
            'pago' => $pago === null ? null : [
                'id' => $pago->id,
                'estado' => $pago->estado->value,
                'estado_etiqueta' => $pago->estado->etiqueta(),
                'importe_centavos' => $pago->importe_centavos,
                'moneda' => $pago->moneda,
            ],
            'historial' => Contratacion::query()->where('negocio_id', $negocio->id)->where('servicio_id', $servicio->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (Contratacion $c) => [
                    'id' => $c->id,
                    'estado' => $c->estado->value,
                    'estado_etiqueta' => $c->estado->etiqueta(),
                    'origen' => $c->origen->etiqueta(),
                    'inicia_en' => $c->inicia_en?->toIso8601String(),
                    'vence_en' => $c->vence_en?->toIso8601String(),
                ]),
        ]);
    }
}
