<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Producto;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstadisticaController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas): Response
    {
        $request->validate([
            'negocio_id' => ['nullable', 'integer'],
            'producto_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $negocioId = $request->integer('negocio_id') ?: null;
        $productoId = $request->integer('producto_id') ?: null;

        // Un producto solo se filtra si pertenece al negocio elegido.
        if ($productoId !== null && $negocioId !== null
            && ! Producto::query()->whereKey($productoId)->where('negocio_id', $negocioId)->exists()) {
            $productoId = null;
        }

        $filtro = FiltroEstadisticas::desdePeticion($request, $negocioId, $productoId);

        return Inertia::render('admin/estadisticas/Index', [
            'filtros' => [
                ...$filtro->aArreglo(),
                'negocio_id' => $negocioId !== null ? (string) $negocioId : '',
                'producto_id' => $productoId !== null ? (string) $productoId : '',
            ],
            'totales' => $estadisticas->totales($filtro),
            'serie' => $estadisticas->serieDiaria($filtro),
            'productos_top' => $estadisticas->productosMasUsados($filtro, 15),
            'negocios_top' => $estadisticas->negociosMasActivos($filtro, 10),
            'por_tipo' => $estadisticas->porTipo($filtro),
            'dispositivos' => $estadisticas->dispositivos($filtro),
            'navegadores' => $estadisticas->desglose($filtro, 'navegador'),
            'sistemas' => $estadisticas->desglose($filtro, 'sistema_operativo'),
            'negocios' => Negocio::query()->orderBy('nombre_comercial')->get(['id', 'nombre_comercial'])
                ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])->values()->all(),
            'productos' => $negocioId === null ? [] : Producto::query()->where('negocio_id', $negocioId)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (Producto $p) => ['value' => (string) $p->id, 'label' => $p->nombre])->values()->all(),
            'dias_conservacion_huella' => (int) config('dasti.dias_conservacion_huella'),
        ]);
    }
}
