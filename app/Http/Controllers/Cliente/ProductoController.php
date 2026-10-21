<?php

namespace App\Http\Controllers\Cliente;

use App\Actions\Productos\CambiarDestinoProducto;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarDestinoRequest;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Qr\OpcionesQr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Productos del negocio del cliente. Toda consulta parte de su negocio_id;
 * los accesos directos a productos ajenos se resuelven como 404 en la
 * política.
 */
class ProductoController extends Controller
{
    public function index(Request $request, ConsultasEstadisticas $estadisticas): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $negocioId = (int) $usuario->negocio_id;

        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::enum(TipoProducto::class)],
            'estado' => ['nullable', Rule::enum(EstadoProducto::class)],
        ]);

        $productos = Producto::query()
            ->deNegocio($negocioId)
            ->with('negocio')
            ->buscar($filtros['buscar'] ?? null, incluirInternos: false)
            ->when($filtros['tipo'] ?? null, fn (Builder $q, string $v) => $q->where('tipo', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $v) => $q->where('estado', $v))
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        $accesos = $estadisticas->totalesHistoricosPorProducto($negocioId);
        $productos->getCollection()->each(fn (Producto $p) => $p->total_accesos = $accesos[$p->id] ?? 0);

        return Inertia::render('cliente/productos/Index', [
            'productos' => ProductoResource::collection($productos),
            'filtros' => [
                'buscar' => (string) ($filtros['buscar'] ?? ''),
                'tipo' => $filtros['tipo'] ?? '',
                'estado' => $filtros['estado'] ?? '',
            ],
            'tipos' => TipoProducto::opciones(),
            'estados' => EstadoProducto::opciones(),
            'total_productos' => Producto::query()->deNegocio($negocioId)->count(),
        ]);
    }

    public function show(Request $request, Producto $producto, ConsultasEstadisticas $estadisticas, AjustesPlataforma $ajustes): Response
    {
        Gate::authorize('ver', $producto);

        /** @var User $usuario */
        $usuario = $request->user();
        $negocioId = (int) $usuario->negocio_id;
        $producto->load('negocio');

        $filtro = new FiltroEstadisticas(
            FiltroEstadisticas::ultimosDias(30)->desde,
            FiltroEstadisticas::ultimosDias(30)->hasta,
            negocioId: $negocioId,
            productoId: $producto->id,
            soloProductosVigentes: true,
        );
        $producto->total_accesos = $estadisticas->totalesHistoricosPorProducto($negocioId)[$producto->id] ?? 0;
        $verEstadisticas = $ajustes->booleano('clientes_ven_estadisticas');

        return Inertia::render('cliente/productos/Show', [
            'producto' => new ProductoResource($producto),
            'opciones_qr' => OpcionesQr::desdeArreglo($producto->opciones_qr)->aArreglo(),
            'estadisticas' => $verEstadisticas ? [
                'totales' => $estadisticas->totales($filtro),
                'serie' => $estadisticas->serieDiaria($filtro),
            ] : null,
            'cambios' => CambioDestinoResource::collection(
                $producto->cambiosDestino()->with(['usuario', 'producto'])
                    ->where('negocio_id', $negocioId)
                    ->latest('created_at')->latest('id')->limit(10)->get()
            ),
        ]);
    }

    public function destino(CambiarDestinoRequest $request, Producto $producto, CambiarDestinoProducto $accion): RedirectResponse
    {
        $cambio = $accion->ejecutar($producto, (string) $request->validated('url_destino'), $request->user());

        $this->notificar(
            $cambio ? 'Destino guardado. Tu QR y tu tarjeta NFC ya llevan al nuevo enlace.' : 'El destino ya tenía ese valor.',
            $cambio ? 'success' : 'info',
        );

        return back();
    }
}
