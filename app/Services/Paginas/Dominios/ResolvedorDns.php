<?php

namespace App\Services\Paginas\Dominios;

/**
 * Consultas DNS (interfaz para poder simularlas en las pruebas).
 */
interface ResolvedorDns
{
    /** @return list<string> Valores de los registros TXT. */
    public function txt(string $host): array;

    /** @return list<string> Destinos CNAME (sin punto final, en minúsculas). */
    public function cname(string $host): array;

    /** @return list<string> Direcciones IPv4 y IPv6 (A y AAAA, siguiendo CNAME). */
    public function ips(string $host): array;
}
