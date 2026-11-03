<?php

namespace App\Services\Paginas;

use App\Enums\CategoriaDispositivo;
use App\Models\Paginas\EventoPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Services\Estadisticas\FiltroEstadisticas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de una Página de enlaces. Siempre filtradas por la página
 * (y por lo tanto por el negocio). Totales y series salen de los totales
 * diarios (se conservan); dispositivos, procedencia y visitantes estimados
 * de los eventos individuales (periodo de retención).
 *
 * «Visitantes estimados» = huellas diarias distintas (HMAC de IP + agente +
 * día + página) sumadas por día: un mismo dispositivo cuenta una vez al día.
 * No son personas exactas; las huellas se borran tras
 * dasti.dias_conservacion_huella días.
 */
class EstadisticasPagina
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $dias = (int) $filtro->desde->diffInDays($filtro->hasta) + 1;
        $previo = new FiltroEstadisticas($filtro->desde->subDays($dias), $filtro->desde->subDay()->endOfDay());

        $totales = $this->totales($pagina, $filtro);
        $anteriores = $this->totales($pagina, $previo);

        return [
            'totales' => [...$totales, ...$this->visitantes($pagina, $filtro)],
            'previo' => $anteriores,
            'serie' => $this->serie($pagina, $filtro),
            'elementos' => $this->elementos($pagina, $filtro, $totales['visitas']),
            'dispositivos' => $this->dispositivos($pagina, $filtro),
            'referentes' => $this->referentes($pagina, $filtro),
            'subpaginas' => $this->subpaginas($pagina, $filtro),
            'horas' => $this->horas($pagina, $filtro),
        ];
    }

    /**
     * @return array{visitas: int, clics: int, contactos: int, ctr: float, bots: int}
     */
    public function totales(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $filas = DB::table('paginas_enlaces_diarios')
            ->where('pagina_id', $pagina->id)
            ->where('negocio_id', $pagina->negocio_id)
            ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
            ->groupBy('tipo')
            ->selectRaw('tipo, SUM(total) as total, SUM(bots) as bots')
            ->get()->keyBy('tipo');

        $visitas = (int) ($filas[EventoPagina::VISITA]->total ?? 0);
        $clics = (int) ($filas[EventoPagina::CLIC]->total ?? 0);

        return [
            'visitas' => $visitas,
            'clics' => $clics,
            'contactos' => (int) ($filas[EventoPagina::CONTACTO]->total ?? 0),
            'ctr' => $visitas > 0 ? round($clics / $visitas * 100, 1) : 0.0,
            'bots' => (int) $filas->sum(fn ($f) => (int) $f->bots),
        ];
    }

    /**
     * @return array{visitantes_estimados: int|null, visitantes_parcial: bool}
     */
    private function visitantes(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $limite = CarbonImmutable::today()->subDays(max(1, (int) config('dasti.dias_conservacion_huella', 30)) - 1);

        if ($filtro->hasta->lt($limite)) {
            return ['visitantes_estimados' => null, 'visitantes_parcial' => false];
        }

        $total = DB::query()->fromSub(
            EventoPagina::query()->where('pagina_id', $pagina->id)->where('negocio_id', $pagina->negocio_id)
                ->where('tipo', EventoPagina::VISITA)->where('es_bot', false)->whereNotNull('huella_diaria')
                ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
                ->select(['fecha', 'huella_diaria'])->distinct()->toBase(),
            'h',
        )->count();

        return ['visitantes_estimados' => $total, 'visitantes_parcial' => $filtro->desde->lt($limite)];
    }

    /**
     * @return list<array{fecha: string, visitas: int, clics: int}>
     */
    public function serie(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $filas = DB::table('paginas_enlaces_diarios')
            ->where('pagina_id', $pagina->id)
            ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
            ->whereIn('tipo', [EventoPagina::VISITA, EventoPagina::CLIC])
            ->groupBy('fecha', 'tipo')
            ->selectRaw('fecha, tipo, SUM(total) as total')
            ->get();

        $mapa = [];

        foreach ($filas as $fila) {
            $mapa[substr((string) $fila->fecha, 0, 10)][$fila->tipo] = (int) $fila->total;
        }

        $serie = [];

        for ($dia = $filtro->desde->startOfDay(); $dia->lte($filtro->hasta); $dia = $dia->addDay()) {
            $f = $dia->toDateString();
            $serie[] = ['fecha' => $f, 'visitas' => $mapa[$f][EventoPagina::VISITA] ?? 0, 'clics' => $mapa[$f][EventoPagina::CLIC] ?? 0];
        }

        return $serie;
    }

    /**
     * Clics por elemento, con su nombre actual (o «Elemento eliminado»).
     *
     * @return list<array{id: string, nombre: string, tipo: string, clics: int, ctr: float}>
     */
    public function elementos(PaginaEnlaces $pagina, FiltroEstadisticas $filtro, int $visitas): array
    {
        $nombres = $this->nombresElementos($pagina);

        return array_values(DB::table('paginas_enlaces_diarios')
            ->where('pagina_id', $pagina->id)
            ->where('tipo', EventoPagina::CLIC)
            ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()])
            ->groupBy('bloque_id')
            ->selectRaw('bloque_id, SUM(total) as total')
            ->havingRaw('SUM(total) > 0')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(fn ($f) => [
                'id' => (string) $f->bloque_id,
                'nombre' => $nombres[$f->bloque_id]['nombre'] ?? 'Elemento eliminado',
                'tipo' => $nombres[$f->bloque_id]['tipo'] ?? 'eliminado',
                'clics' => (int) $f->total,
                'ctr' => $visitas > 0 ? round((int) $f->total / $visitas * 100, 1) : 0.0,
            ])->values()->all());
    }

    /**
     * @return array<string, array{nombre: string, tipo: string}>
     */
    public function nombresElementos(PaginaEnlaces $pagina): array
    {
        $nombres = [];

        foreach ([$pagina->borrador, $pagina->publicado ?? []] as $documento) {
            foreach (DocumentoPagina::recorrer($documento) as ['bloque' => $b]) {
                $nombres[$b['id']] = ['nombre' => DocumentoPagina::nombre($b), 'tipo' => $b['tipo']];

                foreach (in_array($b['tipo'], ['redes', 'carrusel'], true) ? $b['elementos'] : [] as $e) {
                    $nombres[$e['id']] = [
                        'nombre' => $b['tipo'] === 'redes' ? (CatalogoPagina::NOMBRES_REDES[$e['red']] ?? 'Red social') : ($e['alt'] !== '' ? $e['alt'] : 'Imagen del carrusel'),
                        'tipo' => $b['tipo'] === 'redes' ? 'red' : 'imagen',
                    ];
                }
            }
        }

        return $nombres;
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    private function dispositivos(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        return array_values($this->eventos($pagina, $filtro)->groupBy('dispositivo')
            ->selectRaw('dispositivo as clave, COUNT(*) as total')->orderByDesc('total')->get()
            ->map(fn ($f) => [
                'clave' => (string) $f->clave,
                'etiqueta' => CategoriaDispositivo::tryFrom((string) $f->clave)?->etiqueta() ?? 'Desconocido',
                'total' => (int) $f->total,
            ])->values()->all());
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    private function referentes(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        return array_values($this->eventos($pagina, $filtro)->groupBy('referente')
            ->selectRaw('referente as clave, COUNT(*) as total')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($f) => [
                'clave' => (string) ($f->clave ?? 'directo'),
                'etiqueta' => $f->clave !== null ? (string) $f->clave : 'Directo, QR o desconocido',
                'total' => (int) $f->total,
            ])->values()->all());
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int}>
     */
    private function subpaginas(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $titulos = [];

        foreach (DocumentoPagina::paginas($pagina->publicado) as $p) {
            $titulos[(string) ($p['slug'] ?? '')] = (string) ($p['titulo'] ?? '');
        }

        return array_values($this->eventos($pagina, $filtro)->groupBy('subpagina')
            ->selectRaw('subpagina as clave, COUNT(*) as total')->orderByDesc('total')->limit(20)->get()
            ->map(fn ($f) => [
                'clave' => (string) ($f->clave ?? 'inicio'),
                'etiqueta' => $titulos[(string) ($f->clave ?? 'inicio')] ?? (string) ($f->clave ?? 'Inicio'),
                'total' => (int) $f->total,
            ])->values()->all());
    }

    /**
     * @return list<array{hora: int, total: int}>
     */
    private function horas(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): array
    {
        $filas = $this->eventos($pagina, $filtro)->groupBy('hora')->selectRaw('hora, COUNT(*) as total')->pluck('total', 'hora');

        return array_map(fn (int $h) => ['hora' => $h, 'total' => (int) ($filas[$h] ?? 0)], range(0, 23));
    }

    /**
     * Visitas individuales de personas (sin bots) del periodo.
     */
    private function eventos(PaginaEnlaces $pagina, FiltroEstadisticas $filtro): Builder
    {
        return DB::table('paginas_enlaces_eventos')
            ->where('pagina_id', $pagina->id)
            ->where('negocio_id', $pagina->negocio_id)
            ->where('tipo', EventoPagina::VISITA)
            ->where('es_bot', false)
            ->whereBetween('fecha', [$filtro->desde->toDateString(), $filtro->hasta->toDateString()]);
    }
}
