<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'puedeCambiarEmail' => $request->user()->esAdministrador(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $usuario->fill($request->validated());
        $cambios = $usuario->getDirty();
        $anteriores = array_intersect_key($usuario->getOriginal(), $cambios);
        $usuario->save();

        if ($cambios !== []) {
            $auditoria->registrar(
                accion: 'usuario.perfil_actualizado',
                descripcion: "{$usuario->email} actualizó su perfil",
                recurso: $usuario,
                negocio: $usuario->negocio_id,
                anteriores: $anteriores,
                nuevos: $cambios,
                actor: $usuario,
            );
        }

        $this->notificar('Perfil actualizado.');

        return to_route('profile.edit');
    }
}
