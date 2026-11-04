<?php

namespace Tests\Feature\Iconos;

use App\Models\Negocio;
use App\Services\Fidelidad\Diseno\DocumentoDiseno;
use App\Services\Fidelidad\RegistroVisitas;
use App\Services\Iconos\CatalogoIconos;
use App\Services\Paginas\CatalogoPagina;
use App\Services\Paginas\DocumentoPagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaFidelidad;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

/**
 * Biblioteca de íconos compartida (Página de enlaces y Fidelidad):
 * catálogo versionado, búsqueda, validación en ambos documentos e
 * incrustación de SOLO los trazos usados en las páginas públicas.
 */
class CatalogoIconosTest extends TestCase
{
    use PreparaFidelidad, PreparaPaginas, RefreshDatabase {
        PreparaPaginas::propietario insteadof PreparaFidelidad;
        PreparaFidelidad::propietario as propietarioFidelidad;
    }

    private CatalogoIconos $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        CatalogoIconos::olvidar();
        $this->catalogo = app(CatalogoIconos::class);
    }

    public function test_el_catalogo_carga_con_las_categorias_exigidas_y_la_licencia(): void
    {
        $this->assertGreaterThan(1800, $this->catalogo->total());
        $this->assertFileExists(base_path('resources/iconos/LICENSE-lucide.txt'));
        $this->assertStringContainsString('ISC', (string) file_get_contents(base_path('resources/iconos/LICENSE-lucide.txt')));

        $nombres = array_column($this->catalogo->categorias(), 'nombre');

        foreach ([
            'Agua y hielo', 'Alimentos', 'Bebidas', 'Cafeterías', 'Restaurantes', 'Panaderías', 'Belleza',
            'Spa y masajes', 'Barberías', 'Salud', 'Farmacias', 'Mascotas', 'Deportes', 'Gimnasios',
            'Tecnología', 'Electrónica', 'Automóviles', 'Mecánica', 'Hogar', 'Construcción', 'Educación',
            'Papelerías', 'Ropa y moda', 'Flores y naturaleza', 'Ferreterías', 'Servicios profesionales',
            'Viajes', 'Entretenimiento', 'Celebraciones', 'Comercio general', 'Redes sociales', 'General',
        ] as $nombre) {
            $this->assertContains($nombre, $nombres, "Falta la categoría «{$nombre}».");
        }

        foreach ($this->catalogo->categorias() as $categoria) {
            $this->assertGreaterThanOrEqual(15, count($categoria['iconos']), "La categoría «{$categoria['nombre']}» tiene pocos íconos.");

            foreach ($categoria['iconos'] as $clave) {
                $valor = str_starts_with($clave, 'red:') ? $clave : 'i:'.$clave;
                $this->assertTrue($this->catalogo->existe($valor), "«{$valor}» de «{$categoria['nombre']}» no existe.");
            }
        }

        // Las redes usan los glifos existentes (mismas claves que el módulo).
        $redes = collect($this->catalogo->categorias())->firstWhere('clave', 'redes');
        $this->assertNotNull($redes);
        foreach (array_keys(CatalogoPagina::REDES) as $red) {
            $this->assertContains('red:'.$red, $redes['iconos']);
        }
    }

    public function test_busqueda_de_agua_y_hielo_sin_acentos_ni_mayusculas(): void
    {
        $esperados = [
            'hielo' => 'i:cubos-hielo',
            'cubos de hielo' => 'i:cubos-hielo',
            'Copo de nieve' => 'i:snowflake',
            'agua' => 'i:garrafon-agua',
            'gota' => 'i:droplet',
            'botella' => 'i:botella-agua',
            'vaso' => 'i:glass-water',
            'refrigeración' => 'i:refrigerator',
            'REFRIGERACION' => 'i:refrigerator',
            'garrafón' => 'i:garrafon-agua',
            'garrafones' => 'i:garrafon-agua',
            'bolsa de hielo' => 'i:bolsa-hielo',
            'hielera' => 'i:hielera',
        ];

        foreach ($esperados as $consulta => $valor) {
            $this->assertContains($valor, $this->catalogo->buscar($consulta, 40), "Buscar «{$consulta}» debe encontrar {$valor}.");
        }

        $this->assertNotContains('i:umbrella', $this->catalogo->buscar('agua', 100), 'Se busca por inicio de palabra («paraguas» no es «agua»).');

        $agua = collect($this->catalogo->categorias())->firstWhere('clave', 'agua-hielo');
        $this->assertNotNull($agua);
        foreach (['cubos-hielo', 'snowflake', 'droplet', 'botella-agua', 'glass-water', 'refrigerator', 'garrafon-agua'] as $clave) {
            $this->assertContains($clave, $agua['iconos']);
        }

        $this->assertSame([], $this->catalogo->buscar('de la'));
        $this->assertContains('red:instagram', $this->catalogo->buscar('instagram'));
    }

    public function test_valores_heredados_siguen_siendo_validos_y_los_maliciosos_no(): void
    {
        foreach ([...CatalogoPagina::ICONOS, ...DocumentoDiseno::ICONOS] as $clave) {
            $this->assertTrue($this->catalogo->existe($clave), "La clave heredada «{$clave}» debe seguir siendo válida.");
        }

        foreach (array_keys(CatalogoPagina::REDES) as $red) {
            $this->assertTrue($this->catalogo->existe('red:'.$red));
        }

        foreach (['i:<script>', 'i:droplet"><script>', 'i:../catalogo', 'i:', 'i:DROPLET', 'i:no-existe-jamas', 'red:myspace', 'javascript:alert(1)', 'i:red:instagram', ''] as $malo) {
            $this->assertFalse($this->catalogo->existe($malo), "«{$malo}» no debe ser válido.");
        }

        $nodos = $this->catalogo->nodos(['i:droplet', 'i:<script>', 'cafe', 'red:instagram', 'i:cubos-hielo', 42]);
        $this->assertSame(['i:cubos-hielo', 'i:droplet'], array_keys($nodos), 'Solo los íconos de la biblioteca, y solo los pedidos.');
        $this->assertSame('path', $nodos['i:droplet'][0][0]);
    }

    public function test_documento_de_pagina_conserva_i_validos_y_descarta_desconocidos(): void
    {
        $negocio = Negocio::factory()->create();
        $sanitizador = app(DocumentoPagina::class);
        $doc = $sanitizador->sanear($this->documento([
            $this->enlace('Hielo', 'https://cafe-aurora.mx', ['icono' => 'i:cubos-hielo', 'icono_color' => '#0EA5E9', 'icono_tamano' => 28]),
            $this->enlace('Heredado', 'https://cafe-aurora.mx', ['icono' => 'cafe']),
            $this->enlace('Red', 'https://cafe-aurora.mx', ['icono' => 'red:whatsapp']),
            $this->enlace('Malo', 'https://cafe-aurora.mx', ['icono' => 'i:<script>', 'icono_color' => 'red;x', 'icono_tamano' => 'enorme']),
            $this->enlace('Desconocido', 'https://cafe-aurora.mx', ['icono' => 'i:no-existe']),
            $this->enlace('Fuera de rango', 'https://cafe-aurora.mx', ['icono' => 'i:droplet', 'icono_tamano' => 500]),
        ]), $negocio);

        $bloques = $doc['paginas'][0]['bloques'];
        $this->assertSame('i:cubos-hielo', $bloques[0]['icono']);
        $this->assertSame('#0ea5e9', $bloques[0]['icono_color']);
        $this->assertSame(28, $bloques[0]['icono_tamano']);
        $this->assertSame('cafe', $bloques[1]['icono']);
        $this->assertNull($bloques[1]['icono_color'], 'Por defecto el ícono usa los colores del tema.');
        $this->assertNull($bloques[1]['icono_tamano']);
        $this->assertSame('red:whatsapp', $bloques[2]['icono']);
        $this->assertNull($bloques[3]['icono']);
        $this->assertNull($bloques[3]['icono_color']);
        $this->assertNull($bloques[3]['icono_tamano']);
        $this->assertNull($bloques[4]['icono']);
        $this->assertSame(36, $bloques[5]['icono_tamano']);
    }

    public function test_documento_de_diseno_acepta_i_y_restablece_desconocidos(): void
    {
        $saneador = app(DocumentoDiseno::class);
        $base = DocumentoDiseno::predeterminado();

        $this->assertSame('i:cubos-hielo', $saneador->sanear(['progreso' => ['icono' => 'i:cubos-hielo']], null)['documento']['progreso']['icono']);
        $this->assertSame('cafe', $saneador->sanear(['progreso' => ['icono' => 'cafe']], null)['documento']['progreso']['icono']);

        foreach (['i:<script>', 'i:no-existe', '<svg onload=alert(1)>', 123] as $malo) {
            $resultado = $saneador->sanear(['progreso' => ['icono' => $malo]], null);
            $this->assertSame($base['progreso']['icono'], $resultado['documento']['progreso']['icono']);
            $this->assertNotEmpty($resultado['avisos']);
        }
    }

    public function test_la_pagina_publica_incrusta_solo_los_trazos_usados(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([
            $this->enlace('Pide tu hielo', 'https://cafe-aurora.mx', ['icono' => 'i:bolsa-hielo']),
            $this->enlace('Garrafón', 'https://cafe-aurora.mx', ['icono' => 'i:garrafon-agua']),
            $this->enlace('Heredado', 'https://cafe-aurora.mx', ['icono' => 'mapa']),
        ]));

        $html = (string) $this->get('/l/'.$pagina->slug)->assertOk()->getContent();
        $json = $this->incrustado($html, 'pagina-datos');
        $datos = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['i:bolsa-hielo', 'i:garrafon-agua'], array_keys($datos['iconos']));
        $this->assertNotEmpty($datos['iconos']['i:bolsa-hielo']);
        $this->assertStringNotContainsString('"i:droplet"', $html, 'No se incrusta el catálogo completo.');
        $this->assertLessThan(20_000, strlen($json));
    }

    public function test_la_tarjeta_publica_incrusta_solo_el_icono_del_sello(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 5]);
        $consumidor = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietarioFidelidad($negocio);
        app(RegistroVisitas::class)->registrarVisita($propietario, $consumidor, $campana, $this->uuid());

        $this->actingAs($propietario)->postJson(route('cliente.fidelidad.diseno.publicar'), [
            'campana_id' => null, 'version' => 0,
            'documento' => array_replace_recursive(DocumentoDiseno::predeterminado(), ['progreso' => ['icono' => 'i:cubos-hielo']]),
        ])->assertOk()->assertJsonPath('diseno.publicado.progreso.icono', 'i:cubos-hielo');

        $html = (string) $this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk()->getContent();
        $datos = json_decode($this->incrustado($html, 'datos-fidelidad'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('i:cubos-hielo', $datos['disenos']['general']['progreso']['icono']);
        $this->assertSame(['i:cubos-hielo'], array_keys($datos['iconos']));
        $this->assertStringNotContainsString('"i:droplet"', $html);
    }

    /** JSON incrustado por el servidor en <script type="application/json" id="…">. */
    private function incrustado(string $html, string $id): string
    {
        if (preg_match('#<script type="application/json" id="'.$id.'">(.*?)</script>#s', $html, $m) !== 1) {
            $this->fail("La página no incrusta #{$id}.");
        }

        return $m[1];
    }
}
