<?php

namespace App\Http\Controllers\Cliente;

use App\Enums\EstadoProducto;
use App\Http\Controllers\Controller;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\ProductoResource;
use App\Models\CambioDestino;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas, AjustesPlataforma $ajustes): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $negocio = $usuario->negocio;
        abort_if($negocio === null, 404);

        $filtro = FiltroEstadisticas::ultimosDias(30, $negocio->id, soloProductosVigentes: true);
        $accesos = $estadisticas->totalesHistoricosPorProducto($negocio->id);

        $productos = Producto::query()->deNegocio($negocio->id)->with('negocio')->orderBy('nombre')->get()
            ->each(fn (Producto $p) => $p->total_accesos = $accesos[$p->id] ?? 0);

        $verEstadisticas = $ajustes->booleano('clientes_ven_estadisticas');

        return Inertia::render('cliente/Inicio', [
            'negocio' => ['nombre_comercial' => $negocio->nombre_comercial],
            'indicadores' => [
                'productos' => $productos->count(),
                'productos_activos' => $productos->where('estado', EstadoProducto::Activo)->count(),
                'accesos_totales' => array_sum($accesos),
                'accesos_30_dias' => $estadisticas->totales($filtro)['total'],
            ],
            'serie' => $verEstadisticas ? $estadisticas->serieDiaria($filtro) : null,
            'productos' => ProductoResource::collection($productos->sortByDesc('total_accesos')->take(5)->values()),
            'actividad' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['producto', 'usuario'])->where('negocio_id', $negocio->id)
                    ->whereHas('producto', fn ($q) => $q->where('negocio_id', $negocio->id))
                    ->latest('created_at')->latest('id')->limit(6)->get()
            ),
        ]);
    }
}
