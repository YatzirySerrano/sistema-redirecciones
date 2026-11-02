<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\Fidelidad\Campana;
use App\Services\Fidelidad\GestorCampanas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampanaController extends ControladorFidelidad
{
    public function index(Request $request): Response
    {
        $negocio = $this->negocio($request);

        return Inertia::render('cliente/fidelidad/campanas/Index', [
            ...$this->contexto($request),
            'campanas' => Campana::query()->with('vigente')->withCount('participaciones')
                ->where('negocio_id', $negocio->id)->orderByRaw("estado = 'activa' desc")->orderBy('nombre')->get()
                ->map(fn (Campana $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'estado' => $c->estado->value,
                    'estado_etiqueta' => $c->estado->etiqueta(),
                    'version' => $c->version_actual,
                    'participantes' => $c->participaciones_count,
                    'reglas' => $c->vigente?->reglas(),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('cliente/fidelidad/campanas/Form', [...$this->contexto($request), 'campana' => null]);
    }

    public function store(Request $request, GestorCampanas $gestor): RedirectResponse
    {
        $campana = $gestor->crear($this->negocio($request), $this->validar($request), $this->usuario($request));
        $this->notificar("Campaña «{$campana->nombre}» creada.");

        return redirect()->route('cliente.fidelidad.campanas.index');
    }

    public function edit(Request $request, Campana $campana): Response
    {
        $this->delNegocio($request, $campana);
        $campana->load('vigente')->loadCount('participaciones');

        return Inertia::render('cliente/fidelidad/campanas/Form', [
            ...$this->contexto($request),
            'campana' => [
                'id' => $campana->id,
                'nombre' => $campana->nombre,
                'version' => $campana->version_actual,
                'participantes' => $campana->participaciones_count,
                'reglas' => $campana->vigente?->reglas(),
            ],
        ]);
    }

    public function update(Request $request, Campana $campana, GestorCampanas $gestor): RedirectResponse
    {
        $this->delNegocio($request, $campana);
        $gestor->actualizar($campana, $this->validar($request), $this->usuario($request));
        $this->notificar('Campaña actualizada. Los participantes actuales conservan las condiciones con las que se inscribieron.');

        return redirect()->route('cliente.fidelidad.campanas.index');
    }

    public function estado(Request $request, Campana $campana, GestorCampanas $gestor): RedirectResponse
    {
        $this->delNegocio($request, $campana);
        $datos = $request->validate(['estado' => ['required', Rule::enum(EstadoCampana::class)]]);
        $gestor->cambiarEstado($campana, EstadoCampana::from($datos['estado']), $this->usuario($request));
        $this->notificar('Estado de la campaña actualizado.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $request->merge(['repetir_ciclo' => $request->boolean('repetir_ciclo')]);

        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'meta_visitas' => ['required', 'integer', 'between:2,100'],
            'recompensa' => ['required', 'string', 'min:3', 'max:160'],
            'vigencia_visitas_dias' => ['nullable', 'integer', 'between:1,1095'],
            'vigencia_recompensa_dias' => ['nullable', 'integer', 'between:1,1095'],
            'inicia_en' => ['required', 'date_format:Y-m-d'],
            'termina_en' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicia_en'],
            'limite_participantes' => ['nullable', 'integer', 'between:1,1000000'],
            'max_visitas_por_dia' => ['required', 'integer', 'between:1,10'],
            'intervalo_minimo_minutos' => ['required', 'integer', 'between:0,1440'],
            'repetir_ciclo' => ['boolean'],
        ], [], [
            'meta_visitas' => 'meta de visitas',
            'vigencia_visitas_dias' => 'vigencia de visitas',
            'vigencia_recompensa_dias' => 'vigencia de la recompensa',
            'inicia_en' => 'fecha de inicio',
            'termina_en' => 'fecha de finalización',
            'limite_participantes' => 'límite de participantes',
            'max_visitas_por_dia' => 'visitas máximas por día',
            'intervalo_minimo_minutos' => 'intervalo mínimo',
        ]);
    }
}
