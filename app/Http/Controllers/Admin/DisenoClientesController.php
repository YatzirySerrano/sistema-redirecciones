<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Diseño para clientes»: el personal DASTI elige un negocio y abre su
 * editor de Fidelidad o de Página de enlaces. Esta pantalla solo orienta;
 * la autorización real está en las rutas (permiso `<modulo>.disenar_negocios`
 * + DisenarParaNegocio + contratación del servicio).
 */
class DisenoClientesController extends Controller
{
    public function index(Request $request, AccesoServicios $acceso): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $negocio = $request->integer('negocio') > 0 ? Negocio::query()->find($request->integer('negocio')) : null;

        $modulo = function (string $clave, string $permiso, string $ruta) use ($negocio, $usuario, $acceso): array {
            $permitido = $usuario->tienePermiso($permiso);

            if ($negocio === null) {
                return ['permitido' => $permitido, 'estado' => null, 'puede_abrir' => false, 'puede_guardar' => false, 'url' => null, 'aviso' => null];
            }

            $estado = $acceso->estado($negocio, $clave);
            $consulta = $acceso->puedeConsultar($negocio, $clave);
            $opera = $acceso->puedeOperar($negocio, $clave) && $negocio->estaActivo();

            return [
                'permitido' => $permitido,
                'estado' => $estado['estado'],
                'puede_abrir' => $permitido && $consulta,
                'puede_guardar' => $permitido && $opera,
                'url' => $permitido && $consulta ? route($ruta, ['negocio' => $negocio->id], false) : null,
                'aviso' => match (true) {
                    ! $permitido => 'Tu rol no incluye este permiso.',
                    ! $consulta => 'Este negocio no tiene el servicio contratado. Actívalo desde Servicios para poder diseñarlo.',
                    ! $opera => 'El servicio está en solo lectura o el negocio está suspendido: puedes ver el diseño, pero no guardarlo ni publicarlo.',
                    default => null,
                },
            ];
        };

        return Inertia::render('admin/disenos/Index', [
            'negocio' => $negocio ? [
                'id' => $negocio->id,
                'nombre' => $negocio->nombre_comercial,
                'responsable' => $negocio->responsable,
                'activo' => $negocio->estaActivo(),
            ] : null,
            'modulos' => [
                'fidelidad' => $modulo(Servicio::FIDELIDAD, 'fidelidad.disenar_negocios', 'admin.disenos.fidelidad.edit'),
                'paginas' => $modulo(Servicio::PAGINA_ENLACES, 'paginas.disenar_negocios', 'admin.disenos.paginas.editor'),
            ],
            'puede_servicios' => $usuario->tienePermiso('servicios.gestionar'),
        ]);
    }
}
