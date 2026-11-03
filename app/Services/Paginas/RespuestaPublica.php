<?php

namespace App\Services\Paginas;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Cabeceras de seguridad y respuestas neutras de las páginas públicas. No
 * llevan marca de DASTI ni datos del negocio.
 */
class RespuestaPublica
{
    private ?string $nonce = null;

    public function nonce(): string
    {
        return $this->nonce ??= Str::random(32);
    }

    /**
     * @return array<string, string>
     */
    public function cabeceras(bool $indexar = true): array
    {
        $nonce = $this->nonce();
        $script = ["'self'", "'nonce-{$nonce}'"];
        $estilo = ["'self'", "'nonce-{$nonce}'", 'https://fonts.bunny.net'];
        $conexion = ["'self'"];

        // Desarrollo con el servidor de Vite: sus recursos vienen de otro
        // origen e inyecta estilos en línea.
        if (Vite::isRunningHot()) {
            $origen = rtrim((string) file_get_contents(public_path('hot')));
            $ws = (string) preg_replace('#^http#', 'ws', $origen);

            // Una IP v6 literal ([::1]) no puede expresarse como origen en
            // CSP: solo en desarrollo se usa el esquema.
            if (str_contains($origen, '://[')) {
                [$origen, $ws] = [(string) strstr($origen, '//', true), (string) strstr($ws, '//', true)];
            }

            $script[] = $origen;
            $estilo = [...$estilo, $origen, "'unsafe-inline'"];
            $conexion = [...$conexion, $origen, $ws];
            $estilo = array_values(array_diff($estilo, ["'nonce-{$nonce}'"]));
        }

        $csp = implode('; ', [
            "default-src 'none'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $estilo),
            'font-src https://fonts.bunny.net',
            "img-src 'self' data:",
            "media-src 'self'",
            'frame-src '.implode(' ', Insertados::ORIGENES),
            'connect-src '.implode(' ', $conexion),
            "form-action 'self'",
            "base-uri 'none'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "manifest-src 'self'",
        ]);

        return [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=(), browsing-topics=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            'Cache-Control' => 'private, no-cache',
            'X-Robots-Tag' => $indexar ? 'index, follow' : 'noindex, nofollow',
        ];
    }

    /**
     * Página «no disponible» neutra (sin marca ni datos del negocio).
     */
    public function noDisponible(int $estado = 404): Response
    {
        $estado = in_array($estado, [404, 410, 429], true) ? $estado : 404;

        return response()
            ->view('paginas.no-disponible', ['estado' => $estado], $estado)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Robots-Tag' => 'noindex, nofollow',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'",
            ]);
    }
}
