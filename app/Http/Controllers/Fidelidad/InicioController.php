<?php

namespace App\Http\Controllers\Fidelidad;

use App\Models\Fidelidad\Recompensa;
use App\Services\Fidelidad\ResumenFidelidad;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends ControladorFidelidad
{
    public function __invoke(Request $request, ResumenFidelidad $resumen): Response
    {
        $negocio = $this->negocio($request);

        return Inertia::render('cliente/fidelidad/Inicio', [
            ...$this->contexto($request),
            'resumen' => $resumen->paraNegocio($negocio),
            'canjes_recientes' => Recompensa::query()->with(['consumidor:id,nombre,alias', 'canjeador:id,name'])
                ->where('negocio_id', $negocio->id)->whereNotNull('canjeada_en')
                ->latest('canjeada_en')->limit(6)->get()
                ->map(fn (Recompensa $r) => [
                    'id' => $r->id,
                    'descripcion' => $r->descripcion,
                    'consumidor' => $r->consumidor->nombreVisible(),
                    'consumidor_id' => $r->consumidor_id,
                    'por' => $r->canjeador?->name,
                    'en' => $r->canjeada_en?->toIso8601String(),
                ]),
        ]);
    }
}
