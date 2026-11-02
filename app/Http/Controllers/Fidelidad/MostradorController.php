<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Recompensa;
use App\Services\Fidelidad\Codigos;
use App\Services\Fidelidad\FichaConsumidor;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Database\Eloquent\Builder;
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
        ]);
    }

    /**
     * Identifica por código de credencial (escrito, escaneado o la URL
     * completa) o busca por nombre, alias, teléfono o correo.
     */
    public function identificar(Request $request): JsonResponse
    {
        $datos = $request->validate(['q' => ['required', 'string', 'min:2', 'max:255']]);
        $negocio = $this->negocio($request);
        $texto = trim($datos['q']);

        // Una URL completa de /f/{codigo} también sirve.
        $codigo = strtolower((string) preg_replace('#^.*/f/#', '', $texto));

        if (Codigos::esCodigoValido($codigo)) {
            $credencial = Credencial::query()->where('codigo', $codigo)->where('negocio_id', $negocio->id)->first();

            if ($credencial === null) {
                return response()->json(['resultados' => [], 'mensaje' => 'Esta tarjeta no pertenece a tu negocio o no existe.']);
            }

            if (! $credencial->estaActiva()) {
                return response()->json(['resultados' => [], 'mensaje' => 'Esta tarjeta fue revocada. Pide la credencial vigente o busca al consumidor por nombre.']);
            }

            return response()->json([
                'resultados' => [FichaConsumidor::resumen($credencial->consumidor)],
                'credencial' => ['codigo' => $credencial->codigo, 'tipo' => $credencial->tipo->etiqueta()],
            ]);
        }

        $like = '%'.addcslashes($texto, '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $texto);

        $consumidores = Consumidor::query()->deNegocio($negocio->id)->where('estado', Consumidor::ACTIVO)
            ->where(function (Builder $q) use ($like, $digitos, $texto) {
                $q->where('nombre', 'like', $like)->orWhere('alias', 'like', $like)->orWhere('email', mb_strtolower($texto));

                if (strlen((string) $digitos) >= 4) {
                    $q->orWhere('telefono', 'like', '%'.$digitos);
                }
            })
            ->orderBy('nombre')->limit(10)->get();

        return response()->json([
            'resultados' => $consumidores->map(fn (Consumidor $c) => FichaConsumidor::resumen($c))->all(),
            'mensaje' => $consumidores->isEmpty() ? 'No encontramos consumidores con esos datos.' : null,
        ]);
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
