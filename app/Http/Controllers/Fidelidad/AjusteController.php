<?php

namespace App\Http\Controllers\Fidelidad;

use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Correcciones con motivo obligatorio y permiso propio (fidelidad.ajustes).
 * Nunca se borran movimientos: se agregan ajustes o anulaciones auditados.
 */
class AjusteController extends ControladorFidelidad
{
    public function ajuste(Request $request, Participacion $participacion, RegistroVisitas $registro): RedirectResponse
    {
        $this->delNegocio($request, $participacion);
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'between:-50,50', 'not_in:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
            'operacion_uuid' => ['required', 'uuid'],
        ], [], ['cantidad' => 'cantidad', 'motivo' => 'motivo']);

        $registro->ajustar($this->usuario($request), $participacion, (int) $datos['cantidad'], $datos['motivo'], strtolower($datos['operacion_uuid']));
        $this->notificar('Ajuste registrado.');

        return back();
    }

    public function anularVisita(Request $request, Movimiento $movimiento, RegistroVisitas $registro): RedirectResponse
    {
        $this->delNegocio($request, $movimiento);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], [], ['motivo' => 'motivo']);
        $registro->anularVisita($this->usuario($request), $movimiento, $datos['motivo']);
        $this->notificar('Visita anulada.');

        return back();
    }

    public function anularRecompensa(Request $request, Recompensa $recompensa, RegistroVisitas $registro): RedirectResponse
    {
        $this->delNegocio($request, $recompensa);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], [], ['motivo' => 'motivo']);
        $registro->anularRecompensa($this->usuario($request), $recompensa, $datos['motivo']);
        $this->notificar('Recompensa anulada.');

        return back();
    }
}
