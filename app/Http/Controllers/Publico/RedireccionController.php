<?php

namespace App\Http\Controllers\Publico;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\ResultadoAcceso;
use App\Http\Controllers\Controller;
use App\Jobs\RegistrarEventoAcceso;
use App\Models\Producto;
use App\Services\Redirecciones\GeneradorCodigo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Resuelve los enlaces permanentes (/r/{codigo}) impresos en QR o grabados
 * en NFC y redirige al destino actual.
 *
 * - 302 + Cache-Control: no-store, para que un cambio de destino tenga
 *   efecto inmediato (nunca 301, que los navegadores cachean).
 * - Sin sesión ni cookies: la ruta no usa el grupo de middleware "web".
 * - Casos no disponibles: página neutra sin datos del negocio (nunca 500).
 */
class RedireccionController extends Controller
{
    public function __invoke(Request $request, string $codigo, GeneradorCodigo $generador): RedirectResponse|Response
    {
        $codigo = Str::lower($codigo);

        if (! $generador->tieneFormatoValido($codigo)) {
            return $this->noDisponible(404);
        }

        $producto = Producto::query()
            ->select(['id', 'negocio_id', 'url_destino', 'estado'])
            ->with('negocio:id,estado')
            ->where('codigo', $codigo)
            ->first();

        if ($producto === null) {
            return $this->noDisponible(404);
        }

        $resultado = match (true) {
            $producto->negocio->estado !== EstadoCuenta::Activo => ResultadoAcceso::NegocioSuspendido,
            $producto->estado !== EstadoProducto::Activo => ResultadoAcceso::Suspendido,
            $producto->url_destino === null => ResultadoAcceso::SinDestino,
            default => ResultadoAcceso::Redirigido,
        };

        if (! $request->isMethod('HEAD')) {
            $this->registrarAcceso($request, $producto, $resultado);
        }

        if ($resultado !== ResultadoAcceso::Redirigido) {
            return $this->noDisponible($resultado === ResultadoAcceso::SinDestino ? 404 : 410);
        }

        return redirect()->away((string) $producto->url_destino, 302, $this->cabecerasSinCache());
    }

    private function registrarAcceso(Request $request, Producto $producto, ResultadoAcceso $resultado): void
    {
        $agente = mb_substr((string) $request->userAgent(), 0, 512);
        $hoy = now()->toDateString();

        // Huella anónima: HMAC(IP + agente + día). No permite recuperar la IP
        // y cambia cada día, por lo que no sirve para seguir a una persona.
        $huella = hash_hmac('sha256', $request->ip().'|'.$agente.'|'.$hoy, (string) config('app.key'));

        $job = new RegistrarEventoAcceso(
            productoId: $producto->id,
            negocioId: $producto->negocio_id,
            resultado: $resultado,
            ocurridoEn: now()->toDateTimeString(),
            agente: $agente === '' ? null : $agente,
            huellaDiaria: $huella,
        );

        if (config('dasti.eventos_en_cola')) {
            dispatch($job);

            return;
        }

        // Se ejecuta tras enviar la respuesta (también en 404/410). A
        // diferencia de dispatch()->afterResponse(), los callbacks diferidos
        // se vacían en cada petición y no se acumulan en procesos de larga
        // vida ni en las pruebas.
        defer(fn () => dispatch_sync($job), always: true);
    }

    private function noDisponible(int $estado): Response
    {
        return response()
            ->view('publico.no-disponible', ['estado' => $estado], $estado)
            ->withHeaders($this->cabecerasSinCache());
    }

    /**
     * @return array<string, string>
     */
    private function cabecerasSinCache(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
