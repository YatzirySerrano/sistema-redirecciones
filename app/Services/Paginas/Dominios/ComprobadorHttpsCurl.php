<?php

namespace App\Services\Paginas\Dominios;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Petición HTTPS a https://<host>/.well-known/dasti-verificacion/<token>,
 * forzando la conexión a una IP de entrada de DASTI (CURLOPT_RESOLVE): así
 * nunca se conecta a una IP arbitraria aunque el DNS cambie entre la
 * verificación y la petición (sin SSRF ni «DNS rebinding»). Verifica el
 * certificado y no sigue redirecciones.
 */
class ComprobadorHttpsCurl implements ComprobadorHttps
{
    public function comprobar(string $host, string $ip, string $token): ?string
    {
        $destino = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        try {
            $respuesta = Http::withOptions([
                'allow_redirects' => false,
                'verify' => true,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$destino}"]],
            ])->connectTimeout(5)->timeout(8)
                ->withHeaders(['User-Agent' => 'DASTI-Verificacion-Dominios/1.0'])
                ->get("https://{$host}/.well-known/dasti-verificacion/{$token}");
        } catch (Throwable $e) {
            $mensaje = $e->getMessage();

            return str_contains(strtolower($mensaje), 'ssl') || str_contains(strtolower($mensaje), 'certificate')
                ? 'El certificado HTTPS aún no está listo. Suele tardar unos minutos después de verificar el DNS.'
                : 'No pudimos conectar por HTTPS con el dominio.';
        }

        if ($respuesta->status() !== 200 || trim($respuesta->body()) !== $token) {
            return 'El dominio respondió, pero no con la página de DASTI. Revisa que no haya otro servicio configurado.';
        }

        return null;
    }
}
