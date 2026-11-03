<?php

namespace App\Http\Controllers\Paginas;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoDominio;
use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Paginas\DominioPagina;
use App\Models\Paginas\EventoPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Servicio;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supervisión de DASTI (paginas.supervisar): negocios con el servicio,
 * estado de publicación, dominios e incidencias y uso agregado. Nunca
 * expone mensajes de visitantes ni datos personales.
 */
class SupervisionController extends Controller
{
    public function __invoke(Request $request, AccesoServicios $acceso): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $servicio = Servicio::query()->where('clave', Servicio::PAGINA_ENLACES)->first();

        $negocios = Contratacion::query()
            ->when($servicio, fn ($q) => $q->where('servicio_id', $servicio?->id))
            ->whereNotIn('estado', [EstadoContratacion::Pendiente])
            ->distinct()->pluck('negocio_id');

        $paginas = PaginaEnlaces::query()
            ->with(['negocio:id,nombre_comercial,estado', 'dominios'])
            ->where(fn ($q) => $q->whereIn('negocio_id', $negocios))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('slug', 'like', "%{$buscar}%")
                ->orWhereHas('negocio', fn ($n) => $n->where('nombre_comercial', 'like', "%{$buscar}%"))))
            ->latest('updated_at')
            ->paginate(25)->withQueryString();

        $uso = DB::table('paginas_enlaces_diarios')
            ->whereIn('pagina_id', $paginas->getCollection()->pluck('id'))
            ->where('fecha', '>=', now()->subDays(29)->toDateString())
            ->groupBy('pagina_id', 'tipo')
            ->selectRaw('pagina_id, tipo, SUM(total) as total')
            ->get()->groupBy('pagina_id');

        $sinPagina = $negocios->diff(PaginaEnlaces::query()->whereIn('negocio_id', $negocios)->pluck('negocio_id'))->count();

        return Inertia::render('admin/paginas/Index', [
            'filtros' => ['buscar' => $buscar],
            'resumen' => [
                'negocios_con_servicio' => $negocios->count(),
                'publicadas' => PaginaEnlaces::query()->whereIn('negocio_id', $negocios)->where('estado', 'publicado')->count(),
                'sin_abrir' => $sinPagina,
                'dominios_activos' => DominioPagina::query()->where('estado', EstadoDominio::Activo)->count(),
                'dominios_con_error' => DominioPagina::query()->where('estado', EstadoDominio::Error)->count(),
            ],
            'paginas' => ControladorPaginas::paginado($paginas->through(function (PaginaEnlaces $p) use ($acceso, $uso) {
                $totales = collect($uso->get($p->id, []))->pluck('total', 'tipo');
                $estadoServicio = $acceso->estado($p->negocio, Servicio::PAGINA_ENLACES);

                return [
                    'id' => $p->id,
                    'negocio' => $p->negocio->nombre_comercial,
                    'negocio_id' => $p->negocio_id,
                    'negocio_activo' => $p->negocio->estaActivo(),
                    'servicio' => $estadoServicio['estado'],
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'url_publica' => $p->urlPublica(),
                    'publicado_en' => $p->publicado_en?->toIso8601String(),
                    'incidencia' => $p->incidencia,
                    'incidencia_en' => $p->incidencia_en?->toIso8601String(),
                    'visitas_30d' => (int) ($totales[EventoPagina::VISITA] ?? 0),
                    'clics_30d' => (int) ($totales[EventoPagina::CLIC] ?? 0),
                    'dominios' => $p->dominios->map(fn (DominioPagina $d) => [
                        'host' => $d->host_unicode,
                        'estado' => $d->estado->value,
                        'estado_etiqueta' => $d->estado->etiqueta(),
                        'es_principal' => $d->es_principal,
                        'ultimo_error' => $d->ultimo_error,
                        'ultima_revision_en' => $d->ultima_revision_en?->toIso8601String(),
                    ])->values(),
                ];
            })),
        ]);
    }
}
