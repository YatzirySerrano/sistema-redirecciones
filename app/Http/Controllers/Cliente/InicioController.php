<?php

namespace App\Http\Controllers\Cliente;

use App\Enums\EstadoProducto;
use App\Http\Controllers\Controller;
use App\Http\Resources\CambioDestinoResource;
use App\Models\CambioDestino;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Fidelidad\ResumenFidelidad;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio del cliente: cómo están sus productos, cuánto se usan, qué cambió
 * y qué necesita atención. Solo datos de su negocio y de los productos que
 * posee hoy.
 */
class InicioController extends Controller
{
    public function __invoke(
        Request $request,
        ConsultasEstadisticas $estadisticas,
        AjustesPlataforma $ajustes,
        AccesoServicios $servicios,
        ResumenFidelidad $fidelidad,
    ): Response {
        /** @var User $usuario */
        $usuario = $request->user();
        $negocio = $usuario->negocio;
        abort_if($negocio === null, 404);

        $verEstadisticas = $ajustes->booleano('clientes_ven_estadisticas') && $usuario->tienePermiso('estadisticas.ver');
        $actual = FiltroEstadisticas::ultimosDias(30, $negocio->id, soloProductosVigentes: true);
        $previo = $actual->anterior();
        $sinRedirigir = $verEstadisticas ? array_sum(array_column($estadisticas->sinRedirigir($actual), 'total')) : 0;

        // Sin los QR 2 archivados al reducir a 1 QR (ya no forman parte del producto).
        $enlaces = Producto::query()->deNegocio($negocio->id)->whereNull('archivado_en')->get(['id', 'producto_principal_id', 'nombre', 'estado', 'url_destino', 'medio']);
        $fisicos = $enlaces->whereNull('producto_principal_id');
        $sinDestino = $enlaces->where('estado', EstadoProducto::Activo)->whereNull('url_destino');

        $alertas = [];

        if ($sinDestino->isNotEmpty()) {
            $alertas[] = [
                'clave' => 'sin_destino',
                'titulo' => $sinDestino->count() === 1 ? 'Un enlace no tiene destino' : "{$sinDestino->count()} enlaces no tienen destino",
                'descripcion' => 'Quien los escanee verá «no disponible». Elige a dónde deben llevar.',
                'producto_id' => (int) ($sinDestino->first()->producto_principal_id ?? $sinDestino->first()->id),
            ];
        }

        $suspendidos = $fisicos->where('estado', EstadoProducto::Suspendido)->count();

        if ($suspendidos > 0) {
            $alertas[] = [
                'clave' => 'suspendidos',
                'titulo' => $suspendidos === 1 ? 'Un producto está suspendido' : "{$suspendidos} productos están suspendidos",
                'descripcion' => 'Sus enlaces no redirigen. Contacta a DASTI para reactivarlos.',
                'producto_id' => null,
            ];
        }

        if ($sinRedirigir > 0) {
            $alertas[] = [
                'clave' => 'sin_redirigir',
                'titulo' => $sinRedirigir === 1 ? 'Un acceso no llegó a tu destino' : "{$sinRedirigir} intentos no llegaron a tu destino",
                'descripcion' => 'En los últimos 30 días alguien abrió un enlace sin destino o suspendido.',
                'producto_id' => null,
            ];
        }

        $conFidelidad = $usuario->tienePermiso('fidelidad.ver') && $servicios->puedeConsultarFidelidad($negocio);

        return Inertia::render('cliente/Inicio', [
            'negocio' => ['nombre_comercial' => $negocio->nombre_comercial],
            'indicadores' => [
                'productos' => $fisicos->count(),
                'productos_activos' => $fisicos->where('estado', EstadoProducto::Activo)->count(),
                'enlaces_sin_destino' => $sinDestino->count(),
                'accesos_30_dias' => $verEstadisticas ? $estadisticas->totales($actual) : null,
                'accesos_previos' => $verEstadisticas ? $estadisticas->totales($previo)['total'] : null,
                'enlaces' => $enlaces->count(),
                'enlaces_con_destino' => $enlaces->where('estado', EstadoProducto::Activo)->whereNotNull('url_destino')->count(),
            ],
            'serie' => $verEstadisticas ? $estadisticas->serieCanales($actual) : null,
            'serie_anterior' => $verEstadisticas ? array_column($estadisticas->serieDiaria($previo), 'total') : null,
            'canales' => $verEstadisticas ? $estadisticas->porTipo($actual) : [],
            'productos_top' => $verEstadisticas ? $estadisticas->actividadPorProducto($actual, 5) : [],
            'sin_actividad' => $verEstadisticas ? $estadisticas->productosSinActividad($actual, 4) : null,
            'ver_estadisticas' => $verEstadisticas,
            'alertas' => $alertas,
            'actividad' => CambioDestinoResource::collection(
                // Solo los cambios hechos con la cuenta del propio usuario.
                CambioDestino::query()->with(['producto', 'usuario'])->where('negocio_id', $negocio->id)
                    ->where('user_id', $usuario->id)
                    ->whereHas('producto', fn ($q) => $q->where('negocio_id', $negocio->id))
                    ->latest('created_at')->latest('id')->limit(6)->get()
            ),
            'fidelidad' => $conFidelidad ? $fidelidad->paraNegocio($negocio) : null,
        ]);
    }
}
