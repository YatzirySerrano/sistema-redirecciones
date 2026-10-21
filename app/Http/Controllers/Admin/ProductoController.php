<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Productos\ActualizarProducto;
use App\Actions\Productos\CambiarDestinoProducto;
use App\Actions\Productos\CambiarEstadoProducto;
use App\Actions\Productos\CrearProducto;
use App\Actions\Productos\EliminarProducto;
use App\Actions\Productos\TransferirProducto;
use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CambiarEstadoRequest;
use App\Http\Requests\Admin\GuardarProductoRequest;
use App\Http\Requests\Admin\TransferirProductoRequest;
use App\Http\Requests\CambiarDestinoRequest;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\ProductoResource;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Qr\OpcionesQr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión administrativa de productos físicos (QR, NFC y combinados).
 */
class ProductoController extends Controller
{
    private const ORDENES = [
        'registro' => 'created_at',
        'nombre' => 'nombre',
        'accesos' => 'total_accesos',
        'actividad' => 'ultimo_acceso_en',
    ];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'negocio_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', Rule::enum(TipoProducto::class)],
            'estado' => ['nullable', Rule::enum(EstadoProducto::class)],
            'preparacion' => ['nullable', Rule::enum(EstadoPreparacion::class)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'orden' => ['nullable', Rule::in(array_keys(self::ORDENES))],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $orden = self::ORDENES[$filtros['orden'] ?? 'registro'];
        $direccion = $filtros['direccion'] ?? ($orden === 'nombre' ? 'asc' : 'desc');

        $productos = Producto::query()
            ->with('negocio')
            ->buscar($filtros['buscar'] ?? null)
            ->when($filtros['negocio_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('negocio_id', (int) $id))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, string $v) => $q->where('tipo', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $v) => $q->where('estado', $v))
            ->when($filtros['preparacion'] ?? null, fn (Builder $q, string $v) => $q->where('estado_preparacion', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy($orden, $direccion)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/productos/Index', [
            'productos' => ProductoResource::collection($productos),
            'filtros' => [
                'buscar' => (string) ($filtros['buscar'] ?? ''),
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
                'tipo' => $filtros['tipo'] ?? '',
                'estado' => $filtros['estado'] ?? '',
                'preparacion' => $filtros['preparacion'] ?? '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
                'orden' => $filtros['orden'] ?? 'registro',
                'direccion' => $direccion,
            ],
            ...$this->catalogos(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/productos/Create', [
            'negocio_id' => $request->integer('negocio_id') ?: null,
            'url_publica_base' => Producto::construirUrlPermanente(''),
            ...$this->catalogos(),
        ]);
    }

    public function store(GuardarProductoRequest $request, CrearProducto $accion): RedirectResponse
    {
        $negocio = Negocio::query()->findOrFail($request->integer('negocio_id'));
        /** @var array{nombre: string, tipo: string, url_destino?: string|null, estado_preparacion?: string|null, fecha_entrega?: string|null, referencia_comercial?: string|null, notas_internas?: string|null} $datos */
        $datos = $request->safe()->except(['negocio_id', 'cantidad']);
        $productos = $accion->ejecutar($negocio, $datos, $request->user(), $request->integer('cantidad', 1));

        if (count($productos) === 1) {
            $this->notificar('Producto registrado con su enlace permanente.');

            return redirect()->route('admin.productos.show', $productos[0]);
        }

        $this->notificar(count($productos)." productos registrados para «{$negocio->nombre_comercial}».");

        return redirect()->route('admin.negocios.show', $negocio);
    }

    public function show(Producto $producto, ConsultasEstadisticas $estadisticas): Response
    {
        $producto->load('negocio');
        $filtro = new FiltroEstadisticas(
            FiltroEstadisticas::ultimosDias(30)->desde,
            FiltroEstadisticas::ultimosDias(30)->hasta,
            productoId: $producto->id,
        );

        return Inertia::render('admin/productos/Show', [
            'producto' => new ProductoResource($producto),
            'opciones_qr' => OpcionesQr::desdeArreglo($producto->opciones_qr)->aArreglo(),
            'puede_eliminarse' => EliminarProducto::puedeEliminarse($producto),
            'estadisticas' => [
                'totales' => $estadisticas->totales($filtro),
                'serie' => $estadisticas->serieDiaria($filtro),
                'dispositivos' => $estadisticas->dispositivos($filtro),
            ],
            'cambios' => CambioDestinoResource::collection(
                $producto->cambiosDestino()->with(['usuario', 'producto'])->latest('created_at')->latest('id')->limit(20)->get()
            ),
            'auditoria' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])
                    ->where('auditable_type', 'producto')->where('auditable_id', $producto->id)
                    ->latest('created_at')->latest('id')->limit(20)->get()
            ),
            'negocios' => $this->opcionesNegocios(),
        ]);
    }

    public function edit(Producto $producto): Response
    {
        return Inertia::render('admin/productos/Edit', [
            'producto' => new ProductoResource($producto->load('negocio')),
            ...$this->catalogos(),
        ]);
    }

    public function update(GuardarProductoRequest $request, Producto $producto, ActualizarProducto $accion): RedirectResponse
    {
        $accion->ejecutar($producto, $request->validated());
        $this->notificar('Datos del producto actualizados.');

        return redirect()->route('admin.productos.show', $producto);
    }

    public function destino(CambiarDestinoRequest $request, Producto $producto, CambiarDestinoProducto $accion): RedirectResponse
    {
        $cambio = $accion->ejecutar($producto, $request->validated('url_destino'), $request->user());

        $this->notificar($cambio ? 'Destino actualizado. El enlace permanente no cambió.' : 'El destino ya tenía ese valor.', $cambio ? 'success' : 'info');

        return back();
    }

    public function estado(CambiarEstadoRequest $request, Producto $producto, CambiarEstadoProducto $accion): RedirectResponse
    {
        $estado = EstadoProducto::from((string) $request->validated('estado'));
        $accion->ejecutar($producto, $estado, $request->validated('motivo'));

        $this->notificar($estado === EstadoProducto::Suspendido
            ? 'Producto suspendido: su enlace muestra «no disponible».'
            : 'Producto reactivado.');

        return back();
    }

    public function transferir(TransferirProductoRequest $request, Producto $producto, TransferirProducto $accion): RedirectResponse
    {
        $destino = Negocio::query()->findOrFail($request->integer('negocio_id'));
        $accion->ejecutar($producto, $destino, $request->user(), (string) $request->validated('motivo'), $request->boolean('conservar_destino'));

        $this->notificar("Producto transferido a «{$destino->nombre_comercial}».");

        return redirect()->route('admin.productos.show', $producto);
    }

    public function destroy(Producto $producto, EliminarProducto $accion): RedirectResponse
    {
        $negocioId = $producto->negocio_id;
        $accion->ejecutar($producto);

        $this->notificar('Producto eliminado.');

        return redirect()->route('admin.negocios.show', $negocioId);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'negocios' => $this->opcionesNegocios(),
            'tipos' => TipoProducto::opciones(),
            'estados' => EstadoProducto::opciones(),
            'preparaciones' => EstadoPreparacion::opciones(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function opcionesNegocios(): array
    {
        return array_values(Negocio::query()
            ->orderBy('nombre_comercial')
            ->get(['id', 'nombre_comercial', 'estado'])
            ->map(fn (Negocio $n) => [
                'value' => (string) $n->id,
                'label' => $n->nombre_comercial.($n->estaActivo() ? '' : ' (suspendido)'),
            ])
            ->values()
            ->all());
    }
}
