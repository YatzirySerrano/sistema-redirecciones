<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\TipoMovimiento;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Recompensa;
use App\Models\RegistroAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Actividad del programa y reporte por empleado (visitas, canjes, ajustes),
 * para que el propietario detecte patrones anómalos de fraude interno.
 */
class ActividadController extends ControladorFidelidad
{
    public function __invoke(Request $request): Response
    {
        $datos = $request->validate(['dias' => ['nullable', 'integer', 'in:7,30,90']]);
        $dias = (int) ($datos['dias'] ?? 30);
        $negocio = $this->negocio($request);
        $desde = CarbonImmutable::now()->subDays($dias)->startOfDay();

        $porEmpleado = DB::table('fidelidad_movimientos as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.autorizado_por')
            ->where('m.negocio_id', $negocio->id)->where('m.created_at', '>=', $desde)
            ->groupBy('m.autorizado_por', 'u.name')
            ->selectRaw("u.name as nombre,
                SUM(m.tipo = 'visita') as visitas,
                SUM(m.tipo = 'ajuste') as ajustes,
                SUM(m.tipo = 'anulacion') as anulaciones,
                COUNT(DISTINCT CASE WHEN m.tipo = 'visita' THEN m.consumidor_id END) as consumidores,
                MAX(m.created_at) as ultima")
            ->orderByDesc('visitas')->get();

        $canjes = Recompensa::query()->where('negocio_id', $negocio->id)->where('canjeada_en', '>=', $desde)
            ->selectRaw('canjeada_por, COUNT(*) as total')->groupBy('canjeada_por')->pluck('total', 'canjeada_por');

        $nombres = DB::table('users')->whereIn('id', $canjes->keys())->pluck('name', 'id');

        return Inertia::render('cliente/fidelidad/Actividad', [
            ...$this->contexto($request),
            'dias' => $dias,
            'por_empleado' => $porEmpleado->map(fn ($f) => [
                'nombre' => $f->nombre ?? 'Sistema',
                'visitas' => (int) $f->visitas,
                'ajustes' => (int) $f->ajustes,
                'anulaciones' => (int) $f->anulaciones,
                'consumidores' => (int) $f->consumidores,
                'ultima' => $f->ultima ? CarbonImmutable::parse($f->ultima)->toIso8601String() : null,
            ]),
            'canjes_por_empleado' => $canjes->map(fn ($total, $id) => ['nombre' => $nombres[$id] ?? 'Sistema', 'total' => (int) $total])->values(),
            'ultimos' => Movimiento::query()->with(['autorizador:id,name', 'participacion.campana:id,nombre'])
                ->where('negocio_id', $negocio->id)->latest('id')->limit(40)->get()
                ->map(fn (Movimiento $m) => [
                    'id' => $m->id,
                    'tipo' => $m->tipo->value,
                    'tipo_etiqueta' => $m->tipo->etiqueta(),
                    'cantidad' => $m->cantidad,
                    'campana' => $m->participacion->campana->nombre,
                    'consumidor_id' => $m->consumidor_id,
                    'motivo' => $m->motivo,
                    'por' => $m->autorizador?->name,
                    'created_at' => $m->created_at->toIso8601String(),
                    'anulable' => $m->tipo === TipoMovimiento::Visita,
                ]),
            'auditoria' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])
                    ->where('negocio_id', $negocio->id)->where('accion', 'like', 'fidelidad.%')
                    ->latest('created_at')->latest('id')->limit(30)->get()
            ),
        ]);
    }
}
