<?php

namespace App\Services\Fidelidad;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Enums\EstadoRecompensa;
use App\Enums\TipoMovimiento;
use App\Models\Contratacion;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de Fidelidad para los paneles, calculados desde MariaDB. No
 * estima ingresos: solo cuenta hechos registrados.
 */
class ResumenFidelidad
{
    /**
     * @return array{
     *     consumidores: int,
     *     visitas_periodo: int,
     *     dias_periodo: int,
     *     recurrentes: int,
     *     recompensas_disponibles: int,
     *     recompensas_canjeadas_periodo: int,
     *     proximos_a_meta: list<array{consumidor_id: int, nombre: string, campana: string, visitas: int, meta: int, faltan: int}>
     * }
     */
    public function paraNegocio(Negocio $negocio, int $dias = 30): array
    {
        $desde = CarbonImmutable::now()->subDays($dias)->startOfDay();

        $visitas = Movimiento::query()->where('negocio_id', $negocio->id)
            ->where('tipo', TipoMovimiento::Visita)->where('created_at', '>=', $desde);

        // Recurrentes: consumidores con 2 o más visitas en el periodo.
        $recurrentes = DB::query()->fromSub(
            (clone $visitas)->selectRaw('consumidor_id')->groupBy('consumidor_id')->havingRaw('COUNT(*) >= 2'),
            'recurrentes',
        )->count();

        $proximos = Participacion::query()
            ->with(['consumidor:id,nombre,alias', 'campana:id,nombre', 'version:id,meta_visitas'])
            ->where('negocio_id', $negocio->id)->where('estado', Participacion::ACTIVA)->where('visitas_ciclo', '>', 0)
            ->get()
            ->map(fn (Participacion $p) => [
                'consumidor_id' => $p->consumidor_id,
                'nombre' => $p->consumidor->nombreVisible(),
                'campana' => $p->campana->nombre,
                'visitas' => $p->visitas_ciclo,
                'meta' => $p->version->meta_visitas,
                'faltan' => max(0, $p->version->meta_visitas - $p->visitas_ciclo),
            ])
            ->filter(fn (array $p) => $p['faltan'] <= max(1, (int) ceil($p['meta'] * 0.2)))
            ->sortBy('faltan')->take(8)->values()->all();
        $proximos = array_values($proximos);

        return [
            'consumidores' => Consumidor::query()->deNegocio($negocio->id)->where('estado', Consumidor::ACTIVO)->count(),
            'visitas_periodo' => (clone $visitas)->count(),
            'dias_periodo' => $dias,
            'recurrentes' => $recurrentes,
            'recompensas_disponibles' => Recompensa::query()->where('negocio_id', $negocio->id)
                ->where('estado', EstadoRecompensa::Disponible)
                ->where(fn ($q) => $q->whereNull('vence_en')->orWhere('vence_en', '>', now()))->count(),
            'recompensas_canjeadas_periodo' => Recompensa::query()->where('negocio_id', $negocio->id)
                ->where('estado', EstadoRecompensa::Canjeada)->where('canjeada_en', '>=', $desde)->count(),
            'proximos_a_meta' => $proximos,
        ];
    }

    /**
     * @return array{
     *     negocios_con_fidelidad: int,
     *     contrataciones_pendientes: int,
     *     pagos_confirmados: int,
     *     pagos_confirmados_centavos: int,
     *     pagos_pendientes: int,
     *     incidencias: int,
     *     moneda: string
     * }
     */
    public function paraPlataforma(): array
    {
        $fidelidad = Servicio::query()->where('clave', Servicio::FIDELIDAD)->value('id');

        $pagados = Pago::query()->where('servicio_id', $fidelidad)->where('estado', EstadoPago::Pagado);

        return [
            'negocios_con_fidelidad' => Contratacion::query()->vigentes()->where('servicio_id', $fidelidad)->distinct()->count('negocio_id'),
            'contrataciones_pendientes' => Pago::query()->where('servicio_id', $fidelidad)->where('estado', EstadoPago::Pendiente)->count()
                + Contratacion::query()->where('servicio_id', $fidelidad)->where('estado', EstadoContratacion::Pendiente)->count(),
            'pagos_confirmados' => (clone $pagados)->count(),
            'pagos_confirmados_centavos' => (int) (clone $pagados)->sum('importe_centavos'),
            'pagos_pendientes' => Pago::query()->where('servicio_id', $fidelidad)->where('estado', EstadoPago::Pendiente)->count(),
            'incidencias' => Pago::query()->where('requiere_revision', true)->count()
                + Contratacion::query()->where('estado', EstadoContratacion::Restringida)->count(),
            'moneda' => 'MXN',
        ];
    }
}
