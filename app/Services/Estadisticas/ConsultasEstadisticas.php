<?php

namespace App\Services\Estadisticas;

use App\Enums\CategoriaDispositivo;
use App\Enums\ResultadoAcceso;
use App\Enums\TipoProducto;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de estadísticas sobre accesos.
 *
 * - Totales y series diarias salen de `accesos_diarios` (agregado que se
 *   conserva aunque se depuren los eventos).
 * - Desgloses (dispositivo, navegador, SO) y visitantes estimados salen de
 *   `eventos_acceso`, por lo que solo cubren el periodo de retención.
 * - Los accesos de bots identificados se excluyen de los totales.
 */
class ConsultasEstadisticas
{
    /**
     * @return array{total: int, bots: int, visitantes_estimados: int, productos_con_accesos: int, promedio_diario: float}
     */
    public function totales(FiltroEstadisticas $filtro): array
    {
        $fila = $this->diarios($filtro)
            ->selectRaw('COALESCE(SUM(ad.total), 0) as total, COALESCE(SUM(ad.bots), 0) as bots, COUNT(DISTINCT CASE WHEN ad.total > 0 THEN ad.producto_id END) as productos')
            ->first();

        $visitantes = (int) $this->eventos($filtro)
            ->whereNotNull('ea.huella_diaria')
            ->selectRaw('COUNT(DISTINCT DATE(ea.ocurrido_en), ea.huella_diaria) as visitantes')
            ->value('visitantes');

        $total = (int) ($fila->total ?? 0);
        $dias = max(1, (int) $filtro->desde->diffInDays($filtro->hasta) + 1);

        return [
            'total' => $total,
            'bots' => (int) ($fila->bots ?? 0),
            'visitantes_estimados' => $visitantes,
            'productos_con_accesos' => (int) ($fila->productos ?? 0),
            'promedio_diario' => round($total / $dias, 1),
        ];
    }

    /**
     * Serie diaria completa (días sin accesos incluidos con 0).
     *
     * @return list<array{fecha: string, total: int}>
     */
    public function serieDiaria(FiltroEstadisticas $filtro): array
    {
        $porDia = $this->diarios($filtro)
            ->selectRaw('ad.fecha as fecha, SUM(ad.total) as total')
            ->groupBy('ad.fecha')
            ->pluck('total', 'fecha')
            ->mapWithKeys(fn ($total, $fecha) => [substr((string) $fecha, 0, 10) => (int) $total]);

        $serie = [];

        for ($dia = $filtro->desde; $dia->lessThanOrEqualTo($filtro->hasta); $dia = $dia->addDay()) {
            $clave = $dia->toDateString();
            $serie[] = ['fecha' => $clave, 'total' => (int) ($porDia[$clave] ?? 0)];
        }

        return $serie;
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: string, tipo: string, negocio: string, total: int}>
     */
    public function productosMasUsados(FiltroEstadisticas $filtro, int $limite = 8): array
    {
        return array_values($this->diarios($filtro)
            ->join('negocios as n', 'n.id', '=', 'p.negocio_id')
            ->selectRaw('p.id, p.nombre, p.codigo, p.tipo, n.nombre_comercial as negocio, SUM(ad.total) as total')
            ->groupBy('p.id', 'p.nombre', 'p.codigo', 'p.tipo', 'n.nombre_comercial')
            ->havingRaw('SUM(ad.total) > 0')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'nombre' => (string) $f->nombre,
                'codigo' => (string) $f->codigo,
                'tipo' => TipoProducto::from($f->tipo)->etiqueta(),
                'negocio' => (string) $f->negocio,
                'total' => (int) $f->total,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, nombre: string, total: int}>
     */
    public function negociosMasActivos(FiltroEstadisticas $filtro, int $limite = 6): array
    {
        return array_values($this->diarios($filtro)
            ->join('negocios as n', 'n.id', '=', 'ad.negocio_id')
            ->selectRaw('n.id, n.nombre_comercial as nombre, SUM(ad.total) as total')
            ->groupBy('n.id', 'n.nombre_comercial')
            ->havingRaw('SUM(ad.total) > 0')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => ['id' => (int) $f->id, 'nombre' => (string) $f->nombre, 'total' => (int) $f->total])
            ->values()
            ->all());
    }

    /**
     * Accesos por tipo de producto. Los productos combinados comparten un
     * mismo enlace entre QR y NFC, así que su canal no es distinguible y se
     * reportan aparte, sin repartirlos.
     *
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    public function porTipo(FiltroEstadisticas $filtro): array
    {
        $totales = $this->diarios($filtro)
            ->selectRaw('p.tipo as tipo, SUM(ad.total) as total')
            ->groupBy('p.tipo')
            ->pluck('total', 'tipo');

        return array_map(fn (TipoProducto $tipo) => [
            'clave' => $tipo->value,
            'etiqueta' => $tipo === TipoProducto::Combinado ? 'Combinado (canal no distinguible)' : $tipo->etiqueta(),
            'total' => (int) ($totales[$tipo->value] ?? 0),
        ], TipoProducto::cases());
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    public function dispositivos(FiltroEstadisticas $filtro): array
    {
        return array_values($this->eventos($filtro)
            ->selectRaw('ea.dispositivo as clave, COUNT(*) as total')
            ->groupBy('ea.dispositivo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => [
                'clave' => (string) $f->clave,
                'etiqueta' => CategoriaDispositivo::tryFrom($f->clave)?->etiqueta() ?? (string) $f->clave,
                'total' => (int) $f->total,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    public function desglose(FiltroEstadisticas $filtro, string $columna, int $limite = 6): array
    {
        abort_unless(in_array($columna, ['navegador', 'sistema_operativo'], true), 500);

        return array_values($this->eventos($filtro)
            ->selectRaw("COALESCE(ea.{$columna}, 'Otro') as clave, COUNT(*) as total")
            ->groupBy('clave')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => ['clave' => (string) $f->clave, 'etiqueta' => (string) $f->clave, 'total' => (int) $f->total])
            ->values()
            ->all());
    }

    /**
     * Accesos acumulados (sin límite de fechas) por producto para un negocio,
     * contando solo el periodo en que el producto le perteneció.
     *
     * @return array<int, int>
     */
    public function totalesHistoricosPorProducto(int $negocioId): array
    {
        return DB::table('accesos_diarios')
            ->where('negocio_id', $negocioId)
            ->selectRaw('producto_id, SUM(total) as total')
            ->groupBy('producto_id')
            ->pluck('total', 'producto_id')
            ->map(fn ($t) => (int) $t)
            ->all();
    }

    private function diarios(FiltroEstadisticas $filtro): Builder
    {
        $query = DB::table('accesos_diarios as ad')
            ->join('productos as p', 'p.id', '=', 'ad.producto_id')
            ->whereBetween('ad.fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()]);

        return $this->aplicarAlcance($query, $filtro, 'ad');
    }

    private function eventos(FiltroEstadisticas $filtro): Builder
    {
        $query = DB::table('eventos_acceso as ea')
            ->join('productos as p', 'p.id', '=', 'ea.producto_id')
            ->where('ea.resultado', ResultadoAcceso::Redirigido->value)
            ->where('ea.es_bot', false)
            ->whereBetween('ea.ocurrido_en', [$filtro->desde, $filtro->hasta]);

        return $this->aplicarAlcance($query, $filtro, 'ea');
    }

    private function aplicarAlcance(Builder $query, FiltroEstadisticas $filtro, string $alias): Builder
    {
        if ($filtro->negocioId !== null) {
            $query->where("{$alias}.negocio_id", $filtro->negocioId);

            if ($filtro->soloProductosVigentes) {
                $query->where('p.negocio_id', $filtro->negocioId);
            }
        }

        if ($filtro->productoId !== null) {
            $query->where("{$alias}.producto_id", $filtro->productoId);
        }

        return $query;
    }
}
