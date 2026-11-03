<?php

namespace App\Services\Paginas;

/**
 * Hosts que pertenecen a DASTI (panel, enlaces permanentes y páginas en
 * /l/...). Cualquier otro host solo puede ser un dominio personalizado.
 */
class HostsPropios
{
    /** @var list<string>|null */
    private ?array $hosts = null;

    /**
     * @return list<string>
     */
    public function lista(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }

        $hosts = [
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('dasti.url_publica'), PHP_URL_HOST),
            ...(array) config('dasti.paginas.hosts_propios', []),
            // Desarrollo local y comprobaciones del propio servidor.
            'localhost', '127.0.0.1', '::1', '[::1]',
        ];

        return $this->hosts = array_values(array_unique(array_filter(array_map(
            fn ($h) => is_string($h) ? strtolower(rtrim(trim($h), '.')) : '',
            $hosts,
        ))));
    }

    public function esPropio(string $host): bool
    {
        return in_array(strtolower(rtrim($host, '.')), $this->lista(), true);
    }

    /**
     * ¿El host es (o cuelga de) un dominio de DASTI? Ningún negocio puede
     * registrarlo como dominio propio.
     */
    public function esDeDasti(string $host): bool
    {
        $raices = [...$this->lista(), ...(array) config('dasti.paginas.dominios_reservados', [])];
        $cname = strtolower((string) config('dasti.paginas.dominios.cname'));

        if ($cname !== '') {
            $raices[] = $cname;
        }

        foreach ($raices as $raiz) {
            $raiz = strtolower(trim((string) $raiz));

            if ($raiz !== '' && ($host === $raiz || str_ends_with($host, '.'.$raiz))) {
                return true;
            }
        }

        return false;
    }
}
