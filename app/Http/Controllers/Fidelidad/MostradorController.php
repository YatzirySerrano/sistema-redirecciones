<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Recompensa;
use App\Services\Fidelidad\BuscadorConsumidores;
use App\Services\Fidelidad\FichaConsumidor;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mostrador: identificar al consumidor (tarjeta, QR, enlace o búsqueda) y,
 * con confirmación explícita del personal, registrar la visita o canjear.
 * Las operaciones responden JSON y requieren conexión (sin modo offline).
 */
class MostradorController extends ControladorFidelidad
{
    public function show(Request $request): Response
    {
        $negocio = $this->negocio($request);

        return Inertia::render('cliente/fidelidad/Mostrador', [
            ...$this->contexto($request),
            'campanas' => Campana::query()->with('vigente')->where('negocio_id', $negocio->id)
                ->where('estado', EstadoCampana::Activa)->orderBy('nombre')->get()
                ->map(fn (Campana $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'meta' => $c->vigente?->meta_visitas,
                    'recompensa' => $c->vigente?->recompensa,
                ]),
            'busqueda' => [
                'espera_ms' => max(100, min(2000, (int) config('dasti.fidelidad.busqueda_espera_ms', 300))),
                'min_caracteres' => BuscadorConsumidores::MIN_CARACTERES,
            ],
        ]);
    }

    /**
     * Búsqueda en tiempo real (código de tarjeta, nombre, alias, teléfono o
     * correo exacto). Solo lectura: nunca registra visitas.
     */
    public function identificar(Request $request, BuscadorConsumidores $buscador): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['required', 'string', 'max:255'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], ['q.required' => 'Escribe un nombre, teléfono o código de tarjeta.']);

        return response()->json($buscador->buscar($this->negocio($request), $datos['q'], (int) ($datos['pagina'] ?? 1)));
    }

    /**
     * Ficha del consumidor elegido en la búsqueda (progreso por campaña y
     * recompensas disponibles). Solo lectura.
     */
    public function consumidor(Request $request, Consumidor $consumidor): JsonResponse
    {
        $this->delNegocio($request, $consumidor);
        abort_unless($consumidor->estaActivo(), 404);

        return response()->json(['consumidor' => FichaConsumidor::resumen($consumidor)]);
    }

    public function visita(Request $request, RegistroVisitas $registro): JsonResponse
    {
        $datos = $request->validate([
            'consumidor_id' => ['required', 'integer'],
            'campana_id' => ['required', 'integer'],
            'operacion_uuid' => ['required', 'uuid'],
            'credencial' => ['nullable', 'string', 'max:40'],
        ]);

        $consumidor = $this->delNegocio($request, Consumidor::query()->findOrFail((int) $datos['consumidor_id']));
        $campana = $this->delNegocio($request, Campana::query()->findOrFail((int) $datos['campana_id']));
        $credencial = isset($datos['credencial'])
            ? Credencial::query()->where('codigo', strtolower($datos['credencial']))->where('negocio_id', $consumidor->negocio_id)->first()
            : null;

        $resultado = $registro->registrarVisita($this->usuario($request), $consumidor, $campana, strtolower($datos['operacion_uuid']), $credencial);

        return response()->json([
            'repetida' => $resultado['repetida'],
            'mensaje' => match (true) {
                $resultado['repetida'] => 'Esta visita ya estaba registrada; no se sumó de nuevo.',
                $resultado['recompensa'] !== null => '¡Meta completada! Recompensa disponible: '.$resultado['recompensa']->descripcion.'.',
                default => 'Visita registrada.',
            },
            'consumidor' => FichaConsumidor::resumen($consumidor->fresh() ?? $consumidor),
        ]);
    }

    public function canje(Request $request, Recompensa $recompensa, RegistroVisitas $registro): JsonResponse
    {
        $this->delNegocio($request, $recompensa);
        $datos = $request->validate(['operacion_uuid' => ['required', 'uuid']]);

        $resultado = $registro->canjear($this->usuario($request), $recompensa, strtolower($datos['operacion_uuid']));

        return response()->json([
            'repetida' => $resultado['repetida'],
            'mensaje' => $resultado['repetida'] ? 'El canje ya estaba registrado.' : 'Recompensa canjeada: '.$recompensa->descripcion.'.',
            'consumidor' => FichaConsumidor::resumen($recompensa->consumidor->fresh() ?? $recompensa->consumidor),
        ]);
    }
}
