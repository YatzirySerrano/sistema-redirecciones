<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Apariencia\MarcaDasti;
use App\Services\Apariencia\TemaVisual;
use App\Services\Medios\LimitesArchivos;
use App\Services\Servicios\AccesoServicios;
use App\Services\Usuarios\PreferenciasUsuario;
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
                    'rol_nombre' => $usuario->rolAsignado()?->name,
                    'two_factor_enabled' => $usuario->two_factor_confirmed_at !== null,
                    'negocio' => $usuario->esCliente() && $usuario->negocio !== null
                        ? ['id' => $usuario->negocio->id, 'nombre_comercial' => $usuario->negocio->nombre_comercial]
                        : null,
                ],
                'permisos' => $usuario === null ? null : [
                    'administrar' => $usuario->esAdministrador(),
                    'superadministrador' => $usuario->esSuperadministrador(),
                    // Permisos efectivos del rol (para mostrar u ocultar
                    // menús y botones; el servidor vuelve a comprobarlos).
                    'lista' => $usuario->permisosEfectivos(),
                    'ver_estadisticas' => $usuario->esAdministrador()
                        ? $usuario->tienePermiso('estadisticas.ver')
                        : $usuario->can('ver-estadisticas-cliente'),
                    // El administrador debe activar la verificación en dos
                    // pasos antes de usar el panel (RequerirDosPasosAdministrador).
                    'requiere_dos_pasos' => $usuario->esAdministrador()
                        && (bool) config('dasti.exigir_2fa_administradores')
                        && $usuario->two_factor_confirmed_at === null,
                ],
            ],
            // Servicios adicionales del negocio del cliente (navegación).
            'servicios' => fn () => $usuario !== null && $usuario->esCliente() && $usuario->negocio !== null
                ? app(AccesoServicios::class)->resumen($usuario->negocio)
                : null,
            // Identidad de DASTI (logotipo oficial cuando se coloque en
            // public/marca; mientras tanto, recurso neutral temporal).
            'marca' => fn () => app(MarcaDasti::class)->datos(),
            // Límites reales de carga (formatos y tamaño máximo por tipo).
            'limites_archivos' => fn () => $usuario !== null ? app(LimitesArchivos::class)->todos() : null,
            'preferencias' => [
                'vistas' => $usuario === null ? (object) [] : (object) PreferenciasUsuario::vistas($usuario),
            ],
            // Colores personalizados de la interfaz (null: tema predeterminado).
            'tema' => fn () => app(TemaVisual::class)->tokens(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
