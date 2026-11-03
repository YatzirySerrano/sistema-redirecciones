<?php

namespace Tests\Feature\Paginas;

use App\Models\Negocio;
use App\Services\Paginas\DocumentoPagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

/**
 * Validación y saneamiento del documento (lista blanca estricta).
 */
class DocumentoPaginaTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    private DocumentoPagina $sanitizador;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->sanitizador = app(DocumentoPagina::class);
        $this->negocio = Negocio::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array<string, string>
     */
    private function errores(array $documento, string $modo = DocumentoPagina::MODO_BORRADOR): array
    {
        try {
            $this->sanitizador->sanear($documento, $this->negocio, $modo);
        } catch (ValidationException $e) {
            return array_map(fn ($m) => $m[0], $e->errors());
        }

        return [];
    }

    public function test_colores_invalidos_y_css_inyectado_se_reemplazan_por_valores_seguros(): void
    {
        $doc = $this->sanitizador->sanear($this->documento([], ['tema' => [
            'colores' => [
                'fondo' => 'red; background:url(javascript:alert(1))',
                'texto' => '#ABCDEF',
                'boton_fondo' => 'expression(alert(1))',
                'titulos' => '#12345',
                'extra' => '#000000',
            ],
            'fondo' => ['tipo' => 'css-libre', 'angulo' => 9999, 'opacidad' => -5, 'desenfoque' => 500, 'paradas' => [['color' => 'url(x)', 'posicion' => 400]]],
            'botones' => ['radio' => 999, 'estilo' => '<script>', 'borde' => -3],
            'tipografia' => ['fuente_texto' => 'Comic Sans; } body {', 'tamano' => 99],
        ]]), $this->negocio);

        $porDefecto = DocumentoPagina::temaPorDefecto();
        $this->assertSame($porDefecto['colores']['fondo'], $doc['tema']['colores']['fondo']);
        $this->assertSame('#abcdef', $doc['tema']['colores']['texto']);
        $this->assertSame($porDefecto['colores']['boton_fondo'], $doc['tema']['colores']['boton_fondo']);
        $this->assertSame($porDefecto['colores']['titulos'], $doc['tema']['colores']['titulos']);
        $this->assertArrayNotHasKey('extra', $doc['tema']['colores']);
        $this->assertSame('solido', $doc['tema']['fondo']['tipo']);
        $this->assertSame(360, $doc['tema']['fondo']['angulo']);
        $this->assertSame(0, $doc['tema']['fondo']['opacidad']);
        $this->assertSame(20, $doc['tema']['fondo']['desenfoque']);
        // Menos de dos paradas válidas: se usa el degradado por defecto.
        $this->assertCount(2, $doc['tema']['fondo']['paradas']);
        $this->assertSame(40, $doc['tema']['botones']['radio']);
        $this->assertSame('solido', $doc['tema']['botones']['estilo']);
        $this->assertSame(0, $doc['tema']['botones']['borde']);
        $this->assertSame('inter', $doc['tema']['tipografia']['fuente_texto']);
        $this->assertSame(20, $doc['tema']['tipografia']['tamano']);
        $this->assertStringNotContainsString('javascript', (string) json_encode($doc));
    }

    public function test_degradado_valido_se_conserva_con_maximo_tres_paradas(): void
    {
        $doc = $this->sanitizador->sanear($this->documento([], ['tema' => ['fondo' => [
            'tipo' => 'degradado', 'angulo' => 45,
            'paradas' => [
                ['color' => '#111111', 'posicion' => 0], ['color' => '#222222', 'posicion' => 50],
                ['color' => '#333333', 'posicion' => 100], ['color' => '#444444', 'posicion' => 100],
            ],
        ]]]), $this->negocio);

        $this->assertSame('degradado', $doc['tema']['fondo']['tipo']);
        $this->assertSame(45, $doc['tema']['fondo']['angulo']);
        $this->assertSame(['#111111', '#222222', '#333333'], array_column($doc['tema']['fondo']['paradas'], 'color'));
    }

    public function test_contenido_malicioso_se_elimina_o_se_rechaza(): void
    {
        $id = (string) Str::uuid();
        $doc = $this->sanitizador->sanear($this->documento([
            ['id' => $id, 'tipo' => 'texto', 'texto' => 'Hola <script>alert(1)</script><img src=x onerror=alert(2)> **mundo**', 'onclick' => 'alert(3)'],
            ['tipo' => 'titulo', 'texto' => '<iframe src="https://evil.example"></iframe>Bienvenidos'],
            ['tipo' => 'html', 'contenido' => '<b>no</b>'],
            ['tipo' => 'video', 'fuente' => 'externo', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'embed' => '<iframe src="https://evil.example">'],
        ]), $this->negocio);

        $bloques = $doc['paginas'][0]['bloques'];
        $this->assertCount(3, $bloques, 'Los tipos desconocidos se descartan.');
        $this->assertSame($id, $bloques[0]['id']);
        $this->assertSame('Hola alert(1) **mundo**', $bloques[0]['texto']);
        $this->assertArrayNotHasKey('onclick', $bloques[0]);
        $this->assertSame('Bienvenidos', $bloques[1]['texto']);
        $this->assertSame(['proveedor' => 'youtube', 'ref' => 'dQw4w9WgXcQ'], ['proveedor' => $bloques[2]['proveedor'], 'ref' => $bloques[2]['ref']]);
        $this->assertArrayNotHasKey('embed', $bloques[2]);
        $json = (string) json_encode($doc);
        $this->assertStringNotContainsString('<script', $json);
        $this->assertStringNotContainsString('<iframe', $json);
        $this->assertStringNotContainsString('evil.example', $json);
        $this->assertStringNotContainsString('onerror', $json);
    }

    public function test_urls_peligrosas_se_rechazan_con_mensaje_en_el_elemento(): void
    {
        $peligrosas = [
            'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox',
            'http://localhost/admin', 'https://127.0.0.1', 'https://2130706433', 'file:///etc/passwd',
            'https://usuario:clave@banco.mx', 'https://redireccion.test/admin', 'https://intranet.local',
        ];

        foreach ($peligrosas as $url) {
            $enlace = $this->enlace('Peligroso', $url);
            $errores = $this->errores($this->documento([$enlace]));
            $this->assertArrayHasKey("bloques.{$enlace['id']}.url", $errores, "Debió rechazarse: {$url}");
        }
    }

    public function test_enlace_externo_valido_se_normaliza_y_www_se_entiende_como_https(): void
    {
        $doc = $this->sanitizador->sanear($this->documento([
            $this->enlace('Sitio', 'www.cafe-aurora.mx/menú'),
            $this->enlace('Instagram', 'https://instagram.com/cafe'),
        ]), $this->negocio);

        $this->assertSame('https://www.cafe-aurora.mx/men%C3%BA', $doc['paginas'][0]['bloques'][0]['url']);
        $this->assertSame('https://instagram.com/cafe', $doc['paginas'][0]['bloques'][1]['url']);
    }

    public function test_whatsapp_telefono_y_correo_se_construyen_en_el_servidor(): void
    {
        $doc = $this->sanitizador->sanear($this->documento([
            ['tipo' => 'whatsapp', 'titulo' => 'WhatsApp', 'telefono' => '55 1234 5678', 'mensaje' => 'Hola & adiós', 'url' => 'https://evil.example'],
            ['tipo' => 'telefono', 'titulo' => 'Llamar', 'telefono' => '+1 (415) 555-0100'],
            ['tipo' => 'correo', 'titulo' => 'Correo', 'email' => 'Hola@Cafe-Aurora.mx', 'asunto' => 'Pedido'],
        ]), $this->negocio);

        [$wa, $tel, $correo] = $doc['paginas'][0]['bloques'];
        $this->assertSame('525512345678', $wa['telefono']);
        $this->assertArrayNotHasKey('url', $wa);
        $this->assertSame('https://wa.me/525512345678?text=Hola%20%26%20adi%C3%B3s', $this->sanitizador->destino($wa, '/l/x'));
        $this->assertSame('tel:+14155550100', $this->sanitizador->destino($tel, '/l/x'));
        $this->assertSame('mailto:hola@cafe-aurora.mx?subject=Pedido', $this->sanitizador->destino($correo, '/l/x'));

        $errores = $this->errores($this->documento([
            ['id' => $a = (string) Str::uuid(), 'tipo' => 'telefono', 'titulo' => 'X', 'telefono' => 'javascript:1'],
            ['id' => $b = (string) Str::uuid(), 'tipo' => 'correo', 'titulo' => 'Y', 'email' => 'no-es-correo'],
        ]));
        $this->assertArrayHasKey("bloques.{$a}.telefono", $errores);
        $this->assertArrayHasKey("bloques.{$b}.email", $errores);
    }

    public function test_insertados_solo_de_proveedores_permitidos(): void
    {
        $doc = $this->sanitizador->sanear($this->documento([
            ['tipo' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
            ['tipo' => 'video', 'url' => 'https://vimeo.com/76979871'],
            ['tipo' => 'musica', 'url' => 'https://open.spotify.com/intl-es/track/4uLU6hMCjMI75M1A2tKUQC?si=abc'],
            ['tipo' => 'musica', 'url' => 'https://soundcloud.com/artista/cancion-1'],
            ['tipo' => 'musica', 'url' => 'https://music.apple.com/mx/album/un-disco/1440857781?i=1440857786'],
        ]), $this->negocio);

        $refs = array_map(fn ($b) => [$b['proveedor'], $b['ref']], $doc['paginas'][0]['bloques']);
        $this->assertSame([
            ['youtube', 'dQw4w9WgXcQ'],
            ['vimeo', '76979871'],
            ['spotify', 'track/4uLU6hMCjMI75M1A2tKUQC'],
            ['soundcloud', 'artista/cancion-1'],
            ['apple', 'mx/album/un-disco/1440857781?i=1440857786'],
        ], $refs);

        $id = (string) Str::uuid();
        $errores = $this->errores($this->documento([['id' => $id, 'tipo' => 'video', 'url' => 'https://evil.example/embed/dQw4w9WgXcQ']]));
        $this->assertArrayHasKey("bloques.{$id}.url", $errores);
    }

    public function test_redes_deben_corresponder_al_dominio_de_la_red(): void
    {
        $id = (string) Str::uuid();
        $elemento = (string) Str::uuid();
        $errores = $this->errores($this->documento([['id' => $id, 'tipo' => 'redes', 'elementos' => [
            ['id' => $elemento, 'red' => 'instagram', 'valor' => 'https://instagram.com.phishing.mx/cafe'],
        ]]]));

        $this->assertArrayHasKey("bloques.{$id}.elementos.{$elemento}.valor", $errores);
    }

    public function test_publicar_exige_datos_completos_pero_el_borrador_no(): void
    {
        $enlace = $this->enlace('', '');
        $this->assertSame([], $this->errores($this->documento([$enlace])));

        $errores = $this->errores($this->documento([$enlace]), DocumentoPagina::MODO_PUBLICACION);
        $this->assertArrayHasKey("bloques.{$enlace['id']}.titulo", $errores);
        $this->assertArrayHasKey("bloques.{$enlace['id']}.url", $errores);
    }

    public function test_limites_de_cantidad_tamano_y_anidacion(): void
    {
        config(['dasti.paginas.limites.subpaginas' => 2, 'dasti.paginas.limites.bloques_por_pagina' => 3]);

        $paginas = array_map(fn ($i) => ['titulo' => "P{$i}", 'bloques' => []], range(1, 3));
        $this->assertArrayHasKey('documento', $this->errores(['paginas' => $paginas]));

        $errores = $this->errores($this->documento(array_map(fn ($i) => $this->enlace("E{$i}"), range(1, 4))));
        $this->assertNotEmpty(array_filter(array_keys($errores), fn ($k) => str_ends_with($k, '.bloques')));

        // Un grupo dentro de un grupo se descarta (un solo nivel).
        $doc = $this->sanitizador->sanear($this->documento([['tipo' => 'grupo', 'bloques' => [
            ['tipo' => 'grupo', 'bloques' => [$this->enlace()]],
            $this->enlace(),
        ]]]), $this->negocio, DocumentoPagina::MODO_TOLERANTE);
        $this->assertCount(1, $doc['paginas'][0]['bloques'][0]['bloques']);
        $this->assertSame('enlace', $doc['paginas'][0]['bloques'][0]['bloques'][0]['tipo']);
    }

    public function test_identificadores_estables_unicos_y_textos_acotados(): void
    {
        $id = (string) Str::uuid();
        $doc = $this->sanitizador->sanear($this->documento([
            $this->enlace('A', 'https://a.mx', ['id' => $id]),
            $this->enlace(str_repeat('x', 500), 'https://b.mx', ['id' => $id]),
            $this->enlace('C', 'https://c.mx', ['id' => '42']),
        ]), $this->negocio);

        $ids = array_column($doc['paginas'][0]['bloques'], 'id');
        $this->assertSame($id, $ids[0]);
        $this->assertCount(3, array_unique($ids));
        $this->assertTrue(Str::isUuid($ids[2]));
        $this->assertSame(100, mb_strlen($doc['paginas'][0]['bloques'][1]['titulo']));
    }

    public function test_programacion_de_visibilidad_se_valida(): void
    {
        $enlace = $this->enlace('Promo', 'https://a.mx', ['visible_desde' => '2026-12-01T10:00', 'visible_hasta' => '2026-11-01T10:00']);
        $this->assertArrayHasKey("bloques.{$enlace['id']}.visible_hasta", $this->errores($this->documento([$enlace])));

        $mala = $this->enlace('Promo', 'https://a.mx', ['visible_desde' => '2026-13-45T99:99']);
        $this->assertArrayHasKey("bloques.{$mala['id']}.visible_desde", $this->errores($this->documento([$mala])));
    }

    public function test_subpaginas_con_slug_unico_y_enlaces_internos_validos(): void
    {
        $inicio = (string) Str::uuid();
        $menu = (string) Str::uuid();
        $doc = $this->sanitizador->sanear(['paginas' => [
            ['id' => $inicio, 'titulo' => 'Inicio', 'bloques' => [['tipo' => 'subpagina', 'titulo' => 'Menú', 'pagina_id' => $menu]]],
            ['id' => $menu, 'titulo' => 'Menú del día', 'slug' => 'menu', 'bloques' => []],
            ['titulo' => 'Otro', 'slug' => 'menu', 'bloques' => []],
            ['titulo' => 'Reservado', 'slug' => 'contacto', 'bloques' => []],
        ]], $this->negocio);

        $this->assertSame(['inicio', 'menu', 'menu-2', 'pagina'], array_column($doc['paginas'], 'slug'));
        $this->assertSame('/l/cafe/menu', $this->sanitizador->destino($doc['paginas'][0]['bloques'][0], '/l/cafe', [$menu => 'menu']));

        $id = (string) Str::uuid();
        $errores = $this->errores(['paginas' => [['titulo' => 'Inicio', 'bloques' => [['id' => $id, 'tipo' => 'subpagina', 'titulo' => 'X', 'pagina_id' => (string) Str::uuid()]]]]]);
        $this->assertArrayHasKey("bloques.{$id}.pagina_id", $errores);
    }
}
