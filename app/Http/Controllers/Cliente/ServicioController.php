<?php

namespace App\Http\Controllers\Cliente;

use App\Enums\EstadoIntentoPago;
use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Pagos\ServicioPagos;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 * Servicios del negocio: incluidos, disponibles y contratados, con la
 * compra en línea (Openpay). La página de retorno solo MUESTRA el estado:
 * antes de mostrarlo se consulta el cargo a Openpay desde el servidor y
 * solo esa confirmación activa el servicio.
 */
class ServicioController extends Controller
{
    /** Ruta de la configuración de cada servicio una vez contratado. */
    private const RUTAS_MODULO = [
        Servicio::REDIRECCIONES => 'cliente.productos.index',
        Servicio::FIDELIDAD => 'cliente.fidelidad.inicio',
        Servicio::PAGINA_ENLACES => 'cliente.paginas.inicio',
    ];

    public function index(Request $request, AccesoServicios $acceso, ServicioPagos $pagos): Response
    {
        $negocio = $this->negocio($request);
        $usuario = $this->usuario($request);

        return Inertia::render('cliente/servicios/Index', [
            'servicios' => Servicio::query()->where(fn ($q) => $q->where('activo', true)->orWhere('es_base', true))
                ->orderBy('orden')->orderBy('id')->get()
                ->map(fn (Servicio $s) => $this->resumen($s, $negocio, $usuario, $acceso, $pagos))->values(),
            'puede_contratar' => $usuario->tienePermiso('servicios.contratar'),
        ]);
    }

    public function show(Request $request, string $clave, AccesoServicios $acceso, ServicioPagos $pagos): Response
    {
        return $this->pagina($request, $this->servicio($clave), $acceso, $pagos);
    }

    public function pago(Request $request, string $clave, ServicioPagos $pagos): RespuestaHttp
    {
        $resultado = $pagos->iniciar($this->servicio($clave), $this->negocio($request), $this->usuario($request));

        // Redirección externa al formulario seguro de Openpay.
        return Inertia::location($resultado['url']);
    }

    public function retorno(Request $request, AccesoServicios $acceso, ServicioPagos $pagos): Response
    {
        $folio = (string) $request->query('folio', '');
        $pago = $folio === '' ? null : Pago::query()->with('servicio')->where('folio', $folio)
            ->where('negocio_id', $this->negocio($request)->id)->first();

        abort_if($pago === null, 404);

        // Verificación servidor a servidor (el parámetro `id` que agrega
        // Openpay a la URL no se usa como prueba de nada).
        $pago = $pagos->verificar($pago);
        $acceso->olvidar();

        return $this->pagina($request, $pago->servicio, $acceso, $pagos, $pago);
    }

    private function pagina(Request $request, Servicio $servicio, AccesoServicios $acceso, ServicioPagos $pagos, ?Pago $pago = null): Response
    {
        $negocio = $this->negocio($request);
        $usuario = $this->usuario($request);

        return Inertia::render('cliente/servicios/Show', [
            'servicio' => $this->resumen($servicio, $negocio, $usuario, $acceso, $pagos),
            'pago' => $pago === null ? null : [
                'folio' => $pago->folio,
                'estado' => $pago->estado->value,
                'estado_etiqueta' => $pago->estado->etiqueta(),
                'importe_centavos' => $pago->importe_centavos,
                'moneda' => $pago->moneda,
                'mensaje_error' => $pago->intentos()->latest('id')->value('mensaje_error'),
                'en_proceso' => $pago->intentos()->whereIn('estado', [EstadoIntentoPago::Creado, EstadoIntentoPago::Pendiente])->exists(),
            ],
            'historial' => $servicio->es_base ? [] : Contratacion::query()->with('pago:id,folio,importe_centavos,moneda')
                ->where('negocio_id', $negocio->id)->where('servicio_id', $servicio->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (Contratacion $c) => [
                    'id' => $c->id,
                    'estado' => $c->estado->value,
                    'estado_etiqueta' => $c->estado->etiqueta(),
                    'origen' => $c->origen->etiqueta(),
                    'inicia_en' => $c->inicia_en?->toIso8601String(),
                    'vence_en' => $c->vence_en?->toIso8601String(),
                    'folio' => $c->pago?->folio,
                ]),
            'pagos' => $servicio->es_base ? [] : Pago::query()->where('negocio_id', $negocio->id)->where('servicio_id', $servicio->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (Pago $p) => [
                    'folio' => $p->folio,
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'importe_centavos' => $p->importe_centavos,
                    'moneda' => $p->moneda,
                    'proveedor' => $p->etiquetaProveedor(),
                    'fecha' => ($p->pagado_en ?? $p->created_at)?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Servicio $servicio, Negocio $negocio, User $usuario, AccesoServicios $acceso, ServicioPagos $pagos): array
    {
        $estado = $servicio->es_base
            ? ['activo' => true, 'solo_lectura' => false, 'estado' => 'incluido', 'vence_en' => null]
            : $acceso->estado($negocio, $servicio->clave);
        $ruta = self::RUTAS_MODULO[$servicio->clave] ?? null;

        return [
            'clave' => $servicio->clave,
            'nombre' => $servicio->nombre,
            'descripcion' => $servicio->descripcion,
            'beneficios' => $servicio->beneficios ?? [],
            'incluido' => $servicio->es_base,
            'precio_centavos' => $servicio->precio_centavos,
            'moneda' => $servicio->moneda,
            'impuestos_incluidos' => $servicio->impuestos_incluidos,
            'vigencia_meses' => $servicio->vigencia_meses,
            'estado' => $estado,
            'no_disponible' => $servicio->es_base ? null : $pagos->motivoNoDisponible($servicio, $negocio),
            'puede_contratar' => ! $servicio->es_base && $usuario->tienePermiso('servicios.contratar'),
            'url_modulo' => $ruta !== null && Route::has($ruta) && ($estado['activo'] || $estado['solo_lectura']) ? route($ruta) : null,
        ];
    }

    private function servicio(string $clave): Servicio
    {
        $servicio = Servicio::query()->where('clave', $clave)->first();
        abort_if($servicio === null || (! $servicio->activo && ! $servicio->es_base), 404);

        return $servicio;
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }

    private function negocio(Request $request): Negocio
    {
        $negocio = $this->usuario($request)->negocio;
        abort_if($negocio === null, 404);

        return $negocio;
    }
}
