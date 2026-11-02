<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCuenta;
use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opciones de los selectores con búsqueda remota del panel administrativo.
 *
 * Nunca envía catálogos completos: devuelve páginas de POR_PAGINA
 * resultados ordenados por un campo indexado y consulta uno más para saber
 * si hay más (sin COUNT). La comparación usa la intercalación
 * utf8mb4_unicode_ci de MariaDB, que ignora mayúsculas y acentos.
 */
class OpcionesController extends Controller
{
    public const POR_PAGINA = 20;

    /**
     * Negocios: los necesitan quienes asignan, transfieren, filtran o
     * crean accesos.
     */
    public function negocios(Request $request): JsonResponse
    {
        $this->exigirAlguno($request, ['negocios.ver', 'productos.ver', 'productos.crear', 'productos.transferir', 'usuarios.ver', 'usuarios.crear', 'redirecciones.ver', 'estadisticas.ver', 'auditoria.ver']);
        ['buscar' => $buscar, 'pagina' => $pagina] = $this->parametros($request);

        $consulta = Negocio::query()
            ->select(['id', 'nombre_comercial', 'responsable', 'email_contacto', 'estado'])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('nombre_comercial', 'like', $like)
                    ->orWhere('responsable', 'like', $like)
                    ->orWhere('email_contacto', 'like', $like));
            })
            ->when($request->boolean('solo_activos'), fn (Builder $q) => $q->where('estado', EstadoCuenta::Activo))
            ->orderBy('nombre_comercial')
            ->orderBy('id');

        return $this->pagina($consulta, $pagina, fn (Negocio $n) => [
            'value' => (string) $n->id,
            'label' => $n->nombre_comercial.($n->estaActivo() ? '' : ' (suspendido)'),
            'descripcion' => $n->responsable ?? $n->email_contacto ?? '',
        ]);
    }

    public function usuarios(Request $request): JsonResponse
    {
        $this->exigirAlguno($request, ['usuarios.ver', 'auditoria.ver']);
        ['buscar' => $buscar, 'pagina' => $pagina] = $this->parametros($request);

        $consulta = User::query()
            ->select(['id', 'name', 'email'])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id');

        return $this->pagina($consulta, $pagina, fn (User $u) => [
            'value' => (string) $u->id,
            'label' => $u->name,
            'descripcion' => $u->email,
        ]);
    }

    /**
     * Productos de un negocio (filtro de estadísticas).
     */
    public function productos(Request $request): JsonResponse
    {
        $this->exigirAlguno($request, ['productos.ver', 'estadisticas.ver']);
        ['buscar' => $buscar, 'pagina' => $pagina] = $this->parametros($request);
        $negocioId = $request->integer('negocio_id');

        $consulta = Producto::query()
            ->select(['id', 'nombre', 'codigo'])
            ->when($negocioId > 0, fn (Builder $q) => $q->where('negocio_id', $negocioId))
            ->buscar($buscar, incluirInternos: false)
            ->orderBy('nombre')
            ->orderBy('id');

        return $this->pagina($consulta, $pagina, fn (Producto $p) => [
            'value' => (string) $p->id,
            'label' => $p->nombre,
            'descripcion' => $p->codigo,
        ]);
    }

    /**
     * @param  list<string>  $permisos
     */
    private function exigirAlguno(Request $request, array $permisos): void
    {
        /** @var User $usuario */
        $usuario = $request->user();

        abort_unless(collect($permisos)->contains(fn (string $p) => $usuario->tienePermiso($p)), 403, 'No tienes permiso para realizar esta acción.');
    }

    /**
     * @return array{buscar: string, pagina: int}
     */
    private function parametros(Request $request): array
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return [
            'buscar' => trim((string) ($datos['buscar'] ?? '')),
            'pagina' => (int) ($datos['pagina'] ?? 1),
        ];
    }

    /**
     * @template TModelo of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModelo>  $consulta
     * @param  callable(TModelo): array{value: string, label: string, descripcion: string}  $mapear
     */
    private function pagina(Builder $consulta, int $pagina, callable $mapear): JsonResponse
    {
        $filas = $consulta->offset(($pagina - 1) * self::POR_PAGINA)->limit(self::POR_PAGINA + 1)->get();

        return response()->json([
            'data' => $filas->take(self::POR_PAGINA)->map($mapear)->values(),
            'hay_mas' => $filas->count() > self::POR_PAGINA,
        ]);
    }
}
