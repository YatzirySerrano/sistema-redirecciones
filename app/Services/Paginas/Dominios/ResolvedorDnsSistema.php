<?php

namespace App\Services\Paginas\Dominios;

/**
 * Resolución con el resolvedor del sistema operativo (dns_get_record).
 * Solo consulta registros DNS: nunca se conecta al host.
 */
class ResolvedorDnsSistema implements ResolvedorDns
{
    public function txt(string $host): array
    {
        $valores = [];

        foreach ($this->consultar($host, DNS_TXT) as $registro) {
            $texto = $registro['txt'] ?? (isset($registro['entries']) ? implode('', (array) $registro['entries']) : null);

            if (is_string($texto)) {
                $valores[] = trim($texto, " \"\t");
            }
        }

        return $valores;
    }

    public function cname(string $host): array
    {
        return array_values(array_filter(array_map(
            fn (array $r) => isset($r['target']) ? strtolower(rtrim((string) $r['target'], '.')) : null,
            $this->consultar($host, DNS_CNAME),
        )));
    }

    public function ips(string $host): array
    {
        $ips = [];

        foreach ($this->consultar($host, DNS_A | DNS_AAAA) as $registro) {
            $ip = $registro['ip'] ?? $registro['ipv6'] ?? null;

            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $ips[] = strtolower($ip);
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultar(string $host, int $tipo): array
    {
        $resultado = @dns_get_record($host, $tipo);

        return is_array($resultado) ? $resultado : [];
    }
}
