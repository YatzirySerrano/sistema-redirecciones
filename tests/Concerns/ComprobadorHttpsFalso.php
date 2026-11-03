<?php

namespace Tests\Concerns;

use App\Services\Paginas\Dominios\ComprobadorHttps;

/**
 * HTTPS simulado: registra las llamadas y responde lo configurado.
 */
class ComprobadorHttpsFalso implements ComprobadorHttps
{
    public ?string $error = null;

    /** @var list<array{host: string, ip: string, token: string}> */
    public array $llamadas = [];

    public function comprobar(string $host, string $ip, string $token): ?string
    {
        $this->llamadas[] = ['host' => $host, 'ip' => $ip, 'token' => $token];

        return $this->error;
    }
}
