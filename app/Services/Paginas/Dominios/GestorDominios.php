<?php

namespace App\Services\Paginas\Dominios;

use App\Enums\EstadoDominio;
use App\Models\Paginas\DominioPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Paginas\HostsPropios;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dominios personalizados de las Páginas de enlaces (mecanismo real):
 *
 * 1. Registro del host (normalizado, IDN → Punycode) con un token aleatorio.
 * 2. Propiedad: registro TXT `_dasti-verificacion.<host>` = token.
 * 3. Destino: CNAME al host configurado o A/AAAA solo a las IP de entrada.
 * 4. HTTPS: Caddy emite y renueva el certificado bajo demanda (on_demand_tls
 *    pregunta a /interno/dominios/autorizar, que solo autoriza dominios con
 *    DNS verificado). Después se comprueba por HTTPS, conectando únicamente
 *    a una IP de entrada de DASTI, que el dominio sirve el token.
 *
 * Un dominio solo se muestra «activo» si pasaron todas las comprobaciones.
 */
class GestorDominios
{
    private const SUFIJOS_INTERNOS = ['localhost', 'local', 'internal', 'intranet', 'lan', 'home', 'corp', 'localdomain', 'home.arpa', 'test', 'invalid', 'example', 'onion', 'arpa'];

    /** Días que un dominio puede seguir «pendiente» antes de marcarse con error. */
    private const DIAS_PENDIENTE = 7;

    public function __construct(
        private readonly ResolvedorDns $dns,
        private readonly ComprobadorHttps $https,
        private readonly HostsPropios $hosts,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @throws ValidationException
     */
    public function normalizar(string $entrada): string
    {
        $host = strtolower(trim($entrada));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $host);
        $host = rtrim(explode('/', explode('?', $host)[0])[0], '.');

        $error = fn (string $m) => ValidationException::withMessages(['host' => $m]);

        if ($host === '') {
            throw $error('Escribe el dominio, por ejemplo enlaces.tunegocio.com.');
        }

        if (str_contains($host, '@') || str_contains($host, ':') || str_contains($host, '*') || str_contains($host, ' ')) {
            throw $error('Escribe solo el dominio, sin «https://», puertos, rutas ni comodines.');
        }

        if (preg_match('/[^\x20-\x7E]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii === false) {
                throw $error('El dominio no es válido.');
            }

            $host = strtolower($ascii);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/i', $host)) {
            throw $error('Usa un nombre de dominio, no una dirección IP.');
        }

        if (strlen($host) > 253 || ! str_contains($host, '.')) {
            throw $error('El dominio no es válido. Debe incluir una extensión, por ejemplo .com o .mx.');
        }

        foreach (explode('.', $host) as $etiqueta) {
            if (! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $etiqueta)) {
                throw $error('El dominio no es válido.');
            }
        }

        $tld = substr($host, (int) strrpos($host, '.') + 1);

        if (! preg_match('/^([a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', $tld)) {
            throw $error('El dominio no es válido.');
        }

        foreach (self::SUFIJOS_INTERNOS as $sufijo) {
            if ($host === $sufijo || str_ends_with($host, '.'.$sufijo)) {
                throw $error('Ese dominio es interno o de pruebas; usa un dominio público.');
            }
        }

        if ($this->hosts->esDeDasti($host)) {
            throw $error('No puedes usar un dominio de DASTI. Usa un dominio propio de tu negocio.');
        }

        return $host;
    }

    /**
     * @throws ValidationException
     */
    public function registrar(PaginaEnlaces $pagina, string $entrada, User $usuario, ?DominioPagina $reemplaza = null): DominioPagina
    {
        $host = $this->normalizar($entrada);

        if ($reemplaza !== null && $reemplaza->pagina_id !== $pagina->id) {
            abort(404);
        }

        if (DominioPagina::query()->where('pagina_id', $pagina->id)->where('host', $host)->exists()) {
            throw ValidationException::withMessages(['host' => 'Ya agregaste este dominio.']);
        }

        if (DominioPagina::query()->where('host_verificado', $host)->where('negocio_id', '!=', $pagina->negocio_id)->exists()) {
            throw ValidationException::withMessages(['host' => 'Este dominio ya está conectado a otra cuenta. Si es tuyo, contacta a DASTI para revisarlo.']);
        }

        $maximo = (int) config('dasti.paginas.dominios.max_por_pagina', 3);

        if (DominioPagina::query()->where('pagina_id', $pagina->id)->count() >= $maximo + ($reemplaza !== null ? 1 : 0)) {
            throw ValidationException::withMessages(['host' => "Puedes conectar hasta {$maximo} dominios. Desvincula uno para agregar otro."]);
        }

        $unicode = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        try {
            $dominio = DominioPagina::query()->create([
                'pagina_id' => $pagina->id,
                'negocio_id' => $pagina->negocio_id,
                'host' => $host,
                'host_unicode' => $unicode !== false ? $unicode : $host,
                'estado' => EstadoDominio::Pendiente,
                'token' => 'dasti-'.strtolower(Str::random(32)),
                'es_principal' => ! DominioPagina::query()->where('pagina_id', $pagina->id)->exists(),
                'reemplaza_a_id' => $reemplaza?->id,
                'proxima_revision_en' => now()->addMinutes(5),
                'creado_por' => $usuario->id,
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['host' => 'Ya agregaste este dominio.']);
        }

        $this->auditoria->registrar(
            accion: 'pagina.dominio_agregado',
            descripcion: "Dominio «{$host}» agregado a la página de enlaces",
            recurso: $dominio,
            negocio: $pagina->negocio_id,
            nuevos: ['host' => $host, 'reemplaza' => $reemplaza?->host],
            actor: $usuario,
        );

        return $dominio;
    }

    /**
     * Revisa DNS y HTTPS y actualiza el estado. Nunca marca «activo» sin
     * que pasen todas las comprobaciones.
     */
    public function verificar(DominioPagina $dominio, ?User $usuario = null): DominioPagina
    {
        $cnameEsperado = strtolower(rtrim((string) config('dasti.paginas.dominios.cname'), '.'));
        $ingreso = array_map('strtolower', (array) config('dasti.paginas.dominios.ips_ingreso', []));

        $txt = in_array($dominio->token, $this->dns->txt($dominio->nombreRegistroTxt()), true);
        $cnames = $this->dns->cname($dominio->host);
        $ips = $this->dns->ips($dominio->host);
        $porCname = $cnameEsperado !== '' && in_array($cnameEsperado, $cnames, true);
        $porIp = $ips !== [] && $ingreso !== [] && array_diff($ips, $ingreso) === [];
        $ipsIngreso = array_values(array_intersect($ips, $ingreso));

        $diagnostico = [
            'txt_encontrado' => $txt,
            'cname' => array_slice($cnames, 0, 5),
            'ips' => array_slice($ips, 0, 8),
            'apunta_a_dasti' => $porCname || $porIp,
            'https' => null,
            'revisado_en' => now()->toIso8601String(),
        ];

        $anterior = $dominio->estado;
        $error = match (true) {
            ! $txt => 'Aún no encontramos el registro TXT de verificación. Los cambios de DNS pueden tardar hasta 24 horas.',
            ! $porCname && ! $porIp => $cnames !== []
                ? 'El dominio apunta a «'.$cnames[0].'» en lugar de a '.$cnameEsperado.'.'
                : ($ips !== [] ? 'El dominio apunta a una dirección que no es de DASTI ('.$ips[0].').' : 'El dominio todavía no apunta a DASTI.'),
            default => null,
        };

        if ($error === null) {
            $ocupado = DominioPagina::query()->where('host_verificado', $dominio->host)->whereKeyNot($dominio->id)->exists();

            if ($ocupado) {
                $error = 'Este dominio ya está conectado a otra cuenta.';
            }
        }

        if ($error !== null) {
            return $this->registrarFallo($dominio, $error, $diagnostico, $usuario, $anterior);
        }

        // DNS correcto: el host ya se puede enrutar y Caddy puede emitir el
        // certificado. El índice único de host_verificado impide que dos
        // negocios lo verifiquen a la vez.
        try {
            $dominio->forceFill([
                'host_verificado' => $dominio->host,
                'dns_verificado_en' => $dominio->dns_verificado_en ?? now(),
                'estado' => $dominio->estado === EstadoDominio::Activo ? EstadoDominio::Activo : EstadoDominio::DnsVerificado,
            ])->save();
        } catch (QueryException) {
            return $this->registrarFallo($dominio->refresh(), 'Este dominio ya está conectado a otra cuenta.', $diagnostico, $usuario, $anterior);
        }

        $errorHttps = match (true) {
            $ingreso === [] => 'DASTI está terminando de configurar la activación HTTPS. Volveremos a revisar automáticamente.',
            $ipsIngreso === [] => 'El dominio aún no resuelve a la IP de DASTI. Volveremos a revisar automáticamente.',
            default => $this->https->comprobar($dominio->host, $ipsIngreso[0], $dominio->token),
        };
        $diagnostico['https'] = $errorHttps === null;

        if ($errorHttps !== null) {
            // DNS correcto pero sin HTTPS comprobado: nunca «activo».
            $estado = $dominio->estado === EstadoDominio::Activo && $dominio->fallos_consecutivos + 1 < (int) config('dasti.paginas.dominios.fallos_para_error', 3)
                ? EstadoDominio::Activo
                : EstadoDominio::DnsVerificado;

            $dominio->forceFill([
                'estado' => $estado,
                'ultimo_error' => $errorHttps,
                'fallos_consecutivos' => $dominio->estado === EstadoDominio::Activo ? $dominio->fallos_consecutivos + 1 : 0,
                'diagnostico' => $diagnostico,
                'ultima_revision_en' => now(),
                'proxima_revision_en' => now()->addMinutes(10),
            ])->save();

            $this->auditarCambio($dominio, $anterior, $usuario);

            return $dominio;
        }

        $dominio->forceFill([
            'estado' => EstadoDominio::Activo,
            'https_verificado_en' => $dominio->https_verificado_en ?? now(),
            'ultimo_error' => null,
            'fallos_consecutivos' => 0,
            'diagnostico' => $diagnostico,
            'ultima_revision_en' => now(),
            'proxima_revision_en' => now()->addDay(),
        ])->save();

        if ($anterior !== EstadoDominio::Activo) {
            $this->completarReemplazo($dominio, $usuario);
            $this->asegurarPrincipal($dominio->pagina_id);
        }

        $this->auditarCambio($dominio, $anterior, $usuario);

        return $dominio->refresh();
    }

    public function hacerPrincipal(DominioPagina $dominio, User $usuario): void
    {
        if (! $dominio->estaActivo()) {
            throw ValidationException::withMessages(['dominio' => 'Solo un dominio activo con HTTPS puede ser el principal.']);
        }

        DB::transaction(function () use ($dominio) {
            DominioPagina::query()->where('pagina_id', $dominio->pagina_id)->update(['es_principal' => false]);
            $dominio->forceFill(['es_principal' => true])->save();
        });

        $this->auditoria->registrar(
            accion: 'pagina.dominio_principal',
            descripcion: "Dominio principal: «{$dominio->host}»",
            recurso: $dominio,
            negocio: $dominio->negocio_id,
            nuevos: ['host' => $dominio->host],
            actor: $usuario,
        );
    }

    public function eliminar(DominioPagina $dominio, ?User $usuario): void
    {
        $paginaId = $dominio->pagina_id;
        $dominio->delete();
        $this->asegurarPrincipal($paginaId);

        $this->auditoria->registrar(
            accion: 'pagina.dominio_eliminado',
            descripcion: "Dominio «{$dominio->host}» desvinculado de la página de enlaces",
            recurso: $dominio,
            negocio: $dominio->negocio_id,
            anteriores: ['host' => $dominio->host, 'estado' => $dominio->estado->value],
            actor: $usuario,
        );
    }

    /**
     * Dominio que puede servir la página en este host (DNS verificado).
     */
    public function enrutable(string $host): ?DominioPagina
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        $dominio = DominioPagina::query()->with('pagina')->where('host_verificado', $host)->first();

        return $dominio !== null && $dominio->estado->enrutable() ? $dominio : null;
    }

    /**
     * Registros que el negocio debe crear en su proveedor de DNS.
     *
     * @return array<string, mixed>
     */
    public function instrucciones(DominioPagina $dominio): array
    {
        $partes = explode('.', $dominio->host);
        $esRaiz = count($partes) === 2 || (count($partes) === 3 && in_array($partes[1], ['com', 'org', 'net', 'gob', 'edu'], true) && strlen($partes[2]) === 2);
        $txtNombre = (string) config('dasti.paginas.dominios.registro_txt', '_dasti-verificacion');

        $registros = [[
            'tipo' => 'TXT',
            'nombre' => $dominio->nombreRegistroTxt(),
            'nombre_corto' => $esRaiz ? $txtNombre : $txtNombre.'.'.$partes[0],
            'valor' => $dominio->token,
            'proposito' => 'Demuestra que el dominio es tuyo.',
        ]];

        if ($esRaiz) {
            foreach ((array) config('dasti.paginas.dominios.ips_ingreso', []) as $ip) {
                $registros[] = [
                    'tipo' => str_contains((string) $ip, ':') ? 'AAAA' : 'A',
                    'nombre' => $dominio->host,
                    'nombre_corto' => '@',
                    'valor' => (string) $ip,
                    'proposito' => 'Hace que tu dominio muestre tu página de enlaces.',
                ];
            }
        } else {
            $registros[] = [
                'tipo' => 'CNAME',
                'nombre' => $dominio->host,
                'nombre_corto' => $partes[0],
                'valor' => (string) config('dasti.paginas.dominios.cname'),
                'proposito' => 'Hace que tu dominio muestre tu página de enlaces.',
            ];
        }

        return ['es_raiz' => $esRaiz, 'registros' => $registros, 'ips_configuradas' => (array) config('dasti.paginas.dominios.ips_ingreso', []) !== []];
    }

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    private function registrarFallo(DominioPagina $dominio, string $error, array $diagnostico, ?User $usuario, EstadoDominio $anterior): DominioPagina
    {
        $fallos = $dominio->fallos_consecutivos + 1;
        $umbral = (int) config('dasti.paginas.dominios.fallos_para_error', 3);
        $verificadoAntes = $dominio->estado->enrutable();

        $estado = match (true) {
            // Un dominio que funcionaba tolera fallos temporales del DNS.
            $verificadoAntes && $fallos < $umbral => $dominio->estado,
            $verificadoAntes => EstadoDominio::Error,
            $dominio->created_at !== null && $dominio->created_at->lt(now()->subDays(self::DIAS_PENDIENTE)) => EstadoDominio::Error,
            $dominio->estado === EstadoDominio::Error => EstadoDominio::Error,
            default => EstadoDominio::Pendiente,
        };

        $dominio->forceFill([
            'estado' => $estado,
            // Deja de enrutarse en cuanto pierde la verificación.
            'host_verificado' => $estado->enrutable() ? $dominio->host_verificado : null,
            'ultimo_error' => $error,
            'fallos_consecutivos' => $fallos,
            'diagnostico' => $diagnostico,
            'ultima_revision_en' => now(),
            'proxima_revision_en' => match (true) {
                $estado === EstadoDominio::Pendiente && $dominio->created_at?->gt(now()->subDay()) => now()->addMinutes(15),
                $estado === EstadoDominio::Pendiente => now()->addHours(6),
                default => now()->addDay(),
            },
        ])->save();

        if ($estado !== EstadoDominio::Activo) {
            $this->asegurarPrincipal($dominio->pagina_id);
        }

        $this->auditarCambio($dominio, $anterior, $usuario);

        return $dominio;
    }

    private function auditarCambio(DominioPagina $dominio, EstadoDominio $anterior, ?User $usuario): void
    {
        if ($anterior === $dominio->estado) {
            return;
        }

        $this->auditoria->registrar(
            accion: 'pagina.dominio_verificado',
            descripcion: "Dominio «{$dominio->host}»: {$dominio->estado->etiqueta()}",
            recurso: $dominio,
            negocio: $dominio->negocio_id,
            anteriores: ['estado' => $anterior->value],
            nuevos: ['estado' => $dominio->estado->value, 'error' => $dominio->ultimo_error],
            actor: $usuario,
        );
    }

    /**
     * Al activarse un dominio que reemplaza a otro, el anterior se
     * desvincula (cambio de dominio sin interrupción).
     */
    private function completarReemplazo(DominioPagina $dominio, ?User $usuario): void
    {
        if ($dominio->reemplaza_a_id === null) {
            return;
        }

        $viejo = DominioPagina::query()->whereKey($dominio->reemplaza_a_id)->where('pagina_id', $dominio->pagina_id)->first();

        if ($viejo !== null) {
            $eraPrincipal = $viejo->es_principal;
            $this->eliminar($viejo, $usuario);

            if ($eraPrincipal) {
                DominioPagina::query()->where('pagina_id', $dominio->pagina_id)->update(['es_principal' => false]);
                $dominio->forceFill(['es_principal' => true])->save();
            }
        }

        $dominio->forceFill(['reemplaza_a_id' => null])->save();
    }

    /**
     * Si el principal no está activo y hay otro activo, este pasa a ser el
     * principal.
     */
    private function asegurarPrincipal(int $paginaId): void
    {
        $dominios = DominioPagina::query()->where('pagina_id', $paginaId)->get();
        $principal = $dominios->firstWhere('es_principal', true);

        if ($principal !== null && $principal->estaActivo()) {
            return;
        }

        $activo = $dominios->first(fn (DominioPagina $d) => $d->estaActivo());

        if ($activo !== null) {
            DominioPagina::query()->where('pagina_id', $paginaId)->update(['es_principal' => false]);
            $activo->forceFill(['es_principal' => true])->save();
        } elseif ($principal === null && $dominios->isNotEmpty()) {
            $dominios->first()->forceFill(['es_principal' => true])->save();
        }
    }
}
