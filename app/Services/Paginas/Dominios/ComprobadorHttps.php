<?php

namespace App\Services\Paginas\Dominios;

/**
 * Comprueba que el dominio responde por HTTPS con un certificado válido y
 * que la respuesta proviene de DASTI (token de verificación).
 */
interface ComprobadorHttps
{
    /**
     * @param  string  $ip  IP de entrada de DASTI a la que se fuerza la conexión.
     * @return string|null Mensaje de error, o null si respondió el token correcto.
     */
    public function comprobar(string $host, string $ip, string $token): ?string;
}
