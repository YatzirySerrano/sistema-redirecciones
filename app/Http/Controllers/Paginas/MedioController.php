<?php

namespace App\Http\Controllers\Paginas;

use App\Models\ArchivoPublico;
use App\Services\Medios\AlmacenMedios;
use App\Services\Medios\LimitesArchivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Biblioteca de imágenes y videos de la página (solo del propio negocio).
 * La validación de contenido, el re-codificado y la cuota los aplica
 * AlmacenMedios.
 */
class MedioController extends ControladorPaginas
{
    public function index(Request $request, AlmacenMedios $medios): JsonResponse
    {
        $negocio = $this->negocio($request);

        return response()->json([
            'medios' => ArchivoPublico::query()->where('negocio_id', $negocio->id)->where('modulo', ArchivoPublico::MODULO_PAGINAS)
                ->latest('id')->limit(300)->get()->map(fn (ArchivoPublico $a) => [...$a->resumen(), 'nombre' => $a->nombre_original]),
            'uso' => $medios->uso($negocio, ArchivoPublico::MODULO_PAGINAS),
        ]);
    }

    public function store(Request $request, AlmacenMedios $medios): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file'],
            'tipo' => ['required', 'in:imagen,video'],
            'uso' => ['nullable', 'in:normal,fondo,favicon,miniatura'],
        ], [
            'archivo.required' => 'Elige un archivo.',
            'archivo.file' => 'No recibimos el archivo. Inténtalo nuevamente.',
            // PHP no pudo recibirlo (p. ej. supera upload_max_filesize).
            'archivo.uploaded' => 'El archivo es demasiado grande. '.app(LimitesArchivos::class)->texto($request->input('tipo') === 'video' ? LimitesArchivos::VIDEO : LimitesArchivos::IMAGEN),
        ]);

        $negocio = $this->negocio($request);
        $archivo = $request->file('archivo');
        abort_unless($archivo instanceof UploadedFile, 422);

        $lado = match ($request->input('uso')) {
            'favicon' => 256,
            'miniatura' => 400,
            'fondo' => 2000,
            default => 1600,
        };

        $guardado = $request->input('tipo') === 'video'
            ? $medios->guardarVideo($archivo, $negocio, ArchivoPublico::MODULO_PAGINAS, $this->usuario($request))
            : $medios->guardarImagen($archivo, $negocio, ArchivoPublico::MODULO_PAGINAS, $this->usuario($request), $lado, 'archivo', $request->input('uso') === 'favicon' ? LimitesArchivos::LOGO : LimitesArchivos::IMAGEN);

        return response()->json(['medio' => [...$guardado->resumen(), 'nombre' => $guardado->nombre_original]], 201);
    }

    public function destroy(Request $request, string $uuid, AlmacenMedios $medios): JsonResponse
    {
        $archivo = $medios->delNegocio($this->negocio($request), ArchivoPublico::MODULO_PAGINAS, $uuid);
        abort_if($archivo === null, 404);

        $medios->eliminar($archivo, $this->usuario($request));

        return response()->json(['ok' => true]);
    }
}
