<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\User;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estadísticas del negocio del cliente: solo sus productos vigentes y solo
 * los accesos ocurridos mientras le pertenecían. El negocio sale siempre de
 * la sesión; un producto de otro negocio en la petición se ignora.
 */
class EstadisticaController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas): Response
    {
        $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'canal' => ['nullable', Rule::in(FiltroEstadisticas::CANALES)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        /** @var User $usuario */
        $usuario = $request->user();
        $negocioId = (int) $usuario->negocio_id;

        $productoId = $request->integer('producto_id') ?: null;

        if ($productoId !== null && ! Producto::query()->deNegocio($negocioId)->principales()->whereKey($productoId)->exists()) {
            $productoId = null;
        }

        $canal = $request->string('canal')->toString() ?: null;
        $filtro = FiltroEstadisticas::desdePeticion($request, $negocioId, soloProductosVigentes: true, canal: $canal, productoFisicoId: $productoId);
        $filtroGeneral = new FiltroEstadisticas($filtro->desde, $filtro->hasta, $negocioId, soloProductosVigentes: true);

        return Inertia::render('cliente/estadisticas/Index', [
            'filtros' => [
                ...$filtro->aArreglo(),
                'producto_id' => $productoId !== null ? (string) $productoId : '',
                'canal' => $filtro->canal ?? '',
            ],
            ...$estadisticas->analisis($filtro),
            // Inventario del negocio (no depende del canal ni del producto).
            'sin_actividad' => $productoId === null && $filtro->canal === null
                ? $estadisticas->productosSinActividad($filtroGeneral)
                : null,
            'productos' => Producto::query()->deNegocio($negocioId)->principales()->orderBy('nombre')->get(['id', 'nombre', 'codigo'])
                ->map(fn (Producto $p) => ['value' => (string) $p->id, 'label' => $p->nombre, 'descripcion' => $p->codigo])->values()->all(),
            'canales_disponibles' => DB::table('productos')->where('negocio_id', $negocioId)
                ->selectRaw('DISTINCT COALESCE(medio, tipo) as canal')->pluck('canal')->map(fn ($c) => (string) $c)->values()->all(),
            'tiene_productos' => Producto::query()->deNegocio($negocioId)->exists(),
        ]);
    }
}
