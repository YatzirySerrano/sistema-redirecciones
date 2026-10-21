<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\User;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estadísticas del negocio del cliente: solo sus productos vigentes y solo
 * los accesos ocurridos mientras le pertenecían.
 */
class EstadisticaController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas): Response
    {
        $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        /** @var User $usuario */
        $usuario = $request->user();
        $negocioId = (int) $usuario->negocio_id;

        $productoId = $request->integer('producto_id') ?: null;

        if ($productoId !== null && ! Producto::query()->deNegocio($negocioId)->whereKey($productoId)->exists()) {
            $productoId = null;
        }

        $filtro = FiltroEstadisticas::desdePeticion($request, $negocioId, $productoId, soloProductosVigentes: true);

        return Inertia::render('cliente/estadisticas/Index', [
            'filtros' => [...$filtro->aArreglo(), 'producto_id' => $productoId !== null ? (string) $productoId : ''],
            'totales' => $estadisticas->totales($filtro),
            'serie' => $estadisticas->serieDiaria($filtro),
            'productos_top' => $estadisticas->productosMasUsados($filtro, 20),
            'dispositivos' => $estadisticas->dispositivos($filtro),
            'navegadores' => $estadisticas->desglose($filtro, 'navegador'),
            'sistemas' => $estadisticas->desglose($filtro, 'sistema_operativo'),
            'productos' => Producto::query()->deNegocio($negocioId)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (Producto $p) => ['value' => (string) $p->id, 'label' => $p->nombre])->values()->all(),
        ]);
    }
}
