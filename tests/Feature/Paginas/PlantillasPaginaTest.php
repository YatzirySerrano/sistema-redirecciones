<?php

namespace Tests\Feature\Paginas;

use App\Models\Negocio;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Paginas\VersionPagina;
use App\Services\Paginas\CatalogoPagina;
use App\Services\Paginas\DocumentoPagina;
use App\Services\Paginas\PlantillasPagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

class PlantillasPaginaTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->negocio = $this->negocioConPaginas();
    }

    private function plantillas(): PlantillasPagina
    {
        return app(PlantillasPagina::class);
    }

    /**
     * Comprueba que cada valor de $esperado aparece igual en $real.
     *
     * @param  array<mixed>  $esperado
     * @param  array<mixed>  $real
     */
    private function contiene(array $esperado, array $real, string $ruta): void
    {
        foreach ($esperado as $clave => $valor) {
            $this->assertArrayHasKey($clave, $real, "Se perdió {$ruta}.{$clave}");

            if (is_array($valor)) {
                $this->assertIsArray($real[$clave], "{$ruta}.{$clave}");
                $this->contiene($valor, $real[$clave], "{$ruta}.{$clave}");
            } else {
                $this->assertSame($valor, $real[$clave], "Cambió {$ruta}.{$clave}");
            }
        }
    }

    public function test_catalogo_amplio_con_metadatos_validos_y_giros_cubiertos(): void
    {
        $lista = $this->plantillas()->todas($this->negocio);

        $this->assertGreaterThanOrEqual(20, count($lista));
        $this->assertLessThanOrEqual(26, count($lista));
        $this->assertSame(count($lista), count(array_unique(array_column($lista, 'clave'))));
        $this->assertSame(count($lista), count(array_unique(array_column($lista, 'nombre'))));

        $cubiertos = [];

        foreach ($lista as $p) {
            $this->assertNotSame('', $p['descripcion']);
            $this->assertNotEmpty($p['rasgos'], $p['clave']);
            $this->assertArrayHasKey($p['estilo'], PlantillasPagina::ESTILOS, $p['clave']);
            $this->assertNotEmpty($p['giros']);

            foreach ($p['giros'] as $giro) {
                $this->assertArrayHasKey($giro, PlantillasPagina::GIROS, $p['clave']);
                $cubiertos[$giro] = true;
            }

            $this->assertSame($this->negocio->nombre_comercial, $p['documento']['perfil']['nombre']);
        }

        $this->assertSame([], array_values(array_diff(array_keys(PlantillasPagina::GIROS), array_keys($cubiertos))), 'Todos los giros tienen al menos una plantilla.');
        $this->assertGreaterThanOrEqual(8, count(array_unique(array_column($lista, 'estilo'))));
    }

    public function test_cada_plantilla_pasa_el_saneamiento_estricto_sin_perder_campos(): void
    {
        $documento = app(DocumentoPagina::class);

        foreach ($this->plantillas()->entradas($this->negocio) as $clave => $entrada) {
            // Modo borrador: lanza ValidationException ante cualquier valor inválido.
            $saneado = $documento->sanear($entrada, $this->negocio, DocumentoPagina::MODO_BORRADOR);

            $this->contiene($entrada['tema'], $saneado['tema'], "{$clave}.tema");
            $this->assertSame($entrada['perfil']['descripcion'], $saneado['perfil']['descripcion']);

            $bloques = $entrada['paginas'][0]['bloques'];
            $this->assertCount(count($bloques), $saneado['paginas'][0]['bloques'], $clave);

            foreach ($bloques as $i => $bloque) {
                $real = $saneado['paginas'][0]['bloques'][$i];

                foreach ($bloque as $campo => $valor) {
                    if ($campo === 'bloques') {
                        $this->assertCount(count($valor), $real['bloques'], "{$clave}.{$i}");

                        foreach ($valor as $k => $hijo) {
                            $this->contiene($hijo, $real['bloques'][$k], "{$clave}.{$i}.bloques.{$k}");
                        }
                    } elseif ($campo === 'elementos') {
                        $this->assertSame(array_column($valor, 'red'), array_column($real['elementos'], 'red'), "{$clave}.{$i}");
                    } else {
                        $this->contiene([$campo => $valor], $real, "{$clave}.{$i}");
                    }
                }
            }

            // Lo que ya está saneado no cambia al volver a sanearse (idempotente).
            $otra = $documento->sanear($saneado, $this->negocio, DocumentoPagina::MODO_BORRADOR);
            $this->assertSame($saneado['tema'], $otra['tema'], $clave);
        }
    }

    public function test_plantillas_distintas_en_estructura_y_diseno(): void
    {
        $lista = $this->plantillas()->todas($this->negocio);
        $firmas = [];

        foreach ($lista as $p) {
            $tema = $p['documento']['tema'];
            $tipos = array_map(
                fn (array $b) => $b['tipo'].(isset($b['bloques']) ? '['.implode(',', array_column($b['bloques'], 'tipo')).']' : ''),
                $p['documento']['paginas'][0]['bloques'],
            );
            $firma = implode('|', $tipos).'#'.$tema['disposicion']['encabezado'].'#'.$tema['botones']['estilo'];
            $this->assertArrayNotHasKey($firma, $firmas, "{$p['clave']} repite la estructura de ".($firmas[$firma] ?? ''));
            $firmas[$firma] = $p['clave'];
        }

        // Además de los colores, cada pareja difiere en al menos 4 rasgos de diseño.
        $rasgos = fn (array $t) => [
            'encabezado' => $t['disposicion']['encabezado'],
            'boton' => $t['botones']['estilo'],
            'radio' => $t['botones']['radio'] >= 30 ? 'pildora' : ($t['botones']['radio'] <= 4 ? 'recto' : 'redondeado'),
            'sombra' => $t['botones']['sombra'],
            'icono_fondo' => $t['botones']['icono_fondo'],
            'mayusculas' => $t['botones']['mayusculas'],
            'fuente_titulos' => $t['tipografia']['fuente_titulos'],
            'fuente_texto' => $t['tipografia']['fuente_texto'],
            'alineacion' => $t['tipografia']['alineacion'],
            'fondo' => $t['fondo']['tipo'].($t['fondo']['tipo'] === 'textura' ? ':'.$t['fondo']['textura'] : ''),
            'titulos' => $t['disposicion']['titulos'],
            'tarjetas' => $t['disposicion']['tarjetas'],
            'lienzo' => $t['disposicion']['lienzo'],
            'imagen' => $t['disposicion']['forma_imagen'],
        ];

        foreach ($lista as $i => $a) {
            foreach (array_slice($lista, $i + 1) as $b) {
                $diferencias = count(array_diff_assoc($rasgos($a['documento']['tema']), $rasgos($b['documento']['tema'])));
                $this->assertGreaterThanOrEqual(4, $diferencias, "{$a['clave']} y {$b['clave']} se parecen demasiado");
            }
        }

        // Se usan los recursos de estilo disponibles.
        $temas = array_column(array_column($lista, 'documento'), 'tema');
        // «portada» requiere una imagen propia; las demás composiciones se usan todas.
        $this->assertGreaterThanOrEqual(6, count(array_unique(array_column(array_column($temas, 'disposicion'), 'encabezado'))));
        $this->assertGreaterThanOrEqual(6, count(array_unique(array_column(array_column($temas, 'botones'), 'estilo'))));
        $this->assertGreaterThanOrEqual(12, count(array_unique(array_column(array_column($temas, 'tipografia'), 'fuente_titulos'))));
    }

    /**
     * Mismo criterio que avisosContraste() en resources/js/components/paginas/tema.ts:
     * al abrir una plantilla el panel de diseño no debe mostrar avisos.
     */
    public function test_plantillas_sin_avisos_de_contraste(): void
    {
        $rgb = fn (string $h) => [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
        $mezclar = function (string $a, string $b, float $peso) use ($rgb): string {
            [$r1, $g1, $b1] = $rgb($a);
            [$r2, $g2, $b2] = $rgb($b);

            return sprintf('#%02x%02x%02x', (int) round($r1 * $peso + $r2 * (1 - $peso)), (int) round($g1 * $peso + $g2 * (1 - $peso)), (int) round($b1 * $peso + $b2 * (1 - $peso)));
        };
        $lum = function (string $h) use ($rgb): float {
            $c = array_map(fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb($h));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $ratio = fn (string $a, string $b) => (max($lum($a), $lum($b)) + 0.05) / (min($lum($a), $lum($b)) + 0.05);
        $fallas = [];

        foreach ($this->plantillas()->todas($this->negocio) as $p) {
            $t = $p['documento']['tema'];
            $c = $t['colores'];
            $fondo = $c['fondo'];

            if ($t['fondo']['tipo'] === 'degradado') {
                foreach ($t['fondo']['paradas'] as $i => $parada) {
                    $fondo = $i === 0 ? $parada['color'] : $mezclar($fondo, $parada['color'], $i / ($i + 1));
                }
            }

            $boton = match ($t['botones']['estilo']) {
                'contorno', 'subrayado' => $fondo,
                'suave' => $mezclar($c['boton_fondo'], $fondo, 0.22),
                'vidrio' => $mezclar($c['boton_fondo'], $fondo, 0.16),
                'degradado' => $mezclar($c['boton_fondo'], $c['acento'], 0.5),
                default => $c['boton_fondo'],
            };
            $hover = $t['botones']['estilo'] === 'solido' ? $c['boton_hover_fondo'] : $mezclar($c['boton_hover_fondo'], $fondo, 0.5);

            foreach ([
                ['texto', $c['texto'], $fondo, 4.5],
                ['títulos', $c['titulos'], $fondo, 3],
                ['texto secundario', $c['texto_suave'], $fondo, 4.5],
                ['texto de botones', $c['boton_texto'], $boton, 4.5],
                ['botones al pasar el cursor', $c['boton_hover_texto'], $hover, 4.5],
                ['tarjetas', $c['tarjeta_texto'], $c['tarjeta_fondo'], 4.5],
            ] as [$que, $a, $b, $minimo]) {
                if (round($ratio($a, $b), 1) < $minimo) {
                    $fallas[] = "{$p['clave']}: {$que} ".round($ratio($a, $b), 1).':1';
                }
            }
        }

        $this->assertSame([], $fallas);
    }

    public function test_contenido_demostrativo_sin_datos_reales(): void
    {
        foreach ($this->plantillas()->entradas($this->negocio) as $clave => $entrada) {
            $json = (string) json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->assertDoesNotMatchRegularExpression('#https?://|www\.#i', $json, $clave);
            $this->assertDoesNotMatchRegularExpression('/[\w.+-]+@[\w-]+\.[a-z]{2,}/i', $json, $clave);
            $this->assertDoesNotMatchRegularExpression('/\d[\d\s-]{6,}\d/', $json, "{$clave}: parece un teléfono");

            array_walk_recursive($entrada['paginas'], function (mixed $valor, string|int $campo) use ($clave) {
                if (in_array($campo, ['url', 'telefono', 'email', 'direccion', 'valor'], true)) {
                    $this->assertSame('', $valor, "{$clave}: {$campo} debe quedar vacío para que el negocio lo complete");
                }
            });
        }
    }

    public function test_documento_previo_sin_opciones_nuevas_conserva_su_diseno(): void
    {
        // Tema guardado antes de que existieran las opciones nuevas.
        $anterior = [
            'colores' => ['fondo' => '#101010', 'texto' => '#fafafa', 'acento' => '#ff0000'],
            'fondo' => ['tipo' => 'textura', 'textura' => 'rombos', 'color_textura' => '#222222'],
            'tipografia' => ['fuente_titulos' => 'lora', 'alineacion' => 'izquierda'],
            'botones' => ['estilo' => 'vidrio', 'radio' => 20, 'sombra' => 'marcada', 'icono' => 'derecha'],
            'disposicion' => ['encabezado' => 'portada', 'forma_foto' => 'cuadrada', 'ancho' => 'amplio'],
        ];
        $doc = app(DocumentoPagina::class)->sanear($this->documento([], ['tema' => $anterior]), $this->negocio);

        $this->contiene($anterior, $doc['tema'], 'tema');
        // Las opciones nuevas toman el valor que reproduce el diseño de siempre.
        $this->assertFalse($doc['tema']['botones']['mayusculas']);
        $this->assertSame('ninguno', $doc['tema']['botones']['icono_fondo']);
        $this->assertSame(
            ['monograma' => false, 'forma_imagen' => 'tema', 'tarjetas' => 'borde', 'lienzo' => 'libre', 'titulos' => 'simple'],
            array_intersect_key($doc['tema']['disposicion'], array_flip(['monograma', 'forma_imagen', 'tarjetas', 'lienzo', 'titulos'])),
        );

        // El tema por defecto conserva sus valores originales.
        $porDefecto = DocumentoPagina::temaPorDefecto();
        $this->assertSame(['estilo' => 'solido', 'radio' => 14, 'sombra' => 'suave', 'borde' => 1, 'tamano' => 'normal', 'animacion' => 'elevar', 'icono' => 'izquierda', 'alineacion' => 'centro'], array_slice($porDefecto['botones'], 0, 8));
        $this->assertSame(['ancho' => 'normal', 'espaciado' => 'normal', 'encabezado' => 'centrado', 'forma_foto' => 'circulo'], array_slice($porDefecto['disposicion'], 0, 4));
    }

    public function test_opciones_nuevas_solo_de_lista_blanca(): void
    {
        $doc = app(DocumentoPagina::class)->sanear($this->documento([
            ['tipo' => 'separador', 'estilo' => 'ornamento'],
            ['tipo' => 'separador', 'estilo' => '<script>'],
        ], ['tema' => [
            'botones' => ['estilo' => 'subrayado', 'icono_fondo' => 'url(javascript:1)', 'mayusculas' => 'si'],
            'disposicion' => ['encabezado' => 'banda', 'monograma' => true, 'forma_imagen' => 'estrella', 'tarjetas' => 'sombra', 'lienzo' => 'panel', 'titulos' => 'expression()'],
            'fondo' => ['tipo' => 'textura', 'textura' => 'aurora'],
        ]]), $this->negocio);

        $this->assertSame('subrayado', $doc['tema']['botones']['estilo']);
        $this->assertSame('ninguno', $doc['tema']['botones']['icono_fondo']);
        $this->assertFalse($doc['tema']['botones']['mayusculas']);
        $this->assertSame('banda', $doc['tema']['disposicion']['encabezado']);
        $this->assertTrue($doc['tema']['disposicion']['monograma']);
        $this->assertSame('tema', $doc['tema']['disposicion']['forma_imagen']);
        $this->assertSame('sombra', $doc['tema']['disposicion']['tarjetas']);
        $this->assertSame('panel', $doc['tema']['disposicion']['lienzo']);
        $this->assertSame('simple', $doc['tema']['disposicion']['titulos']);
        $this->assertSame('aurora', $doc['tema']['fondo']['textura']);
        $this->assertSame(['ornamento', 'linea'], array_column($doc['paginas'][0]['bloques'], 'estilo'));
        $this->assertContains('aurora', CatalogoPagina::TEXTURAS);
    }

    public function test_aplicar_plantilla_y_restaurar_la_version_anterior(): void
    {
        // Se guardan todas las plantillas seguidas: sin el límite de peticiones por minuto.
        $this->withoutMiddleware(ThrottleRequests::class);
        $usuario = $this->propietario($this->negocio);
        $this->actingAs($usuario)->get(route('cliente.paginas.editor'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/paginas/Editor')
                ->has('plantillas', count($this->plantillas()->todas($this->negocio)))
                ->has('plantillas.0.giros')
                ->has('plantillas.0.estilo')
                ->has('categorias_plantillas.giros.cafeteria')
                ->has('categorias_plantillas.estilos.minimalista')
                ->where('rutas.borrador', '/panel/paginas/borrador')
                ->where('rutas.restaurar', '/panel/paginas/versiones/__ID__/restaurar')
                ->where('rutas.medios_eliminar', '/panel/paginas/medios/__ID__')
                ->where('rutas.version', '/panel/paginas/versiones/__ID__'));

        $pagina = PaginaEnlaces::query()->where('negocio_id', $this->negocio->id)->firstOrFail();
        $propio = $this->documento([$this->enlace('Mi menú', 'https://ejemplo.mx/menu')]);
        $version = (int) $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $propio, 'version' => $pagina->version])->assertOk()->json('pagina.version');
        $original = PaginaEnlaces::query()->findOrFail($pagina->id)->borrador;

        // Cada plantilla aplicada (diseño + bloques de ejemplo) se guarda sin errores.
        foreach ($this->plantillas()->todas($this->negocio) as $plantilla) {
            $version = (int) $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $plantilla['documento'], 'version' => $version])
                ->assertOk()
                ->assertJsonPath('documento.tema.disposicion.encabezado', $plantilla['documento']['tema']['disposicion']['encabezado'])
                ->json('pagina.version');
        }

        // Restaurar la versión anterior a las plantillas recupera el diseño y el contenido propios.
        $copia = VersionPagina::query()->where('pagina_id', $pagina->id)->oldest('id')->get()
            ->first(fn (VersionPagina $v) => ($v->documento['paginas'][0]['bloques'][0]['titulo'] ?? null) === 'Mi menú');
        $this->assertNotNull($copia);
        $this->actingAs($usuario)->postJson(route('cliente.paginas.versiones.restaurar', $copia), ['version' => $version])->assertOk()
            ->assertJsonPath('documento.paginas.0.bloques.0.titulo', 'Mi menú');
        $this->assertSame($original['tema'], PaginaEnlaces::query()->findOrFail($pagina->id)->borrador['tema']);
    }
}
