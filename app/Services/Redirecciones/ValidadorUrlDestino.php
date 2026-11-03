<?php

namespace App\Services\Redirecciones;

use App\Enums\EstadoPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Services\Configuracion\AjustesPlataforma;

/**
 * Valida y normaliza las URLs de destino de los enlaces permanentes.
 *
 * La redirección la realiza el navegador del visitante: el servidor nunca
 * descarga ni consulta el destino. Aun así se restringen esquemas, hosts
 * internos y patrones abusivos para no convertir el servicio en un
 * redireccionador abierto ni en un vector de phishing contra redes privadas.
 */
class ValidadorUrlDestino
{
    public const LONGITUD_MAXIMA = 2048;

    private const SUFIJOS_INTERNOS = ['localhost', 'local', 'internal', 'intranet', 'lan', 'home', 'corp', 'localdomain', 'home.arpa', 'test', 'invalid', 'example'];

    public function __construct(private readonly AjustesPlataforma $ajustes) {}

    /**
     * Normaliza la URL sin cambiar su significado: recorta espacios, convierte
     * dominios internacionales a Punycode y codifica en porcentaje los
     * caracteres no ASCII de la ruta, de modo que la cabecera Location sea
     * siempre ASCII válido. No añade esquemas implícitos.
     */
    public function normalizar(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*://)([^/?\#]*)(.*)$#s', $url, $partes)) {
            return $url;
        }

        [, $esquema, $autoridad, $resto] = $partes;

        if (preg_match('/[^\x00-\x7F]/', $autoridad)) {
            $arroba = strrpos($autoridad, '@');
            $usuario = $arroba === false ? '' : substr($autoridad, 0, $arroba + 1);
            $host = $arroba === false ? $autoridad : substr($autoridad, $arroba + 1);
            $puerto = '';

            if (preg_match('/^(.*?)(:\d+)$/', $host, $coincidencia)) {
                [, $host, $puerto] = $coincidencia;
            }

            $ascii = idn_to_ascii(mb_strtolower($host), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                $autoridad = $usuario.$ascii.$puerto;
            }
        }

        $resto = (string) preg_replace_callback('/[^\x00-\x7F]+/', fn (array $m) => rawurlencode($m[0]), $resto);

        return $esquema.$autoridad.$resto;
    }

    /**
     * Devuelve el mensaje de error o null si la URL es aceptable.
     */
    public function error(string $url): ?string
    {
        $url = $this->normalizar($url);

        if ($url === '') {
            return 'Escribe la URL de destino.';
        }

        if (strlen($url) > self::LONGITUD_MAXIMA) {
            return 'La URL es demasiado larga (máximo '.self::LONGITUD_MAXIMA.' caracteres).';
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url) || str_contains($url, '\\')) {
            return 'La URL contiene espacios o caracteres no permitidos.';
        }

        $partes = parse_url($url);

        if ($partes === false || ! isset($partes['scheme'], $partes['host'])) {
            return 'La URL no es válida. Debe comenzar con https:// e incluir un dominio.';
        }

        $esquema = strtolower($partes['scheme']);
        $permitidos = $this->ajustes->booleano('permitir_destinos_http') ? ['https', 'http'] : ['https'];

        if (! in_array($esquema, $permitidos, true)) {
            return $esquema === 'http'
                ? 'Solo se permiten destinos seguros (https://).'
                : 'El tipo de enlace no está permitido. Usa una dirección web que comience con https://.';
        }

        if (isset($partes['user']) || isset($partes['pass'])) {
            return 'La URL no puede incluir usuarios ni contraseñas.';
        }

        if (isset($partes['port']) && ! in_array((int) $partes['port'], [80, 443], true)) {
            return 'La URL no puede usar puertos personalizados.';
        }

        $host = $this->hostAscii($partes['host']);

        if ($host === null) {
            return 'El dominio de la URL no es válido.';
        }

        if ($this->esDireccionIp($host)) {
            return 'Usa un nombre de dominio en lugar de una dirección IP.';
        }

        // Única excepción al bloqueo del propio servicio: una Página de
        // enlaces publicada (el host es exactamente la URL pública de DASTI).
        if ($this->esPaginaDeEnlacesPublicada($host, $partes)) {
            return null;
        }

        if (! $this->tieneFormatoDeDominioPublico($host)) {
            return 'El dominio de la URL no es válido o no es público.';
        }

        if ($this->esHostInterno($host)) {
            return 'No se permiten destinos internos o locales.';
        }

        if ($this->esDominioPropio($host)) {
            return 'El destino no puede apuntar a este mismo servicio de redirecciones.';
        }

        if ($this->estaBloqueado($host)) {
            return 'Ese dominio no está permitido como destino. Usa la dirección final, sin acortadores.';
        }

        return null;
    }

    public function esValida(string $url): bool
    {
        return $this->error($url) === null;
    }

    private function hostAscii(string $host): ?string
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || str_starts_with($host, '[')) {
            // Las IPv6 literales llegan entre corchetes: se tratan como IP.
            return $host === '' ? null : $host;
        }

        if (preg_match('/[^\x20-\x7E]/', $host)) {
            $convertido = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            return $convertido === false ? null : strtolower($convertido);
        }

        return $host;
    }

    private function esDireccionIp(string $host): bool
    {
        $sinCorchetes = trim($host, '[]');

        if (filter_var($sinCorchetes, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        // Formas numéricas alternativas de IPv4 (decimal, hexadecimal, octal)
        // que algunos navegadores interpretan como IP: 2130706433, 0x7f.0.0.1…
        return (bool) preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/i', $host);
    }

    private function tieneFormatoDeDominioPublico(string $host): bool
    {
        if (strlen($host) > 253 || ! str_contains($host, '.')) {
            return false;
        }

        foreach (explode('.', $host) as $etiqueta) {
            if (! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $etiqueta)) {
                return false;
            }
        }

        // El dominio de primer nivel debe ser alfabético (o IDN "xn--").
        $tld = substr($host, strrpos($host, '.') + 1);

        return (bool) preg_match('/^([a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', $tld);
    }

    private function esHostInterno(string $host): bool
    {
        foreach (self::SUFIJOS_INTERNOS as $sufijo) {
            if ($host === $sufijo || str_ends_with($host, '.'.$sufijo)) {
                return true;
            }
        }

        return false;
    }

    private function esDominioPropio(string $host): bool
    {
        $propios = array_filter(array_unique([
            parse_url((string) config('dasti.url_publica'), PHP_URL_HOST),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ]));

        foreach ($propios as $propio) {
            $propio = strtolower((string) $propio);

            if ($host === $propio || str_ends_with($host, '.'.$propio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Única excepción al bloqueo del dominio propio: una Página de enlaces
     * PUBLICADA en el prefijo público (https://<url_publica>/l/{slug} o una
     * de sus subpáginas), sin parámetros ni fragmentos. Así un QR/NFC puede
     * llevar a la página de enlaces del negocio sin abrir bucles hacia /r/
     * ni hacia el panel.
     *
     * @param  array<string, mixed>  $partes
     */
    private function esPaginaDeEnlacesPublicada(string $host, array $partes): bool
    {
        $publico = strtolower((string) parse_url((string) config('dasti.url_publica'), PHP_URL_HOST));
        $prefijo = preg_quote(trim((string) config('dasti.paginas.prefijo', 'l'), '/'), '#');

        if ($host !== $publico || isset($partes['query']) || isset($partes['fragment'])) {
            return false;
        }

        if (! preg_match('#^/'.$prefijo.'/([a-z0-9-]{3,40})(?:/[a-z0-9-]{1,40})?/?$#', (string) ($partes['path'] ?? ''), $m)) {
            return false;
        }

        return PaginaEnlaces::query()->where('slug', $m[1])->where('estado', EstadoPagina::Publicada)->whereNotNull('publicado')->exists();
    }

    private function estaBloqueado(string $host): bool
    {
        foreach ($this->ajustes->dominiosBloqueados() as $bloqueado) {
            $bloqueado = strtolower(trim($bloqueado));

            if ($bloqueado !== '' && ($host === $bloqueado || str_ends_with($host, '.'.$bloqueado))) {
                return true;
            }
        }

        return false;
    }
}
