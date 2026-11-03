<?php

namespace App\Services\Fidelidad\Diseno;

use App\Enums\EstadoRecompensa;
use App\Models\ArchivoPublico;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Diseno;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Support\Collection;

/**
 * Datos de la página pública del consumidor (/f/{codigo} y
 * /mi-fidelidad/{token}), con el diseño PUBLICADO del negocio.
 *
 * Solo incluye lo mínimo y no sensible: nombre visible (alias o primer
 * nombre), nombre del negocio, progreso real por campaña (visitas del ciclo
 * y meta de la versión de reglas de cada participación) y recompensas
 * disponibles. Nunca teléfono, correo, identificadores internos ni datos de
 * otros consumidores. Es de solo lectura.
 */
class PresentacionPublica
{
    public function __construct(private readonly AccesoServicios $acceso) {}

    /**
     * @return array{datos: array<string, mixed>, fuentes: string|null, titulo: string}
     */
    public function paraConsumidor(Consumidor $consumidor): array
    {
        $consumidor->loadMissing(['negocio', 'participaciones.campana', 'participaciones.version']);
        $negocio = $consumidor->negocio;
        $disenos = Diseno::query()->where('negocio_id', $negocio->id)->whereNotNull('publicado')->get();
        $general = $disenos->firstWhere('campana_clave', 0)->publicado ?? DocumentoDiseno::predeterminado();

        $participaciones = $consumidor->participaciones->filter(fn (Participacion $p) => $p->estado !== Participacion::FUSIONADA)->values();
        $recompensas = Recompensa::query()->where('consumidor_id', $consumidor->id)
            ->whereIn('participacion_id', $participaciones->pluck('id'))
            ->orderBy('created_at')->get();

        $variantes = [];
        $programas = $participaciones
            ->sortBy(fn (Participacion $p) => $p->campana->nombre)
            ->map(function (Participacion $p) use ($recompensas, $disenos, &$variantes) {
                $variante = $disenos->firstWhere('campana_clave', $p->campana_id);
                $indice = null;

                if ($variante !== null) {
                    $indice = count($variantes);
                    $variantes[] = $variante->publicado;
                }

                return self::programa($p, $recompensas->where('participacion_id', $p->id)->values(), $indice);
            })->values()->all();

        $estado = $this->acceso->fidelidad($negocio);
        $documentos = [$general, ...$variantes];

        return [
            'titulo' => $negocio->nombre_comercial,
            'fuentes' => DocumentoDiseno::urlFuentes($documentos),
            'datos' => [
                'negocio' => $negocio->nombre_comercial,
                'consumidor' => $consumidor->nombreVisible(),
                'programas' => $programas,
                'aviso' => $estado['activo'] && $negocio->estaActivo()
                    ? null
                    : 'Por ahora el negocio no está registrando visitas en este programa. Tu progreso se conserva.',
                'disenos' => ['general' => $general, 'variantes' => $variantes],
                'medios' => self::medios($negocio, $documentos),
                'recuperar_url' => route('fidelidad.publico.recuperar', absolute: false),
            ],
        ];
    }

    /**
     * Mapa uuid => URL de las imágenes que existen (las eliminadas se
     * omiten y el diseño usa su respaldo de color).
     *
     * @param  list<array<string, mixed>|null>  $documentos
     * @return array<string, string>
     */
    public static function medios(Negocio $negocio, array $documentos): array
    {
        $uuids = array_values(array_unique(array_merge(...array_map(fn ($d) => DocumentoDiseno::imagenes($d), $documentos ?: [[]]))));

        if ($uuids === []) {
            return [];
        }

        return ArchivoPublico::query()->where('negocio_id', $negocio->id)
            ->where('modulo', ArchivoPublico::MODULO_FIDELIDAD)->where('tipo', ArchivoPublico::TIPO_IMAGEN)
            ->whereIn('uuid', $uuids)->get()
            ->mapWithKeys(fn (ArchivoPublico $a) => [$a->uuid => $a->url()])->all();
    }

    /**
     * @param  Collection<int, Recompensa>  $recompensas
     * @return array<string, mixed>
     */
    private static function programa(Participacion $p, Collection $recompensas, ?int $variante): array
    {
        $meta = $p->version->meta_visitas;
        $visitas = min($p->visitas_ciclo, $meta);
        $disponibles = $recompensas->filter(fn (Recompensa $r) => $r->estado === EstadoRecompensa::Disponible
            && ($r->vence_en === null || $r->vence_en->isFuture()));
        $ultima = $recompensas->sortByDesc('created_at')->first();
        $canjeadaReciente = $ultima !== null && $ultima->estado === EstadoRecompensa::Canjeada
            && ($p->ultima_visita_en === null || $ultima->canjeada_en === null || $ultima->canjeada_en >= $p->ultima_visita_en);

        return [
            'campana' => $p->campana->nombre,
            'visitas' => $visitas,
            'meta' => $meta,
            'faltan' => max(0, $meta - $visitas),
            'recompensa' => $p->version->recompensa,
            'estado' => match (true) {
                $disponibles->isNotEmpty() => 'disponible',
                $canjeadaReciente => 'canjeada',
                $p->estado === Participacion::COMPLETADA => 'completada',
                default => 'en_progreso',
            },
            'recompensas' => $disponibles->map(fn (Recompensa $r) => [
                'descripcion' => $r->descripcion,
                'vence_en' => $r->vence_en?->format('d/m/Y'),
            ])->values()->all(),
            'variante' => $variante,
        ];
    }
}
