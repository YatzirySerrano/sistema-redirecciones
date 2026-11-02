<?php

namespace App\Services\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\CampanaVersion;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;

/**
 * Campañas con versiones inmutables: modificar las reglas crea una versión
 * nueva para los consumidores que se inscriban después; quienes ya
 * participan conservan las condiciones que se les prometieron.
 */
class GestorCampanas
{
    private const REGLAS = [
        'descripcion', 'meta_visitas', 'recompensa', 'vigencia_visitas_dias', 'vigencia_recompensa_dias',
        'inicia_en', 'termina_en', 'limite_participantes', 'max_visitas_por_dia', 'intervalo_minimo_minutos', 'repetir_ciclo',
    ];

    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Negocio $negocio, array $datos, User $actor): Campana
    {
        return DB::transaction(function () use ($negocio, $datos, $actor) {
            $campana = Campana::query()->create([
                'negocio_id' => $negocio->id,
                'nombre' => $datos['nombre'],
                'estado' => EstadoCampana::Activa,
                'version_actual' => 1,
            ]);
            $version = $campana->versiones()->create([
                ...array_intersect_key($datos, array_flip(self::REGLAS)),
                'version' => 1,
                'creado_por' => $actor->id,
            ]);

            $this->auditoria->registrar(
                accion: 'fidelidad.campana_creada',
                descripcion: "Alta de la campaña «{$campana->nombre}»",
                recurso: $campana,
                negocio: $negocio,
                nuevos: $version->reglas(),
                actor: $actor,
            );

            return $campana;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Campana $campana, array $datos, User $actor): Campana
    {
        return DB::transaction(function () use ($campana, $datos, $actor) {
            $campana = Campana::query()->lockForUpdate()->findOrFail($campana->id);
            /** @var CampanaVersion $actual */
            $actual = $campana->versiones()->where('version', $campana->version_actual)->firstOrFail();

            $nuevas = array_intersect_key($datos, array_flip(self::REGLAS));
            $anteriores = $actual->reglas();
            $cambios = array_filter($nuevas, fn ($v, $k) => ($anteriores[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);

            if (($datos['nombre'] ?? $campana->nombre) !== $campana->nombre) {
                $campana->nombre = $datos['nombre'];
            }

            if ($cambios !== []) {
                $campana->version_actual++;
                $campana->versiones()->create([...$anteriores, ...$nuevas, 'version' => $campana->version_actual, 'creado_por' => $actor->id]);
            }

            if (! $campana->isDirty()) {
                return $campana;
            }

            $campana->save();
            $this->auditoria->registrar(
                accion: 'fidelidad.campana_actualizada',
                descripcion: $cambios === []
                    ? "Campaña «{$campana->nombre}» renombrada"
                    : "Nueva versión {$campana->version_actual} de la campaña «{$campana->nombre}» (aplica a nuevas inscripciones)",
                recurso: $campana,
                negocio: $campana->negocio_id,
                anteriores: array_intersect_key($anteriores, $cambios) ?: null,
                nuevos: $cambios ?: ['nombre' => $campana->nombre],
                actor: $actor,
            );

            return $campana;
        });
    }

    public function cambiarEstado(Campana $campana, EstadoCampana $estado, User $actor): Campana
    {
        if ($campana->estado === $estado) {
            return $campana;
        }

        $anterior = $campana->estado;
        $campana->forceFill(['estado' => $estado])->save();
        $this->auditoria->registrar(
            accion: 'fidelidad.campana_estado',
            descripcion: "Campaña «{$campana->nombre}»: {$anterior->etiqueta()} → {$estado->etiqueta()}",
            recurso: $campana,
            negocio: $campana->negocio_id,
            anteriores: ['estado' => $anterior->value],
            nuevos: ['estado' => $estado->value],
            actor: $actor,
        );

        return $campana;
    }
}
