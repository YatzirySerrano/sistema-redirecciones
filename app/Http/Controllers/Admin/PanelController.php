<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Fidelidad\ResumenFidelidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de DASTI. Cada indicador responde a una pregunta operativa y
 * proviene de consultas reales en MariaDB; no se calculan ingresos ni
 * conversiones que la plataforma no registra.
 */
class PanelController extends Controller
{
    public function __invoke(Request $request, ConsultasEstadisticas $estadisticas, ResumenFidelidad $fidelidad): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $actual = FiltroEstadisticas::ultimosDias(30);
        $previo = new FiltroEstadisticas($actual->desde->subDays(30), $actual->desde->subSecond());

        $fisicos = Producto::query()->principales();
        $negocioActivo = fn (Builder $q) => $q->where('estado', EstadoCuenta::Activo);

        // Enlaces (incluye reversos QR): ¿cuántos funcionan hoy?
        $enlacesRedirigiendo = Producto::query()
            ->where('estado', EstadoProducto::Activo)
            ->whereNotNull('url_destino')
            ->whereHas('negocio', $negocioActivo)
            ->count();
        $enlacesSinDestino = Producto::query()
            ->where('estado', EstadoProducto::Activo)
            ->whereNull('url_destino')
            ->whereHas('negocio', $negocioActivo)
            ->count();

        $periodo = $estadisticas->totales($actual);

        return Inertia::render('admin/Panel', [
            'indicadores' => [
                'negocios' => Negocio::query()->count(),
                'negocios_activos' => Negocio::query()->where('estado', EstadoCuenta::Activo)->count(),
                'productos' => (clone $fisicos)->count(),
                'productos_activos' => (clone $fisicos)->where('estado', EstadoProducto::Activo)->count(),
                'productos_suspendidos' => (clone $fisicos)->where('estado', EstadoProducto::Suspendido)->count(),
                'enlaces_redirigiendo' => $enlacesRedirigiendo,
                'enlaces_sin_destino' => $enlacesSinDestino,
                'accesos_periodo' => $periodo,
                'accesos_periodo_anterior' => $estadisticas->totales($previo)['total'],
                // Histórico completo (todos los periodos de propiedad).
                'accesos_totales' => $estadisticas->totalHistorico(),
            ],
            'incidencias' => $this->incidencias($enlacesSinDestino),
            'fidelidad' => $usuario->tienePermiso('servicios.ver') ? $fidelidad->paraPlataforma() : null,
            'serie' => $estadisticas->serieDiaria($actual),
            'productos_top' => $estadisticas->productosMasUsados($actual, 6),
            'negocios_top' => $estadisticas->negociosMasActivos($actual, 6),
            'ultimos_cambios' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['producto', 'usuario'])->latest('created_at')->latest('id')->limit(6)->get()
            ),
            'actividad' => $usuario->tienePermiso('auditoria.ver')
                ? RegistroAuditoriaResource::collection(
                    RegistroAuditoria::query()->with(['usuario', 'negocio'])->latest('created_at')->latest('id')->limit(8)->get()
                )
                : null,
        ]);
    }

    /**
     * Situaciones que requieren atención, con el listado donde resolverlas.
     * Solo se incluyen las que existen (cantidad > 0).
     *
     * @return list<array{clave: string, titulo: string, descripcion: string, cantidad: int, ruta: string, parametros: array<string, string>, permiso: string}>
     */
    private function incidencias(int $enlacesSinDestino): array
    {
        $incidencias = [
            [
                'clave' => 'sin_destino',
                'titulo' => 'Enlaces activos sin destino',
                'descripcion' => 'Al escanearlos muestran «no disponible».',
                'cantidad' => $enlacesSinDestino,
                'ruta' => 'admin.enlaces.index',
                'parametros' => ['situacion' => 'sin_destino'],
                'permiso' => 'redirecciones.ver',
            ],
            [
                'clave' => 'productos_suspendidos',
                'titulo' => 'Productos suspendidos',
                'descripcion' => 'Sus enlaces no redirigen.',
                'cantidad' => Producto::query()->principales()->where('estado', EstadoProducto::Suspendido)->count(),
                'ruta' => 'admin.productos.index',
                'parametros' => ['estado' => 'suspendido'],
                'permiso' => 'productos.ver',
            ],
            [
                'clave' => 'negocios_suspendidos',
                'titulo' => 'Negocios suspendidos',
                'descripcion' => 'Ninguno de sus enlaces funciona y sus usuarios no pueden entrar.',
                'cantidad' => Negocio::query()->where('estado', EstadoCuenta::Suspendido)->count(),
                'ruta' => 'admin.negocios.index',
                'parametros' => ['estado' => 'suspendido'],
                'permiso' => 'negocios.ver',
            ],
            [
                'clave' => 'invitaciones_fallidas',
                'titulo' => 'Invitaciones que no se enviaron',
                'descripcion' => 'Revisa el correo y vuelve a enviar el enlace de acceso.',
                'cantidad' => User::query()->whereNotNull('invitacion_fallida_en')->whereNull('invitacion_aceptada_en')->count(),
                'ruta' => 'admin.usuarios.index',
                'parametros' => [],
                'permiso' => 'usuarios.ver',
            ],
            [
                'clave' => 'personal_sin_2fa',
                'titulo' => 'Personal sin verificación en dos pasos',
                'descripcion' => 'Cuentas de DASTI activas sin segundo factor.',
                'cantidad' => User::query()->where('rol', RolUsuario::Admin)->where('estado', EstadoCuenta::Activo)->whereNull('two_factor_confirmed_at')->count(),
                'ruta' => 'admin.usuarios.index',
                'parametros' => ['rol' => RolUsuario::Admin->value],
                'permiso' => 'usuarios.ver',
            ],
        ];

        return array_values(array_filter($incidencias, fn (array $i) => $i['cantidad'] > 0));
    }
}
