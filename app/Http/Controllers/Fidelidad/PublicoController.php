<?php

namespace App\Http\Controllers\Fidelidad;

use App\Http\Controllers\Controller;
use App\Models\Fidelidad\Recuperacion;
use App\Services\Fidelidad\AccesoConsumidor;
use App\Services\Fidelidad\Diseno\PresentacionPublica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Páginas públicas del consumidor: SOLO LECTURA. Escanear la tarjeta o
 * abrir el enlace muestra el progreso; nunca registra visitas, canjea ni
 * modifica nada. Sin datos de contacto, `noindex` y sin caché. La tarjeta
 * se dibuja con el diseño publicado por el negocio (o el predeterminado).
 */
class PublicoController extends Controller
{
    public function __construct(
        private readonly AccesoConsumidor $acceso,
        private readonly PresentacionPublica $presentacion,
    ) {}

    public function credencial(string $codigo): Response
    {
        $credencial = $this->acceso->porCredencial($codigo);

        if ($credencial === null || ! $credencial->estaActiva() || ! $credencial->consumidor->estaActivo()) {
            return $this->vista('fidelidad.mensaje', [
                'titulo' => 'Tarjeta no disponible',
                'mensaje' => 'Esta tarjeta no está activa. Si la reemplazaste, usa la nueva o pide ayuda en el negocio.',
            ], 404);
        }

        return $this->vista('fidelidad.progreso', $this->presentacion->paraConsumidor($credencial->consumidor));
    }

    public function enlace(string $token): Response
    {
        $consumidor = $this->acceso->porToken($token);

        if ($consumidor === null) {
            return $this->vista('fidelidad.mensaje', [
                'titulo' => 'Enlace no válido',
                'mensaje' => 'Este enlace ya no funciona. Puede que se haya renovado. Recupéralo con tu correo o pide uno nuevo en el negocio.',
                'recuperar' => true,
            ], 404);
        }

        return $this->vista('fidelidad.progreso', $this->presentacion->paraConsumidor($consumidor));
    }

    public function recuperar(): Response
    {
        return $this->vista('fidelidad.recuperar', ['enviado' => session('enviado', false)]);
    }

    public function enviarRecuperacion(Request $request): RedirectResponse
    {
        $datos = $request->validate(['email' => ['required', 'email:rfc', 'max:255']], [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
        ]);

        $this->acceso->solicitarRecuperacion($datos['email']);

        // Respuesta idéntica exista o no la cuenta.
        return redirect()->route('fidelidad.publico.recuperar')->with('enviado', true);
    }

    public function verificar(string $token): Response
    {
        $resultado = $this->acceso->usarEnlace($token, Recuperacion::VERIFICAR);

        return $this->vista('fidelidad.mensaje', $resultado === null
            ? ['titulo' => 'Enlace no válido', 'mensaje' => 'El enlace caducó o ya se usó. Pide al negocio que te lo envíe de nuevo.']
            : ['titulo' => 'Correo confirmado', 'mensaje' => 'Listo. Si pierdes tu enlace de consulta podrás recuperarlo con este correo.'],
            $resultado === null ? 404 : 200);
    }

    public function renovar(string $token): Response|RedirectResponse
    {
        $resultado = $this->acceso->usarEnlace($token, Recuperacion::RENOVAR);

        if ($resultado === null || $resultado['token'] === null) {
            return $this->vista('fidelidad.mensaje', [
                'titulo' => 'Enlace no válido',
                'mensaje' => 'El enlace de recuperación caducó o ya se usó. Solicita otro.',
                'recuperar' => true,
            ], 404);
        }

        return redirect()->route('fidelidad.publico.enlace', $resultado['token']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function vista(string $vista, array $datos, int $estado = 200): Response
    {
        return response()->view($vista, $datos, $estado)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
