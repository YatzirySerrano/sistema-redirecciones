<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonalizacionRequest;
use App\Models\User;
use App\Services\Apariencia\TemaVisual;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración › Apariencia y personalización.
 */
class PersonalizacionController extends Controller
{
    public function edit(Request $request, TemaVisual $tema): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('admin/configuracion/Apariencia', [
            'colores' => $tema->colores(),
            'predeterminados' => TemaVisual::PREDETERMINADOS,
            'campos' => TemaVisual::CAMPOS,
            'personalizado' => $tema->personalizado(),
            'puede_editar' => $usuario->tienePermiso('personalizacion.editar'),
        ]);
    }

    public function update(PersonalizacionRequest $request, TemaVisual $tema, RegistradorAuditoria $auditoria): RedirectResponse
    {
        /** @var array<string, string> $colores */
        $colores = $request->validated();
        $cambios = $tema->guardar($colores);

        if ($cambios['nuevos'] === []) {
            $this->notificar('No hubo cambios que guardar.', 'info');

            return back();
        }

        $auditoria->registrar(
            accion: 'apariencia.actualizada',
            descripcion: 'Actualización de los colores de la interfaz',
            anteriores: $cambios['anteriores'],
            nuevos: $cambios['nuevos'],
        );
        $this->notificar('Apariencia guardada. Los cambios ya se aplican en toda la plataforma.');

        return back();
    }

    public function destroy(TemaVisual $tema, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $anteriores = $tema->restablecer();

        if ($anteriores !== null) {
            $auditoria->registrar(
                accion: 'apariencia.restablecida',
                descripcion: 'Restablecimiento de los colores originales de la interfaz',
                anteriores: $anteriores,
            );
        }

        $this->notificar($anteriores === null ? 'La apariencia ya era la original.' : 'Se restableció la apariencia original.', $anteriores === null ? 'info' : 'success');

        return back();
    }
}
