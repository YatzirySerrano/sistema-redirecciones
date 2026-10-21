<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Http\Controllers\Controller;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\ProductoResource;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de redirecciones: vista centrada en enlaces permanentes y su
 * destino efectivo, con edición rápida del destino.
 */
class EnlaceController extends Controller
{
    public const SITUACIONES = [
        'redirigiendo' => 'Redirigiendo',
        'sin_destino' => 'Sin destino',
        'producto_suspendido' => 'Producto suspendido',
        'negocio_suspendido' => 'Negocio suspendido',
    ];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'negocio_id' => ['nullable', 'integer'],
            'situacion' => ['nullable', Rule::in(array_keys(self::SITUACIONES))],
        ]);

        $negocioActivo = fn (Builder $q) => $q->where('estado', EstadoCuenta::Activo);

        $enlaces = Producto::query()
            ->with('negocio')
            ->buscar($filtros['buscar'] ?? null)
            ->when($filtros['negocio_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('negocio_id', (int) $id))
            ->when($filtros['situacion'] ?? null, function (Builder $q, string $situacion) use ($negocioActivo) {
                match ($situacion) {
                    'redirigiendo' => $q->where('estado', EstadoProducto::Activo)->whereNotNull('url_destino')->whereHas('negocio', $negocioActivo),
                    'sin_destino' => $q->whereNull('url_destino'),
                    'producto_suspendido' => $q->where('estado', EstadoProducto::Suspendido),
                    'negocio_suspendido' => $q->whereHas('negocio', fn (Builder $n) => $n->where('estado', EstadoCuenta::Suspendido)),
                    default => null,
                };
            })
            ->orderByRaw('destino_actualizado_en IS NULL, destino_actualizado_en DESC')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/enlaces/Index', [
            'enlaces' => ProductoResource::collection($enlaces),
            'filtros' => [
                'buscar' => (string) ($filtros['buscar'] ?? ''),
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
                'situacion' => $filtros['situacion'] ?? '',
            ],
            'situaciones' => array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys(self::SITUACIONES), self::SITUACIONES),
            'negocios' => Negocio::query()->orderBy('nombre_comercial')->get(['id', 'nombre_comercial'])
                ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])->values()->all(),
            'cambios_recientes' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['producto', 'usuario'])->latest('created_at')->latest('id')->limit(10)->get()
            ),
        ]);
    }
}
