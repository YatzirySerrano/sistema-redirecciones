<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrigenContratacion;
use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Acciones de DASTI sobre contrataciones: activación manual, pago fuera de
 * línea, suspensión, reactivación y restricciones. Todo con motivo y
 * auditado; nunca borra datos ni afecta las redirecciones.
 */
class ContratacionController extends Controller
{
    public function store(Request $request, GestorContrataciones $gestor): RedirectResponse
    {
        $datos = $request->validate([
            'negocio_id' => ['required', 'integer', Rule::exists('negocios', 'id')],
            'origen' => ['required', Rule::in([OrigenContratacion::Manual->value, OrigenContratacion::FueraDeLinea->value])],
            'importe' => ['exclude_unless:origen,fuera_de_linea', 'required', 'numeric', 'min:1', 'max:999999.99', 'decimal:0,2'],
            'referencia' => ['exclude_unless:origen,fuera_de_linea', 'required', 'string', 'max:120'],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['negocio_id' => 'negocio', 'importe' => 'importe', 'referencia' => 'referencia del pago', 'motivo' => 'motivo']);

        /** @var User $actor */
        $actor = $request->user();
        $negocio = Negocio::query()->findOrFail((int) $datos['negocio_id']);
        $servicio = Servicio::fidelidad();

        if ($datos['origen'] === OrigenContratacion::FueraDeLinea->value) {
            $gestor->registrarPagoFueraDeLinea($negocio, $servicio, (int) round(((float) $datos['importe']) * 100), $datos['referencia'], $actor, $datos['motivo']);
        } else {
            $gestor->activar($negocio, $servicio, OrigenContratacion::Manual, null, $actor, $datos['motivo']);
        }

        $this->notificar("Fidelidad activado para «{$negocio->nombre_comercial}».");

        return back();
    }

    public function estado(Request $request, Contratacion $contratacion, GestorContrataciones $gestor): RedirectResponse
    {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['suspender', 'reactivar', 'restringir', 'levantar'])],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['motivo' => 'motivo']);

        /** @var User $actor */
        $actor = $request->user();

        $motivo = (string) $datos['motivo'];

        match ((string) $datos['accion']) {
            'suspender' => $gestor->suspender($contratacion, $motivo, $actor),
            'reactivar' => $gestor->reactivar($contratacion, $motivo, $actor),
            'restringir' => $gestor->restringir($contratacion, $motivo, $actor, manual: true),
            default => $gestor->levantarRestriccion($contratacion, $motivo, $actor),
        };

        $this->notificar('Contratación actualizada.');

        return back();
    }
}
