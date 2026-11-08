<?php

namespace App\Services\Estadisticas;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoCuenta;
use App\Enums\EstadoDominio;
use App\Enums\EstadoPago;
use App\Enums\EstadoProducto;
use App\Enums\OrigenContratacion;
use App\Enums\RolUsuario;
use App\Enums\TipoProducto;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de la plataforma para el personal DASTI: negocios, usuarios,
 * productos, crecimiento, contrataciones e ingresos.
 *
 * Criterios:
 * - Ingresos = pagos COBRADOS confirmados (pagado, reembolso parcial o en
 *   disputa; los reembolsos se restan). Un pago pendiente, una orden
 *   generada o el precio de un servicio nunca cuentan como ingreso.
 * - Los pagos en línea en modo de pruebas (sandbox) se excluyen y se
 *   informan aparte; los pagos fuera de línea los registra DASTI.
 * - Todo sale de agregados SQL; no se envían registros al navegador.
 */
class IndicadoresPlataforma
{
    /** Estados de pago en los que el dinero se cobró (aunque luego se reembolse). */
    private const COBRADOS = [EstadoPago::Pagado, EstadoPago::ReembolsoParcial, EstadoPago::Reembolsado, EstadoPago::EnDisputa];

    /**
     * @return array{total: int, activos: int, suspendidos: int, nuevos: int, nuevos_anterior: int}
     */
    public function negocios(FiltroEstadisticas $periodo): array
    {
        $anterior = $periodo->anterior();
        $fila = DB::table('negocios')->selectRaw(
            'COUNT(*) as total,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as suspendidos,
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos,
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos_anterior',
            [EstadoCuenta::Activo->value, EstadoCuenta::Suspendido->value, $periodo->desde, $periodo->hasta, $anterior->desde, $anterior->hasta],
        )->first();

        return [
            'total' => (int) ($fila->total ?? 0),
            'activos' => (int) ($fila->activos ?? 0),
            'suspendidos' => (int) ($fila->suspendidos ?? 0),
            'nuevos' => (int) ($fila->nuevos ?? 0),
            'nuevos_anterior' => (int) ($fila->nuevos_anterior ?? 0),
        ];
    }

    /**
     * Cuentas de acceso. «Pendientes» = invitación enviada sin aceptar;
     * «fallidas» = el correo de invitación no se pudo enviar.
     *
     * @return array{total: int, activos: int, suspendidos: int, personal: int, clientes: int, invitaciones_pendientes: int, invitaciones_fallidas: int}
     */
    public function usuarios(): array
    {
        $fila = DB::table('users')->selectRaw(
            'COUNT(*) as total,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as suspendidos,
            SUM(CASE WHEN rol = ? THEN 1 ELSE 0 END) as personal,
            SUM(CASE WHEN invitacion_aceptada_en IS NULL AND invitacion_enviada_en IS NOT NULL AND invitacion_fallida_en IS NULL THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN invitacion_aceptada_en IS NULL AND invitacion_fallida_en IS NOT NULL THEN 1 ELSE 0 END) as fallidas',
            [EstadoCuenta::Activo->value, EstadoCuenta::Suspendido->value, RolUsuario::Admin->value],
        )->first();

        $total = (int) ($fila->total ?? 0);
        $personal = (int) ($fila->personal ?? 0);

        return [
            'total' => $total,
            'activos' => (int) ($fila->activos ?? 0),
            'suspendidos' => (int) ($fila->suspendidos ?? 0),
            'personal' => $personal,
            'clientes' => $total - $personal,
            'invitaciones_pendientes' => (int) ($fila->pendientes ?? 0),
            'invitaciones_fallidas' => (int) ($fila->fallidas ?? 0),
        ];
    }

    /**
     * Productos físicos (sin contar el reverso QR de un combinado como otro
     * producto) y su reparto por tipo.
     *
     * @return array{total: int, activos: int, suspendidos: int, nuevos: int, por_tipo: list<array{clave: string, etiqueta: string, total: int}>}
     */
    public function productos(FiltroEstadisticas $periodo): array
    {
        $base = fn (): Builder => DB::table('productos')->whereNull('producto_principal_id');

        $fila = $base()->selectRaw(
            'COUNT(*) as total,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as suspendidos,
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos',
            [EstadoProducto::Activo->value, EstadoProducto::Suspendido->value, $periodo->desde, $periodo->hasta],
        )->first();

        $porTipo = $base()->selectRaw('tipo, COUNT(*) as total')->groupBy('tipo')->pluck('total', 'tipo');

        return [
            'total' => (int) ($fila->total ?? 0),
            'activos' => (int) ($fila->activos ?? 0),
            'suspendidos' => (int) ($fila->suspendidos ?? 0),
            'nuevos' => (int) ($fila->nuevos ?? 0),
            'por_tipo' => array_map(fn (TipoProducto $t) => [
                'clave' => $t->value,
                'etiqueta' => $t->etiqueta(),
                'total' => (int) ($porTipo[$t->value] ?? 0),
            ], TipoProducto::cases()),
        ];
    }

    /**
     * Altas por mes (negocios, productos físicos y usuarios de clientes) en
     * los últimos N meses, incluido el actual. Meses sin altas con 0.
     *
     * @return list<array{mes: string, negocios: int, productos: int, usuarios: int}>
     */
    public function crecimientoMensual(int $meses = 12): array
    {
        $inicio = CarbonImmutable::today()->startOfMonth()->subMonths($meses - 1);
        $mes = SqlFechas::mes('created_at');

        $contar = fn (Builder $query): array => $query
            ->where('created_at', '>=', $inicio)
            ->selectRaw("{$mes} as mes, COUNT(*) as total")
            ->groupByRaw($mes)
            ->pluck('total', 'mes')
            ->map(fn ($t) => (int) $t)
            ->all();

        $negocios = $contar(DB::table('negocios'));
        $productos = $contar(DB::table('productos')->whereNull('producto_principal_id'));
        $usuarios = $contar(DB::table('users')->where('rol', '!=', RolUsuario::Admin->value));

        $serie = [];

        for ($m = $inicio; $m->lessThanOrEqualTo(CarbonImmutable::today()); $m = $m->addMonth()) {
            $clave = $m->format('Y-m');
            $serie[] = [
                'mes' => $clave,
                'negocios' => $negocios[$clave] ?? 0,
                'productos' => $productos[$clave] ?? 0,
                'usuarios' => $usuarios[$clave] ?? 0,
            ];
        }

        return $serie;
    }

    /**
     * Contrataciones de servicios adicionales: situación actual, nuevas y
     * canceladas en el periodo, y próximas a vencer.
     *
     * @return array<string, mixed>
     */
    public function contrataciones(FiltroEstadisticas $periodo, int $diasAviso = 30): array
    {
        $ahora = now();
        $aviso = $ahora->copy()->addDays($diasAviso);

        // Estado efectivo: una contratación activa con fecha pasada ya venció.
        $porServicio = DB::table('contrataciones as c')
            ->join('servicios as s', 's.id', '=', 'c.servicio_id')
            ->selectRaw(
                's.clave, s.nombre,
                SUM(CASE WHEN c.estado = ? AND (c.vence_en IS NULL OR c.vence_en > ?) THEN 1 ELSE 0 END) as vigentes,
                SUM(CASE WHEN c.estado = ? THEN 1 ELSE 0 END) as restringidas,
                SUM(CASE WHEN c.estado = ? THEN 1 ELSE 0 END) as suspendidas,
                SUM(CASE WHEN c.estado = ? OR (c.estado = ? AND c.vence_en <= ?) THEN 1 ELSE 0 END) as vencidas,
                SUM(CASE WHEN c.estado = ? THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN c.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevas,
                SUM(CASE WHEN c.estado = ? AND c.estado_cambiado_en BETWEEN ? AND ? THEN 1 ELSE 0 END) as canceladas',
                [
                    EstadoContratacion::Activa->value, $ahora,
                    EstadoContratacion::Restringida->value,
                    EstadoContratacion::Suspendida->value,
                    EstadoContratacion::Vencida->value, EstadoContratacion::Activa->value, $ahora,
                    EstadoContratacion::Pendiente->value,
                    $periodo->desde, $periodo->hasta,
                    EstadoContratacion::Cancelada->value, $periodo->desde, $periodo->hasta,
                ],
            )
            ->groupBy('s.clave', 's.nombre')
            ->orderBy('s.nombre')
            ->get()
            ->map(fn ($f) => [
                'clave' => (string) $f->clave,
                'nombre' => (string) $f->nombre,
                'vigentes' => (int) $f->vigentes,
                'restringidas' => (int) $f->restringidas,
                'suspendidas' => (int) $f->suspendidas,
                'vencidas' => (int) $f->vencidas,
                'pendientes' => (int) $f->pendientes,
                'nuevas' => (int) $f->nuevas,
                'canceladas' => (int) $f->canceladas,
            ])
            ->values()
            ->all();

        $nuevasPorOrigen = DB::table('contrataciones')
            ->whereBetween('created_at', [$periodo->desde, $periodo->hasta])
            ->selectRaw('origen, COUNT(*) as total')
            ->groupBy('origen')
            ->pluck('total', 'origen');

        $porVencer = DB::table('contrataciones as c')
            ->join('servicios as s', 's.id', '=', 'c.servicio_id')
            ->join('negocios as n', 'n.id', '=', 'c.negocio_id')
            ->where('c.estado', EstadoContratacion::Activa->value)
            ->whereBetween('c.vence_en', [$ahora, $aviso])
            ->orderBy('c.vence_en');

        return [
            'por_servicio' => $porServicio,
            'nuevas_por_origen' => array_map(fn (OrigenContratacion $o) => [
                'clave' => $o->value,
                'etiqueta' => $o->etiqueta(),
                'total' => (int) ($nuevasPorOrigen[$o->value] ?? 0),
            ], array_values(array_filter(OrigenContratacion::cases(), fn (OrigenContratacion $o) => $o !== OrigenContratacion::Stripe || isset($nuevasPorOrigen[$o->value])))),
            'por_vencer_total' => (clone $porVencer)->count(),
            'por_vencer' => $porVencer->limit(8)
                ->get(['c.id', 'c.negocio_id', 'n.nombre_comercial as negocio', 's.nombre as servicio', 'c.vence_en'])
                ->map(fn ($f) => [
                    'id' => (int) $f->id,
                    'negocio_id' => (int) $f->negocio_id,
                    'negocio' => (string) $f->negocio,
                    'servicio' => (string) $f->servicio,
                    'vence_en' => CarbonImmutable::parse((string) $f->vence_en)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'dias_aviso' => $diasAviso,
        ];
    }

    /**
     * Ingresos cobrados y confirmados del periodo (neto de reembolsos), su
     * evolución mensual y el estado de los pagos. Excluye pagos en línea en
     * modo de pruebas.
     *
     * @return array<string, mixed>
     */
    public function ingresos(FiltroEstadisticas $periodo, int $meses = 12): array
    {
        $cobrados = array_map(fn (EstadoPago $e) => $e->value, self::COBRADOS);
        $reales = fn (Builder $q): Builder => $q->where(fn (Builder $w) => $w
            ->where('p.proveedor', Pago::PROVEEDOR_MANUAL)
            ->orWhere('p.livemode', true));

        $confirmados = fn (): Builder => $reales(DB::table('pagos as p')->whereIn('p.estado', $cobrados)->whereNotNull('p.pagado_en'));

        $periodoFila = $confirmados()
            ->whereBetween('p.pagado_en', [$periodo->desde, $periodo->hasta])
            ->selectRaw('COUNT(*) as pagos, COALESCE(SUM(p.importe_centavos), 0) as bruto, COALESCE(SUM(p.reembolsado_centavos), 0) as reembolsado')
            ->first();

        $anterior = $periodo->anterior();
        $anteriorFila = $confirmados()
            ->whereBetween('p.pagado_en', [$anterior->desde, $anterior->hasta])
            ->selectRaw('COALESCE(SUM(p.importe_centavos - p.reembolsado_centavos), 0) as neto')
            ->first();

        $porServicio = $confirmados()
            ->join('servicios as s', 's.id', '=', 'p.servicio_id')
            ->whereBetween('p.pagado_en', [$periodo->desde, $periodo->hasta])
            ->selectRaw('s.clave, s.nombre, COUNT(*) as pagos, COALESCE(SUM(p.importe_centavos - p.reembolsado_centavos), 0) as neto')
            ->groupBy('s.clave', 's.nombre')
            ->orderByDesc('neto')
            ->get()
            ->map(fn ($f) => ['clave' => (string) $f->clave, 'etiqueta' => (string) $f->nombre, 'pagos' => (int) $f->pagos, 'total' => (int) $f->neto])
            ->values()
            ->all();

        $porMedio = $confirmados()
            ->whereBetween('p.pagado_en', [$periodo->desde, $periodo->hasta])
            ->selectRaw('p.proveedor, COUNT(*) as pagos, COALESCE(SUM(p.importe_centavos - p.reembolsado_centavos), 0) as neto')
            ->groupBy('p.proveedor')
            ->get()
            ->map(fn ($f) => [
                'clave' => (string) $f->proveedor,
                'etiqueta' => $f->proveedor === Pago::PROVEEDOR_MANUAL ? 'Fuera de línea (registrado por DASTI)' : ($f->proveedor === Pago::PROVEEDOR_OPENPAY ? 'En línea (Openpay)' : 'En línea (Stripe, histórico)'),
                'pagos' => (int) $f->pagos,
                'total' => (int) $f->neto,
            ])
            ->values()
            ->all();

        $inicio = CarbonImmutable::today()->startOfMonth()->subMonths($meses - 1);
        $mes = SqlFechas::mes('p.pagado_en');
        $mensual = $confirmados()
            ->where('p.pagado_en', '>=', $inicio)
            ->selectRaw("{$mes} as mes, COALESCE(SUM(p.importe_centavos - p.reembolsado_centavos), 0) as neto")
            ->groupByRaw($mes)
            ->pluck('neto', 'mes');

        $serie = [];

        for ($m = $inicio; $m->lessThanOrEqualTo(CarbonImmutable::today()); $m = $m->addMonth()) {
            $serie[] = ['mes' => $m->format('Y-m'), 'total' => (int) ($mensual[$m->format('Y-m')] ?? 0)];
        }

        $estados = DB::table('pagos')
            ->whereBetween('created_at', [$periodo->desde, $periodo->hasta])
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $pruebas = DB::table('pagos')
            ->where('proveedor', '!=', Pago::PROVEEDOR_MANUAL)
            ->where('livemode', false)
            ->whereBetween('created_at', [$periodo->desde, $periodo->hasta])
            ->count();

        return [
            'neto' => (int) ($periodoFila->bruto ?? 0) - (int) ($periodoFila->reembolsado ?? 0),
            'bruto' => (int) ($periodoFila->bruto ?? 0),
            'reembolsado' => (int) ($periodoFila->reembolsado ?? 0),
            'pagos' => (int) ($periodoFila->pagos ?? 0),
            'neto_anterior' => (int) ($anteriorFila->neto ?? 0),
            'por_servicio' => $porServicio,
            'por_medio' => $porMedio,
            'serie_mensual' => $serie,
            // Órdenes del periodo por estado (todas, incluidas las de pruebas).
            'estados' => array_map(fn (EstadoPago $e) => [
                'clave' => $e->value,
                'etiqueta' => $e->etiqueta(),
                'total' => (int) ($estados[$e->value] ?? 0),
            ], EstadoPago::cases()),
            'pendientes_total' => DB::table('pagos')->where('estado', EstadoPago::Pendiente->value)->count(),
            'requieren_revision' => DB::table('pagos')->where('requiere_revision', true)->count(),
            'pagos_de_prueba' => $pruebas,
            'moneda' => 'MXN',
        ];
    }

    public function dominiosConError(): int
    {
        return DB::table('paginas_enlaces_dominios')->where('estado', EstadoDominio::Error->value)->count();
    }
}
