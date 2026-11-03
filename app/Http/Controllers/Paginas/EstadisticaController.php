<?php

namespace App\Http\Controllers\Paginas;

use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Paginas\EstadisticasPagina;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Estadísticas de la página del negocio y exportación CSV. Solo datos del
 * propio negocio.
 */
class EstadisticaController extends ControladorPaginas
{
    public function index(Request $request, EstadisticasPagina $estadisticas): Response
    {
        $pagina = $this->pagina($request);
        $filtro = FiltroEstadisticas::desdePeticion($request);

        return Inertia::render('cliente/paginas/Estadisticas', [
            ...$this->contexto($request),
            'pagina' => $this->resumenPagina($pagina),
            'filtros' => $filtro->aArreglo(),
            ...$estadisticas->resumen($pagina, $filtro),
        ]);
    }

    public function exportar(Request $request, EstadisticasPagina $estadisticas): StreamedResponse
    {
        $pagina = $this->pagina($request);
        $filtro = FiltroEstadisticas::desdePeticion($request);
        $resumen = $estadisticas->resumen($pagina, $filtro);
        $nombre = 'estadisticas-'.$pagina->slug.'-'.$filtro->desde->toDateString().'-a-'.$filtro->hasta->toDateString().'.csv';

        return response()->streamDownload(function () use ($resumen) {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                return;
            }

            // BOM para que Excel reconozca UTF-8 (acentos).
            fwrite($salida, "\xEF\xBB\xBF");
            $fila = fn (array $valores) => fputcsv($salida, array_map(fn ($v) => $this->celda($v), $valores), escape: '');

            $fila(['Resumen']);
            $fila(['Visitas', 'Clics', 'Mensajes de contacto', 'Tasa de clics (%)', 'Visitantes estimados']);
            $fila([$resumen['totales']['visitas'], $resumen['totales']['clics'], $resumen['totales']['contactos'], $resumen['totales']['ctr'], $resumen['totales']['visitantes_estimados'] ?? 'No disponible']);
            $fila([]);
            $fila(['Fecha', 'Visitas', 'Clics']);

            foreach ($resumen['serie'] as $punto) {
                $fila([$punto['fecha'], $punto['visitas'], $punto['clics']]);
            }

            $fila([]);
            $fila(['Elemento', 'Tipo', 'Clics', 'Tasa de clics (%)']);

            foreach ($resumen['elementos'] as $e) {
                $fila([$e['nombre'], $e['tipo'], $e['clics'], $e['ctr']]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * Evita la inyección de fórmulas al abrir el CSV en una hoja de cálculo.
     */
    private function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        return $texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($texto) ? "'".$texto : $texto;
    }
}
