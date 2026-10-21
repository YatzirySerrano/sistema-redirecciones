<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use App\Http\Controllers\Controller;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard general de DASTI. Todos los indicadores provienen de consultas
 * reales sobre la base de datos.
 */
class PanelController extends Controller
{
    public function __invoke(ConsultasEstadisticas $estadisticas): Response
    {
        $filtro = FiltroEstadisticas::ultimosDias(30);

        $productos = Producto::query()->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN tipo IN (?, ?) THEN 1 ELSE 0 END) as qr', [TipoProducto::Qr->value, TipoProducto::Combinado->value])
            ->selectRaw('SUM(CASE WHEN tipo IN (?, ?) THEN 1 ELSE 0 END) as nfc', [TipoProducto::Nfc->value, TipoProducto::Combinado->value])
            ->selectRaw('COALESCE(SUM(total_accesos), 0) as accesos')
            ->first();

        $redireccionesActivas = Producto::query()
            ->where('estado', EstadoProducto::Activo)
            ->whereNotNull('url_destino')
            ->whereHas('negocio', fn (Builder $q) => $q->where('estado', EstadoCuenta::Activo))
            ->count();

        return Inertia::render('admin/Panel', [
            'indicadores' => [
                'negocios' => Negocio::query()->count(),
                'negocios_activos' => Negocio::query()->where('estado', EstadoCuenta::Activo)->count(),
                'productos' => (int) ($productos->total ?? 0),
                'productos_qr' => (int) ($productos->qr ?? 0),
                'productos_nfc' => (int) ($productos->nfc ?? 0),
                'redirecciones_activas' => $redireccionesActivas,
                'accesos_totales' => (int) ($productos->accesos ?? 0),
                'periodo' => $estadisticas->totales($filtro),
            ],
            'serie' => $estadisticas->serieDiaria($filtro),
            'productos_top' => $estadisticas->productosMasUsados($filtro, 6),
            'negocios_top' => $estadisticas->negociosMasActivos($filtro, 6),
            'por_tipo' => $estadisticas->porTipo($filtro),
            'ultimos_cambios' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['producto', 'usuario'])->latest('created_at')->latest('id')->limit(6)->get()
            ),
            'actividad' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])->latest('created_at')->latest('id')->limit(8)->get()
            ),
        ]);
    }
}
