<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Estadisticas\IndicadoresPlataforma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estadísticas de la plataforma (personal DASTI).
 *
 * - «Uso»: accesos a los enlaces QR/NFC de todos los negocios o de uno.
 * - «Comercial»: contrataciones (servicios.ver) e ingresos cobrados
 *   (pagos.ver). Sin esos permisos la vista no se entrega.
 *
 * El análisis global (sin negocio) se guarda en caché unos minutos: recorre
 * los eventos de todos los negocios y no necesita exactitud al segundo.
 */
class EstadisticaController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas, IndicadoresPlataforma $plataforma): Response
    {
        $request->validate([
            'vista' => ['nullable', Rule::in(['uso', 'comercial'])],
            'negocio_id' => ['nullable', 'integer'],
            'producto_id' => ['nullable', 'integer'],
            'canal' => ['nullable', Rule::in(FiltroEstadisticas::CANALES)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        /** @var User $usuario */
        $usuario = $request->user();
        $puedeComercial = $usuario->tienePermiso('servicios.ver') || $usuario->tienePermiso('pagos.ver');
        $vista = $request->query('vista') === 'comercial' ? 'comercial' : 'uso';

        abort_if($vista === 'comercial' && ! $puedeComercial, 403, 'No tienes permiso para consultar la información comercial.');

        $negocioId = $request->integer('negocio_id') ?: null;
        $productoId = $request->integer('producto_id') ?: null;

        // Un producto solo se filtra si es un producto físico y, con negocio
        // elegido, si pertenece a ese negocio.
        if ($productoId !== null && ! Producto::query()->principales()->whereKey($productoId)
            ->when($negocioId !== null, fn ($q) => $q->where('negocio_id', $negocioId))->exists()) {
            $productoId = null;
        }

        $canal = $request->string('canal')->toString() ?: null;
        $filtro = FiltroEstadisticas::desdePeticion($request, $negocioId, canal: $canal, productoFisicoId: $productoId);

        $comun = [
            'vista' => $vista,
            'puede_comercial' => $puedeComercial,
            'filtros' => [
                ...$filtro->aArreglo(),
                'negocio_id' => $negocioId !== null ? (string) $negocioId : '',
                'producto_id' => $productoId !== null ? (string) $productoId : '',
                'canal' => $filtro->canal ?? '',
            ],
        ];

        if ($vista === 'comercial') {
            return Inertia::render('admin/estadisticas/Index', [
                ...$comun,
                'comercial' => [
                    'contrataciones' => $usuario->tienePermiso('servicios.ver') ? $plataforma->contrataciones($filtro) : null,
                    'ingresos' => $usuario->tienePermiso('pagos.ver') ? $plataforma->ingresos($filtro) : null,
                    'negocios' => $plataforma->negocios($filtro),
                    'crecimiento' => $plataforma->crecimientoMensual(12),
                ],
            ]);
        }

        $segundos = (int) config('dasti.cache_estadisticas_segundos', 300);
        $calcular = fn (): array => [
            ...$estadisticas->analisis($filtro, 15),
            'negocios_top' => $estadisticas->negociosMasActivos($filtro, 10),
            'sin_actividad' => $productoId === null && $filtro->canal === null
                ? $estadisticas->productosSinActividad($filtro)
                : null,
            'negocios_sin_actividad' => $negocioId === null && $productoId === null && $filtro->canal === null
                ? $estadisticas->negociosSinActividad($filtro)
                : null,
        ];

        $uso = $negocioId === null && $segundos > 0
            ? Cache::remember('estadisticas:uso:'.md5((string) json_encode([$filtro->aArreglo(), $productoId, $filtro->canal])), $segundos, $calcular)
            : $calcular();

        return Inertia::render('admin/estadisticas/Index', [
            ...$comun,
            'uso' => $uso,
            // Selectores con búsqueda remota: solo se envía lo ya elegido.
            'negocio_filtro' => $negocioId === null ? null : Negocio::query()->whereKey($negocioId)->get(['id', 'nombre_comercial'])
                ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])->first(),
            'producto_filtro' => $productoId === null ? null : Producto::query()->whereKey($productoId)->get(['id', 'nombre', 'codigo'])
                ->map(fn (Producto $p) => ['value' => (string) $p->id, 'label' => $p->nombre, 'descripcion' => $p->codigo])->first(),
        ]);
    }
}
