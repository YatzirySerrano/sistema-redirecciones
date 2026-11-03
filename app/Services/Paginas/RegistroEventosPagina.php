<?php

namespace App\Services\Paginas;

use App\Jobs\RegistrarEventoPagina;
use App\Models\Paginas\EventoPagina;
use App\Models\Paginas\PaginaEnlaces;
use Illuminate\Http\Request;

/**
 * Programa el registro de eventos sin retrasar la respuesta. Se ignoran las
 * solicitudes HEAD y las de precarga (prefetch/prerender) de los
 * navegadores, que no son visitas reales.
 */
class RegistroEventosPagina
{
    public function __construct(private readonly HostsPropios $hosts) {}

    public function registrar(Request $request, PaginaEnlaces $pagina, string $tipo, ?string $bloqueId = null, ?string $subpagina = null): void
    {
        if ($request->isMethod('HEAD') || $this->esPrecarga($request)) {
            return;
        }

        $agente = mb_substr((string) $request->userAgent(), 0, 512);
        $hoy = now()->toDateString();

        // Huella anónima diaria (solo visitas): HMAC(IP + agente + día +
        // página). No permite recuperar la IP y cambia cada día.
        $huella = $tipo === EventoPagina::VISITA
            ? hash_hmac('sha256', $request->ip().'|'.$agente.'|'.$hoy.'|'.$pagina->id, (string) config('app.key'))
            : null;

        $job = new RegistrarEventoPagina(
            paginaId: $pagina->id,
            negocioId: $pagina->negocio_id,
            tipo: $tipo,
            bloqueId: $bloqueId,
            subpagina: $subpagina !== null ? mb_substr($subpagina, 0, 40) : null,
            ocurridoEn: now()->toDateTimeString(),
            agente: $agente === '' ? null : $agente,
            referente: $tipo === EventoPagina::VISITA ? $this->referente($request) : null,
            huellaDiaria: $huella,
        );

        if (config('dasti.eventos_en_cola')) {
            dispatch($job);

            return;
        }

        defer(fn () => dispatch_sync($job), always: true);
    }

    public function esPrecarga(Request $request): bool
    {
        $proposito = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        return str_contains($proposito, 'prefetch') || str_contains($proposito, 'prerender')
            || strtolower((string) $request->header('X-Moz')) === 'prefetch';
    }

    /**
     * Solo el host de procedencia (sin ruta ni parámetros). La navegación
     * interna de la propia página no cuenta como procedencia.
     */
    private function referente(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $host = (string) preg_replace('/^(www|m|l|lm)\./', '', $host);

        if ($host === '' || ! preg_match('/^[a-z0-9.-]{1,120}$/', $host) || $host === strtolower($request->getHost()) || $this->hosts->esPropio($host)) {
            return null;
        }

        return $host;
    }
}
