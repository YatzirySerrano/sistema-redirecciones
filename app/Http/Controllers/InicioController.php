<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada tras iniciar sesión: envía a cada rol a su panel.
 */
class InicioController extends Controller
{
    /**
     * Primera sección permitida de cada ámbito, en orden de preferencia.
     */
    private const DESTINOS_PLATAFORMA = [
        'panel.ver' => 'admin.panel',
        'productos.ver' => 'admin.productos.index',
        'negocios.ver' => 'admin.negocios.index',
        'redirecciones.ver' => 'admin.enlaces.index',
        'usuarios.ver' => 'admin.usuarios.index',
        'estadisticas.ver' => 'admin.estadisticas.index',
        'auditoria.ver' => 'admin.auditoria.index',
        'roles.ver' => 'admin.roles.index',
        'configuracion.ver' => 'admin.configuracion.edit',
        'personalizacion.ver' => 'admin.personalizacion.edit',
    ];

    private const DESTINOS_NEGOCIO = [
        'panel.ver' => 'cliente.inicio',
        'productos.ver' => 'cliente.productos.index',
    ];

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $destinos = $usuario->esAdministrador() ? self::DESTINOS_PLATAFORMA : self::DESTINOS_NEGOCIO;

        foreach ($destinos as $permiso => $ruta) {
            if ($usuario->tienePermiso($permiso)) {
                return redirect()->route($ruta);
            }
        }

        // Sin secciones asignadas: solo «Mi cuenta».
        return redirect()->route('profile.edit');
    }
}
