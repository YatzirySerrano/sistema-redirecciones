<?php

namespace App\Services\Paginas;

use App\Enums\EstadoPagina;
use App\Models\Negocio;
use App\Models\Paginas\AliasPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Paginas\VersionPagina;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto que modifica Páginas de enlaces: borrador con control de
 * concurrencia optimista (version), publicación, historial, restauración y
 * cambio de dirección. Las operaciones relevantes se auditan.
 */
class GestorPaginas
{
    /** Minutos entre copias automáticas del borrador en el historial. */
    private const MINUTOS_ENTRE_COPIAS = 30;

    public function __construct(
        private readonly DocumentoPagina $documento,
        private readonly ReglasSlug $slugs,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * Página del negocio; la crea con un documento inicial y una dirección
     * derivada del nombre la primera vez que se abre el módulo.
     */
    public function paraNegocio(Negocio $negocio): PaginaEnlaces
    {
        $existente = PaginaEnlaces::query()->where('negocio_id', $negocio->id)->first();

        if ($existente !== null) {
            return $existente;
        }

        for ($intento = 0; $intento < 3; $intento++) {
            try {
                return PaginaEnlaces::query()->create([
                    'negocio_id' => $negocio->id,
                    'slug' => $this->slugs->sugerir($negocio),
                    'estado' => EstadoPagina::Borrador,
                    'borrador' => $this->documento->inicial($negocio),
                    'version' => 1,
                ]);
            } catch (QueryException $e) {
                // Dos pestañas abrieron el módulo a la vez o el slug se ocupó.
                $existente = PaginaEnlaces::query()->where('negocio_id', $negocio->id)->first();

                if ($existente !== null) {
                    return $existente;
                }

                if ($intento === 2) {
                    throw $e;
                }
            }
        }

        return PaginaEnlaces::query()->where('negocio_id', $negocio->id)->firstOrFail();
    }

    /**
     * @throws ConflictoVersion
     * @throws ValidationException
     */
    public function guardarBorrador(PaginaEnlaces $pagina, mixed $documento, int $version, User $usuario): PaginaEnlaces
    {
        $saneado = $this->documento->sanear($documento, $pagina->negocio, DocumentoPagina::MODO_BORRADOR);

        return DB::transaction(function () use ($pagina, $saneado, $version, $usuario) {
            $bloqueada = $this->bloquear($pagina, $version);

            $bloqueada->forceFill([
                'borrador' => $saneado,
                'version' => $bloqueada->version + 1,
                'borrador_actualizado_en' => now(),
                'borrador_actualizado_por' => $usuario->id,
            ])->save();

            $ultima = VersionPagina::query()->where('pagina_id', $bloqueada->id)->latest('id')->first();

            if ($ultima === null || $ultima->created_at === null || $ultima->created_at->lt(now()->subMinutes(self::MINUTOS_ENTRE_COPIAS))) {
                $this->instantanea($bloqueada, 'guardado', $saneado, $usuario);
            }

            return $bloqueada;
        });
    }

    /**
     * Publica exactamente la versión que el usuario está viendo.
     *
     * @throws ConflictoVersion
     * @throws ValidationException
     */
    public function publicar(PaginaEnlaces $pagina, int $version, User $usuario): PaginaEnlaces
    {
        try {
            $publicado = $this->documento->sanear($pagina->borrador, $pagina->negocio, DocumentoPagina::MODO_PUBLICACION);
        } catch (ValidationException $e) {
            $pagina->forceFill(['incidencia' => 'Publicación rechazada: '.count($e->errors()).' elemento(s) incompleto(s) o inválido(s).', 'incidencia_en' => now()])->save();

            throw $e;
        }

        return DB::transaction(function () use ($pagina, $publicado, $version, $usuario) {
            $bloqueada = $this->bloquear($pagina, $version);
            $anterior = $bloqueada->estado;

            $bloqueada->forceFill([
                'publicado' => $publicado,
                'borrador' => $publicado,
                'estado' => EstadoPagina::Publicada,
                'publicado_en' => now(),
                'publicado_por' => $usuario->id,
                'incidencia' => null,
                'incidencia_en' => null,
            ])->save();

            $this->instantanea($bloqueada, 'publicacion', $publicado, $usuario);

            $this->auditoria->registrar(
                accion: 'pagina.publicada',
                descripcion: 'Página de enlaces publicada',
                recurso: $bloqueada,
                negocio: $bloqueada->negocio_id,
                anteriores: ['estado' => $anterior->value],
                nuevos: ['estado' => EstadoPagina::Publicada->value, 'version' => $bloqueada->version],
                actor: $usuario,
            );

            return $bloqueada;
        });
    }

    public function despublicar(PaginaEnlaces $pagina, User $usuario): PaginaEnlaces
    {
        return DB::transaction(function () use ($pagina, $usuario) {
            /** @var PaginaEnlaces $bloqueada */
            $bloqueada = PaginaEnlaces::query()->whereKey($pagina->id)->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoPagina::Publicada) {
                return $bloqueada;
            }

            $bloqueada->forceFill(['estado' => EstadoPagina::Despublicada, 'despublicado_en' => now()])->save();

            $this->auditoria->registrar(
                accion: 'pagina.despublicada',
                descripcion: 'Página de enlaces despublicada',
                recurso: $bloqueada,
                negocio: $bloqueada->negocio_id,
                anteriores: ['estado' => EstadoPagina::Publicada->value],
                nuevos: ['estado' => EstadoPagina::Despublicada->value],
                actor: $usuario,
            );

            return $bloqueada;
        });
    }

    /**
     * Copia una versión del historial al borrador (no publica). Antes se
     * guarda el borrador actual para poder volver a él.
     *
     * @throws ConflictoVersion
     */
    public function restaurar(PaginaEnlaces $pagina, VersionPagina $copia, int $version, User $usuario): PaginaEnlaces
    {
        abort_unless($copia->pagina_id === $pagina->id, 404);

        // Las imágenes eliminadas desde entonces se descartan sin error.
        $documento = $this->documento->sanear($copia->documento, $pagina->negocio, DocumentoPagina::MODO_TOLERANTE);

        return DB::transaction(function () use ($pagina, $copia, $documento, $version, $usuario) {
            $bloqueada = $this->bloquear($pagina, $version);
            $this->instantanea($bloqueada, 'antes_de_restaurar', $bloqueada->borrador, $usuario);

            $bloqueada->forceFill([
                'borrador' => $documento,
                'version' => $bloqueada->version + 1,
                'borrador_actualizado_en' => now(),
                'borrador_actualizado_por' => $usuario->id,
            ])->save();

            $this->instantanea($bloqueada, 'restauracion', $documento, $usuario);

            $this->auditoria->registrar(
                accion: 'pagina.version_restaurada',
                descripcion: 'Versión anterior de la página restaurada en el borrador',
                recurso: $bloqueada,
                negocio: $bloqueada->negocio_id,
                nuevos: ['version_restaurada' => $copia->id, 'motivo_original' => $copia->motivo],
                actor: $usuario,
            );

            return $bloqueada;
        });
    }

    /**
     * Cambia la dirección pública. La anterior queda como alias (redirige
     * con 301 y nadie más puede ocuparla).
     *
     * @throws ValidationException
     */
    public function cambiarSlug(PaginaEnlaces $pagina, string $nuevo, User $usuario): PaginaEnlaces
    {
        $nuevo = $this->slugs->normalizar($nuevo);

        if ($nuevo === $pagina->slug) {
            return $pagina;
        }

        if (($error = $this->slugs->error($nuevo, $pagina)) !== null) {
            throw ValidationException::withMessages(['slug' => $error]);
        }

        $horas = (int) config('dasti.paginas.slug.horas_entre_cambios', 24);

        if ($pagina->slug_cambiado_en !== null && $pagina->slug_cambiado_en->gt(now()->subHours($horas))) {
            throw ValidationException::withMessages(['slug' => "Solo puedes cambiar la dirección una vez cada {$horas} horas. Inténtalo más tarde."]);
        }

        return DB::transaction(function () use ($pagina, $nuevo, $usuario) {
            /** @var PaginaEnlaces $bloqueada */
            $bloqueada = PaginaEnlaces::query()->whereKey($pagina->id)->lockForUpdate()->firstOrFail();
            $anterior = $bloqueada->slug;

            // Recuperar una dirección anterior propia: deja de ser alias.
            AliasPagina::query()->where('pagina_id', $bloqueada->id)->where('slug', $nuevo)->delete();

            $maxAlias = (int) config('dasti.paginas.slug.max_alias', 10);
            $alias = AliasPagina::query()->where('pagina_id', $bloqueada->id)->oldest('retirado_en')->get();

            if ($alias->count() >= $maxAlias) {
                $masAntiguo = $alias->first();
                $reserva = (int) config('dasti.paginas.slug.dias_reserva_alias', 90);

                if ($masAntiguo !== null && $masAntiguo->retirado_en->gt(now()->subDays($reserva))) {
                    throw ValidationException::withMessages(['slug' => 'Cambiaste la dirección demasiadas veces. Para proteger a tus visitantes, espera unos días o contacta a DASTI.']);
                }

                $masAntiguo?->delete();
            }

            AliasPagina::query()->create([
                'pagina_id' => $bloqueada->id,
                'negocio_id' => $bloqueada->negocio_id,
                'slug' => $anterior,
                'retirado_en' => now(),
            ]);

            $bloqueada->forceFill(['slug' => $nuevo, 'slug_cambiado_en' => now()])->save();

            $this->auditoria->registrar(
                accion: 'pagina.direccion_cambiada',
                descripcion: "Dirección de la página cambiada de «{$anterior}» a «{$nuevo}»",
                recurso: $bloqueada,
                negocio: $bloqueada->negocio_id,
                anteriores: ['slug' => $anterior],
                nuevos: ['slug' => $nuevo],
                actor: $usuario,
            );

            return $bloqueada;
        });
    }

    /**
     * @throws ConflictoVersion
     */
    private function bloquear(PaginaEnlaces $pagina, int $version): PaginaEnlaces
    {
        /** @var PaginaEnlaces $bloqueada */
        $bloqueada = PaginaEnlaces::query()->with('editor:id,name')->whereKey($pagina->id)->lockForUpdate()->firstOrFail();

        if ($bloqueada->version !== $version) {
            throw new ConflictoVersion(
                $bloqueada->version,
                $bloqueada->editor?->name,
                $bloqueada->borrador_actualizado_en?->toIso8601String(),
            );
        }

        return $bloqueada;
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function instantanea(PaginaEnlaces $pagina, string $motivo, array $documento, ?User $usuario): void
    {
        VersionPagina::query()->create([
            'pagina_id' => $pagina->id,
            'negocio_id' => $pagina->negocio_id,
            'motivo' => $motivo,
            'documento' => $documento,
            'version_borrador' => $pagina->version,
            'autor_id' => $usuario?->id,
            'created_at' => now(),
        ]);

        $conservar = (int) config('dasti.paginas.limites.versiones', 30);
        $limite = VersionPagina::query()->where('pagina_id', $pagina->id)->orderByDesc('id')->skip($conservar)->value('id');

        if ($limite !== null) {
            VersionPagina::query()->where('pagina_id', $pagina->id)->where('id', '<=', $limite)->delete();
        }
    }
}
