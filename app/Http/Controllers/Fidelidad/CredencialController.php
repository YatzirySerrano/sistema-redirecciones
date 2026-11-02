<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\TipoCredencial;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Services\Fidelidad\GestorConsumidores;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Credenciales de identificación (tarjeta NFC, QR impreso) y enlace
 * digital de consulta. Identifican; nunca autorizan visitas ni canjes.
 */
class CredencialController extends ControladorFidelidad
{
    public function store(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        $datos = $request->validate(['tipo' => ['required', Rule::in([TipoCredencial::Nfc->value, TipoCredencial::Qr->value])]]);

        $credencial = $gestor->emitirCredencial($consumidor, TipoCredencial::from($datos['tipo']), $this->usuario($request));
        $this->notificar("{$credencial->tipo->etiqueta()} emitida. Graba o imprime su enlace.");

        return back();
    }

    public function reemplazo(Request $request, Credencial $credencial, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $credencial);
        $datos = $request->validate([
            'tipo' => ['required', Rule::in([TipoCredencial::Nfc->value, TipoCredencial::Qr->value])],
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ], [], ['motivo' => 'motivo']);

        abort_unless($credencial->estaActiva(), 422, 'La credencial ya estaba revocada.');
        $gestor->reemplazarCredencial($credencial, TipoCredencial::from($datos['tipo']), $datos['motivo'], $this->usuario($request));
        $this->notificar('Credencial reemplazada. La anterior dejó de funcionar; el progreso se conserva.');

        return back();
    }

    public function destroy(Request $request, Credencial $credencial, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $credencial);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], [], ['motivo' => 'motivo']);

        $gestor->revocarCredencial($credencial, $datos['motivo'], $this->usuario($request));
        $this->notificar('Credencial revocada.');

        return back();
    }

    /**
     * QR de la credencial (imagen PNG para imprimir), solo para el personal.
     */
    public function qr(Request $request, Credencial $credencial): Response
    {
        $this->delNegocio($request, $credencial);
        abort_unless($credencial->estaActiva(), 404);

        $png = (new Builder(writer: new PngWriter, data: $credencial->urlPublica(), size: 600, margin: 24))->build()->getString();

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="fidelidad-'.$credencial->codigo.'.png"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Emite (o renueva) el enlace digital de consulta. Sirve como
     * recuperación presencial: el personal verifica la identidad y registra
     * el motivo; el enlace anterior deja de funcionar.
     */
    public function enlace(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
            'identidad_verificada' => ['accepted'],
        ], ['identidad_verificada.accepted' => 'Confirma que verificaste la identidad del consumidor.'], ['motivo' => 'motivo']);

        $token = $gestor->emitirEnlaceDigital($consumidor, $this->usuario($request), $datos['motivo']);
        $request->session()->flash('enlace_fidelidad', route('fidelidad.publico.enlace', $token));
        $this->notificar('Enlace digital emitido. Compártelo ahora con el consumidor: no se volverá a mostrar.');

        return back();
    }

    public function revocarEnlace(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], [], ['motivo' => 'motivo']);
        $gestor->revocarEnlaceDigital($consumidor, $datos['motivo'], $this->usuario($request));
        $this->notificar('Enlace digital revocado.');

        return back();
    }
}
