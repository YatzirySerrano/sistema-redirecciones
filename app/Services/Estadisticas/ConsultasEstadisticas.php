<?php

namespace App\Services\Estadisticas;

use App\Enums\CategoriaDispositivo;
use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\MedioEnlace;
use App\Enums\ResultadoAcceso;
use App\Enums\TipoProducto;
use App\Services\Configuracion\AjustesPlataforma;
use Carbon\CarbonImmutable;
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
 * - El canal (NFC/QR) sale del enlace: su medio propio (combinados con
 *   destinos independientes) o el tipo de producto. Los combinados con un
 *   solo enlace compartido se reportan como «combinado»: no es posible
 *   saber si se escanearon o se leyeron por NFC.
 */
class ConsultasEstadisticas
{
    /** Canal de un enlace (alias p = productos). */
    private const CANAL = 'COALESCE(p.medio, p.tipo)';

    public function __construct(private readonly AjustesPlataforma $ajustes) {}

    /**
     * Totales del periodo.
     *
     * Los «visitantes estimados» cuentan huellas diarias anónimas distintas
     * (una por dispositivo y día). Las huellas se eliminan pasados
     * dasti.dias_conservacion_huella días, así que para periodos más antiguos
     * la estimación solo cubre desde `visitantes_desde` y se marca como
     * parcial (null si ningún día del periodo conserva huellas).
     *
     * @return array{total: int, bots: int, visitantes_estimados: int|null, visitantes_desde: string|null, visitantes_parcial: bool, productos_con_accesos: int, promedio_diario: float}
     */
    public function totales(FiltroEstadisticas $filtro): array
    {
        $fila = $this->diarios($filtro)
            ->selectRaw('COALESCE(SUM(ad.total), 0) as total, COALESCE(SUM(ad.bots), 0) as bots, COUNT(DISTINCT CASE WHEN ad.total > 0 THEN ad.producto_id END) as productos')
            ->first();

        $total = (int) ($fila->total ?? 0);
        $dias = max(1, (int) $filtro->desde->diffInDays($filtro->hasta) + 1);
        $cobertura = $this->coberturaHuellas($filtro);

        $visitantes = null;

        if ($cobertura !== null) {
            // La huella ya incluye el día en su cálculo (HMAC de IP + agente +
            // fecha), por lo que contar huellas distintas equivale a contar
            // visitantes por día. Consulta portable (MariaDB y SQLite).
            $visitantes = (int) $this->eventos($filtro)
                ->whereNotNull('ea.huella_diaria')
                ->where('ea.ocurrido_en', '>=', $cobertura)
                ->selectRaw('COUNT(DISTINCT ea.huella_diaria) as visitantes')
                ->value('visitantes');
        }

        return [
            'total' => $total,
            'bots' => (int) ($fila->bots ?? 0),
            'visitantes_estimados' => $visitantes,
            'visitantes_desde' => $cobertura?->toDateString(),
            'visitantes_parcial' => $cobertura !== null && $cobertura->greaterThan($filtro->desde),
            'productos_con_accesos' => (int) ($fila->productos ?? 0),
            'promedio_diario' => round($total / $dias, 1),
        ];
    }

    /**
     * Análisis completo de un periodo para las pantallas de estadísticas:
     * totales con el periodo anterior equivalente, series, distribuciones
     * y la cobertura de los datos detallados.
     *
     * @return array<string, mixed>
     */
    public function analisis(FiltroEstadisticas $filtro, int $limiteProductos = 10): array
    {
        $anterior = $filtro->anterior();
        $totalesAnterior = $this->totales($anterior);
        $serie = $this->serieCanales($filtro);
        $cobertura = $this->coberturaDetalle($filtro);

        return [
            'totales' => $this->totales($filtro),
            'anterior' => [
                ...$anterior->aArreglo(),
                'total' => $totalesAnterior['total'],
                'promedio_diario' => $totalesAnterior['promedio_diario'],
            ],
            'serie' => $serie,
            'serie_anterior' => array_map(fn (array $p) => $p['total'], $this->serieDiaria($anterior)),
            'canales' => $this->porTipo($filtro),
            'dias_semana' => $this->porDiaSemana($serie),
            'horario' => $this->mapaHorario($filtro),
            'productos_top' => $this->actividadPorProducto($filtro, $limiteProductos),
            'dispositivos' => $this->dispositivos($filtro),
            'navegadores' => $this->desglose($filtro, 'navegador'),
            'sistemas' => $this->desglose($filtro, 'sistema_operativo'),
            'sin_redirigir' => $this->sinRedirigir($filtro),
            'cobertura' => [
                'detalle_desde' => $cobertura?->toDateString(),
                'detalle_parcial' => $cobertura !== null && $cobertura->greaterThan($filtro->desde),
                'retencion_dias' => max(30, (int) $this->ajustes->obtener('dias_retencion_eventos')),
                'huella_dias' => max(1, (int) config('dasti.dias_conservacion_huella')),
            ],
        ];
    }

    /**
     * Total histórico de accesos humanos de toda la plataforma.
     */
    public function totalHistorico(): int
    {
        return (int) DB::table('accesos_diarios')->sum('total');
    }

    /**
     * Primer instante del periodo que aún conserva huellas diarias, o null
     * si el periodo completo es anterior a la conservación.
     */
    private function coberturaHuellas(FiltroEstadisticas $filtro): ?CarbonImmutable
    {
        $dias = max(1, (int) config('dasti.dias_conservacion_huella'));
        $limite = CarbonImmutable::today()->subDays($dias - 1)->startOfDay();

        if ($filtro->hasta->lessThan($limite)) {
            return null;
        }

        return $filtro->desde->greaterThan($limite) ? $filtro->desde : $limite;
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
     * Serie diaria completa con el desglose por canal (días sin accesos
     * incluidos con 0).
     *
     * @return list<array{fecha: string, total: int, nfc: int, qr: int, combinado: int}>
     */
    public function serieCanales(FiltroEstadisticas $filtro): array
    {
        $porDia = [];

        $this->diarios($filtro)
            ->selectRaw('ad.fecha as fecha, '.self::CANAL.' as canal, SUM(ad.total) as total')
            ->groupBy('ad.fecha')
            ->groupByRaw(self::CANAL)
            ->get()
            ->each(function ($fila) use (&$porDia) {
                $porDia[substr((string) $fila->fecha, 0, 10)][(string) $fila->canal] = (int) $fila->total;
            });

        $serie = [];

        for ($dia = $filtro->desde; $dia->lessThanOrEqualTo($filtro->hasta); $dia = $dia->addDay()) {
            $canales = $porDia[$dia->toDateString()] ?? [];
            $nfc = $canales['nfc'] ?? 0;
            $qr = $canales['qr'] ?? 0;
            $combinado = $canales['combinado'] ?? 0;
            $serie[] = ['fecha' => $dia->toDateString(), 'total' => $nfc + $qr + $combinado, 'nfc' => $nfc, 'qr' => $qr, 'combinado' => $combinado];
        }

        return $serie;
    }

    /**
     * Accesos por día de la semana a partir de una serie diaria (cubre todo
     * el periodo, incluso lo anterior a la depuración de eventos). Incluye
     * cuántas veces aparece cada día en el periodo para promediar con justicia.
     *
     * @param  list<array{fecha: string, total: int}>  $serie
     * @return list<array{dia: int, etiqueta: string, total: int, dias: int, promedio: float}>
     */
    public function porDiaSemana(array $serie): array
    {
        $nombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $totales = array_fill(1, 7, 0);
        $apariciones = array_fill(1, 7, 0);

        foreach ($serie as $punto) {
            $dia = CarbonImmutable::parse($punto['fecha'])->dayOfWeekIso;
            $totales[$dia] += $punto['total'];
            $apariciones[$dia]++;
        }

        $resultado = [];

        foreach ($nombres as $dia => $etiqueta) {
            $resultado[] = [
                'dia' => $dia,
                'etiqueta' => $etiqueta,
                'total' => $totales[$dia],
                'dias' => $apariciones[$dia],
                'promedio' => $apariciones[$dia] === 0 ? 0.0 : round($totales[$dia] / $apariciones[$dia], 1),
            ];
        }

        return $resultado;
    }

    /**
     * Primer instante del periodo que aún conserva eventos individuales
     * (hora, dispositivo, navegador), o null si el periodo completo es
     * anterior a la retención configurada.
     */
    public function coberturaDetalle(FiltroEstadisticas $filtro): ?CarbonImmutable
    {
        $dias = max(30, (int) $this->ajustes->obtener('dias_retencion_eventos'));
        $limite = CarbonImmutable::today()->subDays($dias - 1)->startOfDay();

        if ($filtro->hasta->lessThan($limite)) {
            return null;
        }

        return $filtro->desde->greaterThan($limite) ? $filtro->desde : $limite;
    }

    /**
     * Accesos por día de la semana y hora local (mapa de calor). Sale de los
     * eventos individuales, así que solo cubre el periodo de retención.
     *
     * @return array{disponible: bool, desde: string|null, parcial: bool, total: int, celdas: list<array{dia: int, hora: int, total: int}>, por_hora: list<int>}
     */
    public function mapaHorario(FiltroEstadisticas $filtro): array
    {
        $cobertura = $this->coberturaDetalle($filtro);
        $porHora = array_fill(0, 24, 0);

        if ($cobertura === null) {
            return ['disponible' => false, 'desde' => null, 'parcial' => false, 'total' => 0, 'celdas' => [], 'por_hora' => $porHora];
        }

        $dia = SqlFechas::diaSemana('ea.ocurrido_en');
        $hora = SqlFechas::hora('ea.ocurrido_en');

        $celdas = $this->eventos($filtro)
            ->where('ea.ocurrido_en', '>=', $cobertura)
            ->selectRaw("{$dia} as dia, {$hora} as hora, COUNT(*) as total")
            ->groupByRaw("{$dia}, {$hora}")
            ->get()
            ->map(fn ($f) => ['dia' => (int) $f->dia, 'hora' => (int) $f->hora, 'total' => (int) $f->total])
            ->values()
            ->all();

        foreach ($celdas as $celda) {
            $porHora[$celda['hora']] += $celda['total'];
        }

        return [
            'disponible' => true,
            'desde' => $cobertura->toDateString(),
            'parcial' => $cobertura->greaterThan($filtro->desde),
            'total' => array_sum($porHora),
            'celdas' => array_values($celdas),
            'por_hora' => array_values($porHora),
        ];
    }

    /**
     * Intentos que no llegaron a un destino (enlace sin destino, producto o
     * negocio suspendido), sin bots. Solo dentro de la retención de eventos.
     *
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    public function sinRedirigir(FiltroEstadisticas $filtro): array
    {
        $query = DB::table('eventos_acceso as ea')
            ->join('productos as p', 'p.id', '=', 'ea.producto_id')
            ->where('ea.resultado', '!=', ResultadoAcceso::Redirigido->value)
            ->where('ea.es_bot', false)
            ->whereBetween('ea.ocurrido_en', [$filtro->desde, $filtro->hasta]);

        return array_values($this->aplicarAlcance($query, $filtro, 'ea')
            ->selectRaw('ea.resultado as clave, COUNT(*) as total')
            ->groupBy('ea.resultado')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => [
                'clave' => (string) $f->clave,
                'etiqueta' => ResultadoAcceso::tryFrom((string) $f->clave)?->etiqueta() ?? (string) $f->clave,
                'total' => (int) $f->total,
            ])
            ->values()
            ->all());
    }

    /**
     * Actividad por producto físico (una tarjeta combinada con dos enlaces
     * cuenta como un producto), con su reparto por canal.
     *
     * @return list<array{id: int, nombre: string, codigo: string, tipo: string, tipo_clave: string, negocio: string, total: int, nfc: int, qr: int, combinado: int}>
     */
    public function actividadPorProducto(FiltroEstadisticas $filtro, int $limite = 10): array
    {
        $canal = self::CANAL;

        return array_values($this->diarios($filtro)
            ->join('productos as f', DB::raw('COALESCE(p.producto_principal_id, p.id)'), '=', 'f.id')
            ->join('negocios as n', 'n.id', '=', 'f.negocio_id')
            ->selectRaw("f.id, f.nombre, f.codigo, f.tipo, n.nombre_comercial as negocio, SUM(ad.total) as total,
                SUM(CASE WHEN {$canal} = 'nfc' THEN ad.total ELSE 0 END) as nfc,
                SUM(CASE WHEN {$canal} = 'qr' THEN ad.total ELSE 0 END) as qr,
                SUM(CASE WHEN {$canal} = 'combinado' THEN ad.total ELSE 0 END) as combinado")
            ->groupBy('f.id', 'f.nombre', 'f.codigo', 'f.tipo', 'n.nombre_comercial')
            ->havingRaw('SUM(ad.total) > 0')
            ->orderByDesc('total')
            ->orderBy('f.id')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'nombre' => (string) $f->nombre,
                'codigo' => (string) $f->codigo,
                'tipo' => TipoProducto::from((string) $f->tipo)->etiqueta(),
                'tipo_clave' => (string) $f->tipo,
                'negocio' => (string) $f->negocio,
                'total' => (int) $f->total,
                'nfc' => (int) $f->nfc,
                'qr' => (int) $f->qr,
                'combinado' => (int) $f->combinado,
            ])
            ->values()
            ->all());
    }

    /**
     * Productos físicos activos (de negocios activos) sin ningún acceso en
     * el periodo. Con negocio en el filtro, solo los que posee hoy.
     *
     * @return array{total: int, productos: list<array{id: int, nombre: string, codigo: string, tipo: string, ultimo_acceso_en: string|null}>}
     */
    public function productosSinActividad(FiltroEstadisticas $filtro, int $limite = 8): array
    {
        $conActividad = DB::table('accesos_diarios as ad')
            ->join('productos as p', 'p.id', '=', 'ad.producto_id')
            ->whereBetween('ad.fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
            ->where('ad.total', '>', 0)
            ->when($filtro->negocioId !== null, fn ($q) => $q->where('ad.negocio_id', $filtro->negocioId))
            ->selectRaw('DISTINCT COALESCE(p.producto_principal_id, p.id) as fisico');

        $query = DB::table('productos as f')
            ->join('negocios as n', 'n.id', '=', 'f.negocio_id')
            ->whereNull('f.producto_principal_id')
            ->where('f.estado', EstadoProducto::Activo->value)
            ->where('n.estado', EstadoCuenta::Activo->value)
            ->when($filtro->negocioId !== null, fn ($q) => $q->where('f.negocio_id', $filtro->negocioId))
            ->whereNotIn('f.id', $conActividad);

        $total = (clone $query)->count();

        $productos = $query
            ->selectRaw('f.id, f.nombre, f.codigo, f.tipo, (SELECT MAX(e.ultimo_acceso_en) FROM productos e WHERE e.id = f.id OR e.producto_principal_id = f.id) as ultimo')
            ->orderByRaw('ultimo IS NULL DESC')
            ->orderBy('ultimo')
            ->orderBy('f.nombre')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'nombre' => (string) $f->nombre,
                'codigo' => (string) $f->codigo,
                'tipo' => TipoProducto::from((string) $f->tipo)->etiqueta(),
                'ultimo_acceso_en' => $f->ultimo === null ? null : CarbonImmutable::parse((string) $f->ultimo)->toIso8601String(),
            ])
            ->values()
            ->all();

        return ['total' => $total, 'productos' => array_values($productos)];
    }

    /**
     * Negocios activos con al menos un producto activo y sin accesos en el
     * periodo.
     */
    public function negociosSinActividad(FiltroEstadisticas $filtro): int
    {
        $conActividad = DB::table('accesos_diarios')
            ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
            ->where('total', '>', 0)
            ->distinct()
            ->select('negocio_id');

        return DB::table('negocios as n')
            ->where('n.estado', EstadoCuenta::Activo->value)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('productos as p')
                ->whereColumn('p.negocio_id', 'n.id')
                ->where('p.estado', EstadoProducto::Activo->value))
            ->whereNotIn('n.id', $conActividad)
            ->count();
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: string, tipo: string, negocio: string, total: int}>
     */
    public function productosMasUsados(FiltroEstadisticas $filtro, int $limite = 8): array
    {
        return array_values($this->diarios($filtro)
            ->join('negocios as n', 'n.id', '=', 'p.negocio_id')
            ->selectRaw('p.id, p.nombre, p.codigo, p.tipo, p.medio, p.numero_qr, n.nombre_comercial as negocio, SUM(ad.total) as total')
            ->groupBy('p.id', 'p.nombre', 'p.codigo', 'p.tipo', 'p.medio', 'p.numero_qr', 'n.nombre_comercial')
            ->havingRaw('SUM(ad.total) > 0')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'nombre' => (string) $f->nombre,
                'codigo' => (string) $f->codigo,
                // En combinados independientes cada enlace es un medio; con
                // dos QR, «QR 1» / «QR 2».
                'tipo' => match (true) {
                    $f->numero_qr !== null => 'QR '.$f->numero_qr,
                    $f->medio !== null => MedioEnlace::from($f->medio)->etiqueta(),
                    default => TipoProducto::from($f->tipo)->etiqueta(),
                },
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
        // Un enlace con medio propio (combinado con destinos independientes)
        // cuenta en su medio; solo los combinados compartidos son «no
        // distinguibles».
        $totales = $this->diarios($filtro)
            ->selectRaw('COALESCE(p.medio, p.tipo) as tipo, SUM(ad.total) as total')
            ->groupByRaw('COALESCE(p.medio, p.tipo)')
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

        if ($filtro->productoFisicoId !== null) {
            $fisico = $filtro->productoFisicoId;
            $query->where(fn ($q) => $q->where('p.id', $fisico)->orWhere('p.producto_principal_id', $fisico));
        }

        if ($filtro->canal !== null) {
            $query->whereRaw(self::CANAL.' = ?', [$filtro->canal]);
        }

        return $query;
    }
}
