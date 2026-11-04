<?php

namespace App\Http\Controllers\Paginas;

use App\Enums\EstadoPagina;
use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Autorizacion\NegocioDeTrabajo;
use App\Services\Paginas\GestorPaginas;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base de los controladores del panel de Página de enlaces. Todo registro
 * se comprueba contra el negocio del usuario: uno ajeno responde 404.
 */
abstract class ControladorPaginas extends Controller
{
    protected function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }

    /**
     * Negocio de trabajo: el del usuario o, para el personal DASTI en
     * «Diseño para clientes», el negocio autorizado de la ruta.
     */
    protected function negocio(Request $request): Negocio
    {
        $negocio = app(NegocioDeTrabajo::class)->negocio($request);
        abort_if($negocio === null, 404);

        return $negocio;
    }

    /**
     * ¿Es el personal DASTI diseñando para el negocio?
     */
    protected function esDasti(): bool
    {
        return app(NegocioDeTrabajo::class)->esDasti();
    }

    protected function pagina(Request $request): PaginaEnlaces
    {
        $pagina = app(GestorPaginas::class)->paraNegocio($this->negocio($request));
        $pagina->setRelation('negocio', $this->negocio($request));

        return $pagina;
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    protected function delNegocio(Request $request, Model $modelo): Model
    {
        abort_unless((int) $modelo->getAttribute('negocio_id') === $this->negocio($request)->id, 404);

        return $modelo;
    }

    /**
     * Estado del servicio y capacidades del usuario para la interfaz (la
     * autorización real vive en las rutas: permiso + RequiereServicio).
     *
     * @return array<string, mixed>
     */
    protected function contexto(Request $request): array
    {
        $usuario = $this->usuario($request);
        $negocio = $this->negocio($request);
        $acceso = app(AccesoServicios::class);
        $estado = $acceso->estado($negocio, Servicio::PAGINA_ENLACES);
        $opera = $acceso->puedeOperar($negocio, Servicio::PAGINA_ENLACES);

        // Personal DASTI («Diseño para clientes»): solo editar y publicar
        // con su permiso de plataforma; nada de dominios ni mensajes.
        if ($this->esDasti()) {
            $disena = $opera && $usuario->tienePermiso('paginas.disenar_negocios');

            return [
                'servicio' => [...$estado, 'opera' => $opera],
                'contexto_dasti' => [
                    'negocio_id' => $negocio->id,
                    'negocio' => $negocio->nombre_comercial,
                    'volver' => route('admin.disenos.index', ['negocio' => $negocio->id], absolute: false),
                ],
                'puede' => [
                    'editar' => $disena,
                    'publicar' => $disena,
                    'dominios' => false,
                    'ver_dominios' => false,
                    'mensajes' => false,
                    'estadisticas' => false,
                ],
            ];
        }

        return [
            'servicio' => [...$estado, 'opera' => $opera],
            'contexto_dasti' => null,
            'puede' => [
                'editar' => $opera && $usuario->tienePermiso('paginas.editar'),
                'publicar' => $opera && $usuario->tienePermiso('paginas.publicar'),
                'dominios' => $opera && $usuario->tienePermiso('paginas.dominios'),
                'ver_dominios' => $usuario->tienePermiso('paginas.dominios'),
                'mensajes' => $usuario->tienePermiso('paginas.mensajes'),
                'estadisticas' => $usuario->tienePermiso('paginas.estadisticas'),
            ],
        ];
    }

    /**
     * Paginación con la forma que espera el componente Paginacion
     * ({data, meta}).
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginador
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public static function paginado(LengthAwarePaginator $paginador): array
    {
        $arreglo = $paginador->toArray();

        return [
            'data' => array_values($paginador->items()),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'from' => $paginador->firstItem(),
                'to' => $paginador->lastItem(),
                'total' => $paginador->total(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'links' => $arreglo['links'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resumenPagina(PaginaEnlaces $pagina): array
    {
        $pagina->loadMissing(['dominios', 'editor:id,name']);
        $principal = $pagina->dominioPrincipal();

        return [
            'id' => $pagina->id,
            'slug' => $pagina->slug,
            'estado' => $pagina->estado->value,
            'estado_etiqueta' => $pagina->estado->etiqueta(),
            'publicada' => $pagina->estado === EstadoPagina::Publicada,
            'version' => $pagina->version,
            'url_publica' => $pagina->urlPublica(),
            'url_canonica' => $pagina->urlCanonica(),
            'dominio_principal' => $principal?->host,
            'prefijo_url' => config('dasti.url_publica').'/'.trim((string) config('dasti.paginas.prefijo', 'l'), '/').'/',
            'publicado_en' => $pagina->publicado_en?->toIso8601String(),
            'borrador_actualizado_en' => $pagina->borrador_actualizado_en?->toIso8601String(),
            'editor' => $pagina->editor?->name,
            'cambios_sin_publicar' => $pagina->publicado === null || json_encode($pagina->publicado) !== json_encode($pagina->borrador),
            'slug_cambiado_en' => $pagina->slug_cambiado_en?->toIso8601String(),
        ];
    }
}
