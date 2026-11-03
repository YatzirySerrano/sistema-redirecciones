<?php

namespace App\Http\Controllers\Paginas;

use App\Models\Paginas\VersionPagina;
use App\Services\Paginas\CatalogoPagina;
use App\Services\Paginas\ConflictoVersion;
use App\Services\Paginas\DocumentoPagina;
use App\Services\Paginas\GestorPaginas;
use App\Services\Paginas\PlantillasPagina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor visual: borrador con control de concurrencia (409 si otra pestaña
 * guardó antes), publicación, historial y restauración. Las respuestas de
 * guardado son JSON (el editor conserva su estado local y su historial de
 * deshacer).
 */
class EditorController extends ControladorPaginas
{
    public function show(Request $request, PlantillasPagina $plantillas): Response
    {
        $pagina = $this->pagina($request);

        return Inertia::render('cliente/paginas/Editor', [
            ...$this->contexto($request),
            'pagina' => $this->resumenPagina($pagina),
            'documento' => $pagina->borrador,
            'tema_por_defecto' => DocumentoPagina::temaPorDefecto(),
            'plantillas' => $plantillas->todas($this->negocio($request)),
            'limites' => config('dasti.paginas.limites'),
            'reservadas' => CatalogoPagina::SUBPAGINAS_RESERVADAS,
        ]);
    }

    public function guardar(Request $request, GestorPaginas $gestor): JsonResponse
    {
        $request->validate(['documento' => ['required', 'array'], 'version' => ['required', 'integer', 'min:1']]);
        $pagina = $this->pagina($request);

        try {
            $pagina = $gestor->guardarBorrador($pagina, $request->input('documento'), (int) $request->input('version'), $this->usuario($request));
        } catch (ConflictoVersion $e) {
            return $this->conflicto($e);
        }

        return response()->json([
            'documento' => $pagina->borrador,
            'pagina' => $this->resumenPagina($pagina->fresh() ?? $pagina),
        ]);
    }

    public function publicar(Request $request, GestorPaginas $gestor): JsonResponse
    {
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $pagina = $this->pagina($request);

        try {
            $pagina = $gestor->publicar($pagina, (int) $request->input('version'), $this->usuario($request));
        } catch (ConflictoVersion $e) {
            return $this->conflicto($e);
        }

        return response()->json([
            'documento' => $pagina->borrador,
            'pagina' => $this->resumenPagina($pagina->fresh() ?? $pagina),
            'mensaje' => 'Tu página está publicada.',
        ]);
    }

    public function despublicar(Request $request, GestorPaginas $gestor): JsonResponse
    {
        $pagina = $gestor->despublicar($this->pagina($request), $this->usuario($request));

        return response()->json([
            'pagina' => $this->resumenPagina($pagina->fresh() ?? $pagina),
            'mensaje' => 'Tu página ya no es visible. Tus visitantes verán «página no disponible».',
        ]);
    }

    public function versiones(Request $request): JsonResponse
    {
        $pagina = $this->pagina($request);

        return response()->json([
            'versiones' => VersionPagina::query()->with('autor:id,name')
                ->where('pagina_id', $pagina->id)->latest('id')->limit(30)
                ->get(['id', 'pagina_id', 'motivo', 'autor_id', 'created_at', 'version_borrador'])
                ->map(fn (VersionPagina $v) => [
                    'id' => $v->id,
                    'motivo' => $v->motivo,
                    'etiqueta' => VersionPagina::MOTIVOS[$v->motivo] ?? 'Versión',
                    'autor' => $v->autor?->name,
                    'en' => $v->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function version(Request $request, VersionPagina $version): JsonResponse
    {
        $pagina = $this->pagina($request);
        abort_unless($version->pagina_id === $pagina->id, 404);

        return response()->json(['documento' => $version->documento]);
    }

    public function restaurar(Request $request, VersionPagina $version, GestorPaginas $gestor): JsonResponse
    {
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $pagina = $this->pagina($request);
        abort_unless($version->pagina_id === $pagina->id, 404);

        try {
            $pagina = $gestor->restaurar($pagina, $version, (int) $request->input('version'), $this->usuario($request));
        } catch (ConflictoVersion $e) {
            return $this->conflicto($e);
        }

        return response()->json([
            'documento' => $pagina->borrador,
            'pagina' => $this->resumenPagina($pagina->fresh() ?? $pagina),
            'mensaje' => 'Versión restaurada en el borrador. Revísala y publica cuando estés listo.',
        ]);
    }

    private function conflicto(ConflictoVersion $e): JsonResponse
    {
        return response()->json([
            'message' => 'Otra persona u otra ventana guardó cambios en esta página después de que la abriste.',
            'conflicto' => [
                'version' => $e->versionActual,
                'por' => $e->guardadoPor,
                'en' => $e->guardadoEn,
            ],
        ], 409);
    }
}
