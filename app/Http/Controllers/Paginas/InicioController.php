<?php

namespace App\Http\Controllers\Paginas;

use App\Models\Paginas\MensajePagina;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Paginas\DocumentoPagina;
use App\Services\Paginas\EstadisticasPagina;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio del módulo: estado, dirección pública, QR y accesos rápidos.
 */
class InicioController extends ControladorPaginas
{
    public function __invoke(Request $request, EstadisticasPagina $estadisticas): Response
    {
        $pagina = $this->pagina($request);
        $contexto = $this->contexto($request);

        return Inertia::render('cliente/paginas/Inicio', [
            ...$contexto,
            'pagina' => $this->resumenPagina($pagina),
            'totales' => $estadisticas->totales($pagina, FiltroEstadisticas::ultimosDias(30)),
            'mensajes_sin_leer' => $contexto['puede']['mensajes']
                ? MensajePagina::query()->where('negocio_id', $pagina->negocio_id)->whereNull('leido_en')->count()
                : null,
            'dominios' => $pagina->dominios->map(fn ($d) => [
                'id' => $d->id, 'host' => $d->host_unicode, 'estado' => $d->estado->value, 'estado_etiqueta' => $d->estado->etiqueta(), 'es_principal' => $d->es_principal,
            ])->values(),
            'elementos' => DocumentoPagina::contarElementos($pagina->publicado),
        ]);
    }
}
