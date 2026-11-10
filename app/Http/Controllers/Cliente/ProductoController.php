<?php

namespace App\Http\Controllers\Cliente;

use App\Actions\Productos\ActualizarProducto;
use App\Actions\Productos\CambiarCantidadQr;
use App\Actions\Productos\CambiarDestinoProducto;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarCantidadQrRequest;
use App\Http\Requests\CambiarDestinoRequest;
use App\Http\Requests\EditarNombreProductoRequest;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\ProductoResource;
use App\Models\CambioDestino;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Paginas\DestinosPagina;
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
            ->principales()
            ->with(['negocio', 'enlacesSecundarios'])
            ->buscar($filtros['buscar'] ?? null, incluirInternos: false)
            ->when($filtros['tipo'] ?? null, fn (Builder $q, string $v) => $q->where('tipo', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $v) => $q->where('estado', $v))
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        $accesos = $estadisticas->totalesHistoricosPorProducto($negocioId);
        $productos->getCollection()->each(function (Producto $p) use ($accesos) {
            $p->total_accesos = $accesos[$p->id] ?? 0;
            $p->enlacesSecundarios->each(fn (Producto $e) => $e->total_accesos = $accesos[$e->id] ?? 0);
        });

        return Inertia::render('cliente/productos/Index', [
            'productos' => ProductoResource::collection($productos),
            'filtros' => [
                'buscar' => (string) ($filtros['buscar'] ?? ''),
                'tipo' => $filtros['tipo'] ?? '',
                'estado' => $filtros['estado'] ?? '',
            ],
            'tipos' => TipoProducto::opciones(),
            'estados' => EstadoProducto::opciones(),
            'total_productos' => Producto::query()->deNegocio($negocioId)->principales()->count(),
        ]);
    }

    public function show(Request $request, Producto $producto, ConsultasEstadisticas $estadisticas, AjustesPlataforma $ajustes): Response|RedirectResponse
    {
        Gate::authorize('ver', $producto);

        // El reverso QR se administra desde la ficha del producto físico.
        if ($producto->esEnlaceSecundario()) {
            return redirect()->route('cliente.productos.show', $producto->producto_principal_id);
        }

        /** @var User $usuario */
        $usuario = $request->user();
        $negocioId = (int) $usuario->negocio_id;
        $producto->load(['negocio', 'enlacesSecundarios.negocio']);

        // Las estadísticas del producto suman todos sus enlaces (QR 2, reverso
        // QR de un combinado, incluso los archivados); cada enlace por
        // separado se muestra en estadisticas_medios.
        $filtro = new FiltroEstadisticas(
            FiltroEstadisticas::ultimosDias(30)->desde,
            FiltroEstadisticas::ultimosDias(30)->hasta,
            negocioId: $negocioId,
            soloProductosVigentes: true,
            productoFisicoId: $producto->id,
        );
        $historicos = $estadisticas->totalesHistoricosPorProducto($negocioId);
        $producto->total_accesos = $historicos[$producto->id] ?? 0;
        $producto->enlacesSecundarios->each(fn (Producto $e) => $e->total_accesos = $historicos[$e->id] ?? 0);
        $idsSecundarios = $producto->todosLosEnlacesSecundarios()->pluck('id');
        $totalHistorico = $producto->total_accesos + (int) $idsSecundarios->sum(fn (int $id) => $historicos[$id] ?? 0);
        $verEstadisticas = $ajustes->booleano('clientes_ven_estadisticas');

        return Inertia::render('cliente/productos/Show', [
            'producto' => new ProductoResource($producto),
            // El diseño corresponde al enlace que lleva el QR: el reverso en
            // los combinados con destinos independientes; con 2 QR, cada QR
            // tiene el suyo (opciones_qr_enlaces, por id de enlace).
            'opciones_qr' => OpcionesQr::desdeArreglo($producto->enlacesQr()[0]->opciones_qr)->aArreglo(),
            'opciones_qr_enlaces' => collect($producto->enlacesQr())
                ->mapWithKeys(fn (Producto $enlace) => [$enlace->id => OpcionesQr::desdeArreglo($enlace->opciones_qr)->aArreglo()])
                ->all(),
            'estadisticas' => $verEstadisticas ? [
                'totales' => $estadisticas->totales($filtro),
                'serie' => $estadisticas->serieDiaria($filtro),
                'total_historico' => $totalHistorico,
                'varios_enlaces' => $idsSecundarios->isNotEmpty(),
            ] : null,
            'estadisticas_medios' => $verEstadisticas && $producto->tieneDestinosIndependientes()
                ? array_map(fn (Producto $enlace) => [
                    'id' => $enlace->id,
                    'medio' => $enlace->etiquetaMedio(),
                    'totales' => $estadisticas->totales(new FiltroEstadisticas($filtro->desde, $filtro->hasta, negocioId: $negocioId, productoId: $enlace->id, soloProductosVigentes: true)),
                ], [$producto, ...$producto->enlacesSecundarios->all()])
                : [],
            // Página de enlaces publicada del negocio como destino sugerido.
            'destinos_pagina' => app(DestinosPagina::class)->paraNegocio($producto->negocio),
            // Solo los cambios hechos con la cuenta del propio usuario: los de
            // DASTI u otros usuarios se siguen registrando, pero no se envían.
            'cambios' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['usuario', 'producto'])
                    ->whereIn('producto_id', [$producto->id, ...$producto->enlacesSecundarios->pluck('id')->all()])
                    ->where('negocio_id', $negocioId)
                    ->where('user_id', $usuario->id)
                    ->latest('created_at')->latest('id')->limit(10)->get()
            ),
        ]);
    }

    /**
     * Ampliar a 2 QR o reducir a 1. Requiere «productos.cambiar_cantidad_qr»,
     * que ningún rol de cliente tiene de inicio (ProductoPolicy).
     */
    public function cantidadQr(CambiarCantidadQrRequest $request, Producto $producto, CambiarCantidadQr $accion): RedirectResponse
    {
        $cantidad = $request->integer('cantidad_qr');
        $accion->ejecutar($producto, $cantidad, $request->user(), $request->validated('motivo'), $request->validated('url_destino_qr2'));

        $this->notificar($cantidad === 2
            ? 'Tu producto ahora tiene 2 QR. Configura el destino y el diseño del QR 2.'
            : 'Tu producto ahora tiene 1 QR. El QR 2 dejó de funcionar; su historial se conserva.');

        return back();
    }

    /**
     * Cambia únicamente el nombre descriptivo: solo se toma el campo
     * validado «nombre», nunca el resto de la petición.
     */
    public function nombre(EditarNombreProductoRequest $request, Producto $producto, ActualizarProducto $accion): RedirectResponse
    {
        $anterior = $producto->nombre;
        $accion->ejecutar($producto, ['nombre' => (string) $request->validated('nombre')]);

        $this->notificar(
            $producto->nombre !== $anterior ? 'Nombre actualizado.' : 'El producto ya tenía ese nombre.',
            $producto->nombre !== $anterior ? 'success' : 'info',
        );

        return back();
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
