<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Negocios\GestionarLogoNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogoNegocioRequest;
use App\Models\Negocio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logotipo del negocio, guardado en disco privado (no público).
 */
class LogoNegocioController extends Controller
{
    public function show(Negocio $negocio): StreamedResponse
    {
        abort_if($negocio->logo_path === null || ! Storage::disk('local')->exists($negocio->logo_path), 404);

        return Storage::disk('local')->response($negocio->logo_path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(LogoNegocioRequest $request, Negocio $negocio, GestionarLogoNegocio $accion): RedirectResponse
    {
        $archivo = $request->file('logo');
        abort_unless($archivo instanceof UploadedFile, 422);

        $accion->guardar($negocio, $archivo);
        $this->notificar('Logotipo actualizado.');

        return back();
    }

    public function destroy(Negocio $negocio, GestionarLogoNegocio $accion): RedirectResponse
    {
        $accion->eliminar($negocio);
        $this->notificar('Logotipo eliminado.');

        return back();
    }
}
