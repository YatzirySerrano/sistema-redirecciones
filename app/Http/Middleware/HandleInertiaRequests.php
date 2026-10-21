<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos compartidos. Se envía una proyección explícita del usuario (nunca
     * el modelo completo) y solo los permisos que la interfaz necesita para
     * mostrar u ocultar navegación; la autorización real vive en el backend.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $usuario */
        $usuario = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $usuario === null ? null : [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol->value,
                    'two_factor_enabled' => $usuario->two_factor_confirmed_at !== null,
                    'negocio' => $usuario->esCliente() && $usuario->negocio !== null
                        ? ['id' => $usuario->negocio->id, 'nombre_comercial' => $usuario->negocio->nombre_comercial]
                        : null,
                ],
                'permisos' => $usuario === null ? null : [
                    'administrar' => $usuario->esAdministrador(),
                    'ver_estadisticas' => $usuario->esAdministrador()
                        || app(AjustesPlataforma::class)->booleano('clientes_ven_estadisticas'),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
