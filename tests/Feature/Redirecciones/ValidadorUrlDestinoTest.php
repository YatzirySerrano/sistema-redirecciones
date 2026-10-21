<?php

namespace Tests\Feature\Redirecciones;

use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidadorUrlDestinoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function urlsValidas(): array
    {
        return [
            'https simple' => ['https://www.instagram.com/negocio'],
            'whatsapp con texto' => ['https://wa.me/525512345678?text=Hola%20quiero%20info'],
            'reseñas de google' => ['https://g.page/r/abc123/review'],
            'con espacios al borde' => ['  https://example.com/menu  '],
            'dominio internacional' => ['https://cafetería.mx/menú'],
            'puerto 443 explícito' => ['https://example.com:443/'],
            'fragmento' => ['https://example.com/#contacto'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function urlsInvalidas(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.com/archivo'],
            'sin esquema' => ['www.example.com'],
            'http por defecto' => ['http://example.com'],
            'credenciales' => ['https://usuario:clave@example.com'],
            'localhost' => ['https://localhost/admin'],
            'subdominio de localhost' => ['https://app.localhost'],
            'loopback' => ['https://127.0.0.1/'],
            'ip privada' => ['https://192.168.1.10/'],
            'ip pública literal' => ['https://8.8.8.8/'],
            'ipv6 loopback' => ['https://[::1]/'],
            'ip decimal' => ['https://2130706433/'],
            'ip hexadecimal' => ['https://0x7f.0.0.1/'],
            'dominio .local' => ['https://impresora.local/'],
            'dominio interno' => ['https://intranet.empresa.internal/'],
            'sin punto' => ['https://intranet/'],
            'puerto raro' => ['https://example.com:8080/'],
            'redirección circular' => ['https://redireccion.test/r/abcdefghjk'],
            'subdominio propio' => ['https://panel.redireccion.test/'],
            'acortador bloqueado' => ['https://bit.ly/abc'],
            'espacios internos' => ['https://exa mple.com'],
            'barra invertida' => ['https://example.com\\@evil.com'],
            'tld numérico' => ['https://example.123'],
            'demasiado larga' => ['https://example.com/'.str_repeat('a', 2100)],
            'vacía' => [''],
        ];
    }

    #[DataProvider('urlsValidas')]
    public function test_acepta_destinos_legitimos(string $url): void
    {
        $this->assertNull(app(ValidadorUrlDestino::class)->error($url));
    }

    #[DataProvider('urlsInvalidas')]
    public function test_rechaza_destinos_peligrosos_o_invalidos(string $url): void
    {
        $this->assertNotNull(app(ValidadorUrlDestino::class)->error($url), "Se aceptó indebidamente: {$url}");
    }

    public function test_normaliza_dominios_internacionales_y_rutas_a_ascii(): void
    {
        $this->assertSame(
            'https://xn--cafetera-i2a.mx/men%C3%BA?q=caf%C3%A9',
            app(ValidadorUrlDestino::class)->normalizar(' https://Cafetería.mx/menú?q=café '),
        );
    }

    public function test_http_se_acepta_solo_si_la_configuracion_lo_permite(): void
    {
        $validador = app(ValidadorUrlDestino::class);
        $this->assertNotNull($validador->error('http://example.com'));

        app(AjustesPlataforma::class)->guardar(['permitir_destinos_http' => true]);

        $this->assertNull($validador->error('http://example.com'));
    }
}
