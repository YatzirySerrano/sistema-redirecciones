<?php

namespace Tests\Concerns;

use App\Services\Paginas\Dominios\ResolvedorDns;

/**
 * DNS simulado: registros por host.
 */
class ResolvedorDnsFalso implements ResolvedorDns
{
    /** @var array<string, list<string>> */
    public array $txt = [];

    /** @var array<string, list<string>> */
    public array $cname = [];

    /** @var array<string, list<string>> */
    public array $ips = [];

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }

    public function cname(string $host): array
    {
        return $this->cname[$host] ?? [];
    }

    public function ips(string $host): array
    {
        return $this->ips[$host] ?? [];
    }
}
