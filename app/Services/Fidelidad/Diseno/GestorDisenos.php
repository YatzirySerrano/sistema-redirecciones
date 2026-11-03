<?php

namespace App\Services\Fidelidad\Diseno;

use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Diseno;
use App\Models\Fidelidad\DisenoVersion;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Guardar, publicar y restaurar diseños de la tarjeta digital.
 *
 * Solo escribe en `fidelidad_disenos` y `fidelidad_diseno_versiones`: nunca
 * toca consumidores, participaciones, movimientos ni recompensas.
 *
 * Concurrencia: cada escritura bloquea la fila (`FOR UPDATE`) y exige la
 * `version` con la que el editor empezó; si otra pestaña o persona guardó
 * antes, responde 409 sin sobrescribir.
 */
class GestorDisenos
{
    public const MAX_VERSIONES = 30;

    public function __construct(
        private readonly DocumentoDiseno $documentos,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function obtener(Negocio $negocio, ?Campana $campana): ?Diseno
    {
        return Diseno::query()->where('negocio_id', $negocio->id)->where('campana_clave', $campana->id ?? 0)->first();
    }

    /**
     * @return array{diseno: Diseno, avisos: list<string>}
     */
    public function guardarBorrador(Negocio $negocio, ?Campana $campana, mixed $entrada, int $versionEsperada, User $usuario): array
    {
        $limpio = $this->documentos->sanear($entrada, $negocio);

        $diseno = $this->transaccion(function () use ($negocio, $campana, $limpio, $versionEsperada, $usuario) {
            $diseno = $this->bloquear($negocio, $campana, $versionEsperada);

            $diseno->forceFill([
                'borrador' => $limpio['documento'],
                'version' => $diseno->exists ? $diseno->version + 1 : 1,
                'actualizado_por' => $usuario->id,
            ])->save();

            return $diseno;
        });

        return ['diseno' => $diseno, 'avisos' => $limpio['avisos']];
    }

    /**
     * Guarda el documento y lo publica en una sola operación.
     *
     * @return array{diseno: Diseno, avisos: list<string>}
     */
    public function publicar(Negocio $negocio, ?Campana $campana, mixed $entrada, int $versionEsperada, User $usuario): array
    {
        $limpio = $this->documentos->sanear($entrada, $negocio);

        $diseno = $this->transaccion(function () use ($negocio, $campana, $limpio, $versionEsperada, $usuario) {
            $diseno = $this->bloquear($negocio, $campana, $versionEsperada);
            $anterior = $diseno->publicado;

            $diseno->forceFill([
                'borrador' => $limpio['documento'],
                'publicado' => $limpio['documento'],
                'publicado_en' => now(),
                'publicado_por' => $usuario->id,
                'version' => $diseno->exists ? $diseno->version + 1 : 1,
                'actualizado_por' => $usuario->id,
            ])->save();

            $this->historial($diseno, DisenoVersion::PUBLICACION, $limpio['documento'], $usuario);

            $this->auditoria->registrar(
                accion: 'fidelidad.diseno_publicado',
                descripcion: $campana ? "Diseño de la tarjeta publicado para «{$campana->nombre}»" : 'Diseño de la tarjeta publicado',
                recurso: $diseno,
                negocio: $negocio,
                anteriores: ['tenia_publicado' => $anterior !== null],
                nuevos: ['plantilla' => $limpio['documento']['plantilla'], 'presentacion' => $limpio['documento']['progreso']['presentacion']],
                actor: $usuario,
            );

            return $diseno;
        });

        return ['diseno' => $diseno, 'avisos' => $limpio['avisos']];
    }

    /**
     * Copia una versión del historial al borrador (no publica). Antes guarda
     * un respaldo del borrador actual para poder deshacer la restauración.
     *
     * @return array{diseno: Diseno, avisos: list<string>}
     */
    public function restaurar(Diseno $diseno, DisenoVersion $version, int $versionEsperada, User $usuario): array
    {
        $negocio = $diseno->negocio;
        $limpio = $this->documentos->sanear($version->documento, $negocio);

        $diseno = DB::transaction(function () use ($diseno, $limpio, $versionEsperada, $usuario, $version) {
            /** @var Diseno $diseno */
            $diseno = Diseno::query()->lockForUpdate()->findOrFail($diseno->id);
            $this->exigirVersion($diseno, $versionEsperada);

            if ($diseno->borrador != $limpio['documento'] && $diseno->borrador != $diseno->publicado) {
                $this->historial($diseno, DisenoVersion::RESPALDO, $diseno->borrador, $usuario);
            }

            $diseno->forceFill([
                'borrador' => $limpio['documento'],
                'version' => $diseno->version + 1,
                'actualizado_por' => $usuario->id,
            ])->save();

            $this->auditoria->registrar(
                accion: 'fidelidad.diseno_restaurado',
                descripcion: 'Versión anterior del diseño de la tarjeta restaurada en el borrador',
                recurso: $diseno,
                negocio: $diseno->negocio_id,
                nuevos: ['version_restaurada' => $version->id],
                actor: $usuario,
            );

            return $diseno;
        });

        return ['diseno' => $diseno, 'avisos' => $limpio['avisos']];
    }

    /**
     * Quita la variante de una campaña: vuelve a usar el diseño general.
     */
    public function eliminarVariante(Diseno $diseno, User $usuario): void
    {
        abort_if($diseno->campana_id === null, 404);

        DB::transaction(function () use ($diseno, $usuario) {
            $diseno->delete();

            $this->auditoria->registrar(
                accion: 'fidelidad.diseno_variante_eliminada',
                descripcion: 'Variante de diseño de campaña eliminada (se usa el diseño general)',
                recurso: $diseno,
                negocio: $diseno->negocio_id,
                anteriores: ['campana_id' => $diseno->campana_id],
                actor: $usuario,
            );
        });
    }

    // ---------------------------------------------------------------

    /**
     * Dos primeros guardados simultáneos: el índice único detiene al
     * segundo, que recibe el mismo aviso de conflicto.
     *
     * @param  Closure(): Diseno  $operacion
     */
    private function transaccion(Closure $operacion): Diseno
    {
        try {
            return DB::transaction($operacion);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Otra persona (u otra pestaña) guardó este diseño al mismo tiempo. Recarga la página para continuar.');
        }
    }

    private function bloquear(Negocio $negocio, ?Campana $campana, int $versionEsperada): Diseno
    {
        /** @var Diseno|null $diseno */
        $diseno = Diseno::query()->where('negocio_id', $negocio->id)
            ->where('campana_clave', $campana->id ?? 0)->lockForUpdate()->first();

        if ($diseno === null) {
            // Primer guardado: el editor parte de la versión 0.
            if ($versionEsperada !== 0) {
                throw new ConflictHttpException('El diseño cambió mientras lo editabas. Recarga la página para ver la versión más reciente.');
            }

            return new Diseno([
                'negocio_id' => $negocio->id,
                'campana_id' => $campana?->id,
                'campana_clave' => $campana->id ?? 0,
            ]);
        }

        $this->exigirVersion($diseno, $versionEsperada);

        return $diseno;
    }

    private function exigirVersion(Diseno $diseno, int $versionEsperada): void
    {
        if ($diseno->version !== $versionEsperada) {
            throw new ConflictHttpException('Otra persona (u otra pestaña) guardó cambios en este diseño. Recarga la página para continuar con la versión más reciente; tus cambios no se guardaron.');
        }
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function historial(Diseno $diseno, string $motivo, array $documento, User $usuario): void
    {
        DisenoVersion::query()->create([
            'diseno_id' => $diseno->id,
            'negocio_id' => $diseno->negocio_id,
            'motivo' => $motivo,
            'documento' => $documento,
            'creado_por' => $usuario->id,
        ]);

        $conservar = DisenoVersion::query()->where('diseno_id', $diseno->id)
            ->orderByDesc('id')->limit(self::MAX_VERSIONES)->pluck('id');

        DisenoVersion::query()->where('diseno_id', $diseno->id)->whereNotIn('id', $conservar)->delete();
    }
}
