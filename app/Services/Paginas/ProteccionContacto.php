<?php

namespace App\Services\Paginas;

use App\Models\Paginas\PaginaEnlaces;

/**
 * Protección del formulario de contacto sin sesión ni cookies:
 *
 * - Marca de tiempo firmada (HMAC con APP_KEY) emitida al mostrar la página:
 *   impide envíos instantáneos de robots (tiempo mínimo de llenado) y
 *   reutilizar formularios muy antiguos.
 * - Campo trampa (honeypot) que una persona no ve.
 * - Límites por IP y por página (RateLimiter, la IP solo se usa como llave
 *   temporal en caché y nunca se guarda) y límite de enlaces en el mensaje.
 */
class ProteccionContacto
{
    public function marca(PaginaEnlaces $pagina, ?int $momento = null): string
    {
        $momento ??= time();

        return $momento.'.'.$this->firma($pagina, $momento);
    }

    /**
     * Mensaje de error para el visitante, o null si la marca es válida.
     */
    public function errorMarca(mixed $marca, PaginaEnlaces $pagina): ?string
    {
        if (! is_string($marca) || ! preg_match('/^(\d{9,11})\.([0-9a-f]{64})$/', $marca, $m)) {
            return 'Recarga la página e inténtalo nuevamente.';
        }

        $momento = (int) $m[1];

        if (! hash_equals($this->firma($pagina, $momento), $m[2])) {
            return 'Recarga la página e inténtalo nuevamente.';
        }

        $transcurrido = time() - $momento;

        if ($transcurrido < (int) config('dasti.paginas.contacto.segundos_minimos', 3)) {
            return 'Revisa tu mensaje y vuelve a enviarlo en unos segundos.';
        }

        if ($transcurrido > (int) config('dasti.paginas.contacto.horas_validez_marca', 48) * 3600) {
            return 'El formulario caducó. Recarga la página e inténtalo nuevamente.';
        }

        return null;
    }

    public function enlacesEn(string $texto): int
    {
        return (int) preg_match_all('#(https?://|www\.)#i', $texto);
    }

    private function firma(PaginaEnlaces $pagina, int $momento): string
    {
        return hash_hmac('sha256', 'contacto|'.$pagina->id.'|'.$momento, (string) config('app.key'));
    }
}
