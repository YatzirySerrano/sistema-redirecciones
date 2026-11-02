<?php

namespace App\Http\Controllers;

use App\Services\Usuarios\PreferenciasUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarda la vista elegida (tabla o tarjetas) de un listado. Solo afecta a
 * la presentación: datos, filtros, permisos y paginación son los mismos.
 */
class PreferenciaController extends Controller
{
    public function vista(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'modulo' => ['required', Rule::in(PreferenciasUsuario::MODULOS)],
            'vista' => ['required', Rule::in(PreferenciasUsuario::VISTAS)],
        ]);

        PreferenciasUsuario::guardarVista($request->user(), $datos['modulo'], $datos['vista']);

        return response()->json(['vistas' => PreferenciasUsuario::vistas($request->user())]);
    }
}
