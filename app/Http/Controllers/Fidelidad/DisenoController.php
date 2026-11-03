<?php

namespace App\Http\Controllers\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\ArchivoPublico;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Diseno;
use App\Models\Fidelidad\DisenoVersion;
use App\Models\Negocio;
use App\Services\Fidelidad\Diseno\DocumentoDiseno;
use App\Services\Fidelidad\Diseno\GestorDisenos;
use App\Services\Fidelidad\Diseno\PlantillasDiseno;
use App\Services\Fidelidad\Diseno\PresentacionPublica;
use App\Services\Medios\AlmacenMedios;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diseño de la tarjeta digital (solo apariencia). Ver y previsualizar exige
 * el permiso `fidelidad.diseno` y Fidelidad en modo consulta; guardar,
 * publicar, restaurar y subir imágenes exigen además la contratación activa
 * (`RequiereServicio:fidelidad,operar`).
 *
 * Nada de lo que hace este controlador modifica visitas, metas, recompensas
 * ni consumidores: la vista previa usa datos de ejemplo y las reglas reales
 * de las campañas (meta de la versión vigente), nunca datos personales.
 */
class DisenoController extends ControladorFidelidad
{
    public function __construct(private readonly GestorDisenos $gestor) {}

    public function edit(Request $request, PlantillasDiseno $plantillas, AlmacenMedios $medios): Response
    {
        $negocio = $this->negocio($request);
        $campana = $this->campanaSolicitada($request, $request->query('campana'));
        $diseno = $this->gestor->obtener($negocio, $campana);
        $general = $campana !== null ? $this->gestor->obtener($negocio, null) : $diseno;
        $estado = app(AccesoServicios::class)->fidelidad($negocio);
        $opera = $estado['activo'] && $negocio->estaActivo();

        $disenosPorCampana = Diseno::query()->where('negocio_id', $negocio->id)->where('campana_clave', '>', 0)
            ->get(['campana_id', 'publicado'])->keyBy('campana_id');

        return Inertia::render('cliente/fidelidad/Diseno', [
            ...$this->contexto($request),
            'negocio' => $negocio->nombre_comercial,
            'edicion' => [
                'puede' => $opera,
                'motivo' => match (true) {
                    $opera => null,
                    ! $negocio->estaActivo() => 'La cuenta de tu negocio no está activa. Puedes ver el diseño, pero no guardarlo ni publicarlo.',
                    default => 'Fidelidad está en solo lectura (la contratación venció o está en revisión). Puedes ver y previsualizar el diseño, pero no guardar ni publicar cambios.',
                },
            ],
            'variante' => $campana ? ['campana_id' => $campana->id, 'nombre' => $campana->nombre] : null,
            'diseno' => $this->estado($negocio, $diseno, $general),
            'general' => $campana !== null ? ($general->publicado ?? $general->borrador ?? DocumentoDiseno::predeterminado()) : null,
            'versiones' => $this->versiones($diseno),
            'campanas' => Campana::query()->with('vigente')->withCount('participaciones')
                ->where('negocio_id', $negocio->id)->where('estado', '!=', EstadoCampana::Finalizada->value)
                ->orderByRaw("estado = 'activa' desc")->orderBy('nombre')->get()
                ->map(fn (Campana $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'estado' => $c->estado->etiqueta(),
                    'meta' => $c->vigente->meta_visitas ?? 0,
                    'recompensa' => $c->vigente?->recompensa,
                    'participantes' => $c->participaciones_count,
                    'variante' => $disenosPorCampana->has($c->id),
                ])->values(),
            'plantillas' => $plantillas->todas(),
            'predeterminado' => DocumentoDiseno::predeterminado(),
            'archivos' => ArchivoPublico::query()->where('negocio_id', $negocio->id)
                ->where('modulo', ArchivoPublico::MODULO_FIDELIDAD)->where('tipo', ArchivoPublico::TIPO_IMAGEN)
                ->latest('id')->limit(60)->get()->map(fn (ArchivoPublico $a) => $a->resumen())->values(),
            'uso' => $medios->uso($negocio, ArchivoPublico::MODULO_FIDELIDAD),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        [$negocio, $campana, $datos] = $this->entrada($request);
        $resultado = $this->gestor->guardarBorrador($negocio, $campana, $datos['documento'], (int) $datos['version'], $this->usuario($request));

        return $this->respuesta($resultado['diseno'], $resultado['avisos'], 'Borrador guardado. Tus clientes seguirán viendo el diseño publicado hasta que publiques.');
    }

    public function publicar(Request $request): JsonResponse
    {
        [$negocio, $campana, $datos] = $this->entrada($request);
        $resultado = $this->gestor->publicar($negocio, $campana, $datos['documento'], (int) $datos['version'], $this->usuario($request));

        return $this->respuesta($resultado['diseno'], $resultado['avisos'], 'Diseño publicado. Tus clientes ya ven la nueva tarjeta.');
    }

    public function restaurar(Request $request, DisenoVersion $version): JsonResponse
    {
        $this->delNegocio($request, $version);
        $datos = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        $resultado = $this->gestor->restaurar($version->diseno, $version, (int) $datos['version'], $this->usuario($request));

        return $this->respuesta($resultado['diseno'], $resultado['avisos'], 'Versión restaurada en el borrador. Revísala y publícala cuando estés listo.');
    }

    public function eliminarVariante(Request $request, Campana $campana): JsonResponse
    {
        $this->delNegocio($request, $campana);
        $diseno = $this->gestor->obtener($this->negocio($request), $campana);
        abort_if($diseno === null, 404);

        $this->gestor->eliminarVariante($diseno, $this->usuario($request));

        return response()->json(['mensaje' => "«{$campana->nombre}» vuelve a usar el diseño general."]);
    }

    public function subirImagen(Request $request, AlmacenMedios $medios): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file'],
            'uso' => ['required', 'in:logo,portada,fondo'],
        ], [
            'imagen.required' => 'Elige una imagen.',
            'imagen.file' => 'No recibimos la imagen. Inténtalo nuevamente.',
        ]);

        $archivo = $medios->guardarImagen(
            $request->file('imagen'),
            $this->negocio($request),
            ArchivoPublico::MODULO_FIDELIDAD,
            $this->usuario($request),
            $datos['uso'] === 'logo' ? 600 : 1600,
            'imagen',
        );

        return response()->json(['archivo' => $archivo->resumen(), 'mensaje' => 'Imagen lista. Se limpiaron sus datos ocultos (como la ubicación).']);
    }

    // ---------------------------------------------------------------

    /**
     * @return array{0: Negocio, 1: Campana|null, 2: array<string, mixed>}
     */
    private function entrada(Request $request): array
    {
        $datos = $request->validate([
            'campana_id' => ['nullable', 'integer'],
            'version' => ['required', 'integer', 'min:0'],
            'documento' => ['required', 'array'],
        ], ['documento.required' => 'No recibimos el diseño. Inténtalo nuevamente.']);

        if (strlen((string) json_encode($datos['documento'])) > DocumentoDiseno::MAX_BYTES) {
            throw ValidationException::withMessages(['documento' => 'El diseño tiene demasiado contenido. Acorta los textos e inténtalo de nuevo.']);
        }

        return [$this->negocio($request), $this->campanaSolicitada($request, $datos['campana_id'] ?? null), $datos];
    }

    private function campanaSolicitada(Request $request, mixed $id): ?Campana
    {
        if ($id === null || $id === '') {
            return null;
        }

        abort_unless(is_numeric($id), 404);

        return $this->delNegocio($request, Campana::query()->findOrFail((int) $id));
    }

    /**
     * @param  list<string>  $avisos
     */
    private function respuesta(Diseno $diseno, array $avisos, string $mensaje): JsonResponse
    {
        $negocio = $diseno->negocio;
        $general = $diseno->campana_id !== null ? $this->gestor->obtener($negocio, null) : $diseno;

        return response()->json([
            'mensaje' => $mensaje,
            'avisos' => $avisos,
            'diseno' => $this->estado($negocio, $diseno, $general),
            'versiones' => $this->versiones($diseno),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function estado(Negocio $negocio, ?Diseno $diseno, ?Diseno $general): array
    {
        $base = $diseno->borrador ?? $general->borrador ?? DocumentoDiseno::predeterminado();
        $documentos = [$base, $diseno?->publicado];

        return [
            'existe' => $diseno !== null,
            'version' => $diseno->version ?? 0,
            'borrador' => $base,
            'publicado' => $diseno?->publicado,
            'publicado_en' => $diseno?->publicado_en?->toIso8601String(),
            'cambios_sin_publicar' => $diseno === null || $diseno->tieneCambiosSinPublicar(),
            'medios' => PresentacionPublica::medios($negocio, $documentos),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function versiones(?Diseno $diseno): array
    {
        if ($diseno === null) {
            return [];
        }

        return array_values(DisenoVersion::query()->with('autor:id,name')->where('diseno_id', $diseno->id)
            ->orderByDesc('id')->limit(GestorDisenos::MAX_VERSIONES)->get()
            ->map(fn (DisenoVersion $v) => [
                'id' => $v->id,
                'motivo' => $v->motivo === DisenoVersion::PUBLICACION ? 'Publicación' : 'Respaldo antes de restaurar',
                'en' => $v->created_at->toIso8601String(),
                'autor' => $v->autor?->name,
                'documento' => $v->documento,
            ])->all());
    }
}
