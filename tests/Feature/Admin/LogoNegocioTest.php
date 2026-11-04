<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\LogoNegocioRequest;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Carga del logotipo del negocio (disco privado, recodificado a PNG).
 */
class LogoNegocioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->administrador()->create();
        $this->negocio = Negocio::factory()->create();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formatosValidos(): array
    {
        return [
            'PNG' => ['logo.png'],
            'JPG' => ['logo.jpg'],
            'WEBP' => ['logo.webp'],
        ];
    }

    #[DataProvider('formatosValidos')]
    public function test_acepta_imagenes_validas_y_las_guarda_como_png(string $nombre): void
    {
        $this->subir(UploadedFile::fake()->image($nombre, 320, 200))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ruta = $this->negocio->fresh()->logo_path;
        $this->assertNotNull($ruta);
        $this->assertStringStartsWith('logos/negocio-'.$this->negocio->id.'-', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame("\x89PNG", substr((string) Storage::disk('local')->get($ruta), 0, 4));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'negocio.logo_actualizado', 'negocio_id' => $this->negocio->id]);

        // Vista previa después de guardar.
        $this->actingAs($this->admin)->get(route('admin.negocios.logo.show', $this->negocio))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_rechaza_imagenes_que_superan_el_limite_de_logotipos(): void
    {
        // El límite es el de LimitesArchivos (5 MB, o menos si PHP acepta
        // menos en este servidor) y el mensaje lo dice en español.
        $config = 300;
        config(['dasti.medios.logo_max_kb' => $config]);

        $this->subir(UploadedFile::fake()->image('grande.png', 500, 500)->size($config + 50))
            ->assertSessionHasErrors(['logo' => 'El logotipo no debe pesar más de 300 KB.']);

        $this->assertNull($this->negocio->fresh()->logo_path);
    }

    public function test_un_fallo_de_php_al_recibir_el_archivo_se_explica_en_espanol(): void
    {
        // Lo que ocurre cuando el archivo supera upload_max_filesize de PHP.
        $ruta = tempnam(sys_get_temp_dir(), 'logo');
        $archivo = new UploadedFile((string) $ruta, 'foto.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        $this->subir($archivo)->assertSessionHasErrors(['logo' => LogoNegocioRequest::mensajeGeneral()]);
        $this->assertNull($this->negocio->fresh()->logo_path);
    }

    public function test_rechaza_archivos_con_extension_falsa(): void
    {
        $falso = UploadedFile::fake()->createWithContent('logo.png', "<?php echo 'no soy una imagen';");

        $respuesta = $this->subir($falso)->assertSessionHasErrors('logo');

        $this->assertNull($this->negocio->fresh()->logo_path);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]+ (must|failed|field)/', (string) session('errors')->first('logo'));
        $respuesta->assertRedirect();
    }

    public function test_rechaza_svg(): void
    {
        $this->subir(UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'))
            ->assertSessionHasErrors('logo');
    }

    public function test_una_imagen_danada_muestra_un_mensaje_claro_y_conserva_el_logo_anterior(): void
    {
        $this->subir(UploadedFile::fake()->image('original.png', 200, 200))->assertSessionHasNoErrors();
        $anterior = $this->negocio->fresh()->logo_path;

        // Cabecera PNG válida (tipo y dimensiones legibles) con datos truncados.
        $lienzo = imagecreatetruecolor(300, 300);
        ob_start();
        imagepng($lienzo);
        $png = (string) ob_get_clean();
        $danado = UploadedFile::fake()->createWithContent('danado.png', substr($png, 0, 60));

        $this->subir($danado)->assertSessionHasErrors('logo');
        $this->assertStringContainsString('dañado', (string) session('errors')->first('logo'));

        $this->assertSame($anterior, $this->negocio->fresh()->logo_path);
        Storage::disk('local')->assertExists((string) $anterior);
    }

    public function test_un_error_de_almacenamiento_conserva_el_logotipo_anterior(): void
    {
        $this->negocio->forceFill(['logo_path' => 'logos/anterior.png'])->save();

        $disco = Mockery::mock(FilesystemAdapter::class);
        $disco->shouldReceive('put')->once()->andReturnFalse();
        $disco->shouldNotReceive('delete');
        Storage::set('local', $disco);

        $this->subir(UploadedFile::fake()->image('logo.png', 200, 200))
            ->assertSessionHasErrors(['logo' => 'No pudimos guardar la imagen en el servidor. Se conservó el logotipo anterior; inténtalo nuevamente.']);

        $this->assertSame('logos/anterior.png', $this->negocio->fresh()->logo_path);
        $this->assertDatabaseMissing('registros_auditoria', ['accion' => 'negocio.logo_actualizado']);
    }

    public function test_reemplazar_el_logotipo_borra_el_anterior_solo_tras_guardar_el_nuevo(): void
    {
        $this->subir(UploadedFile::fake()->image('uno.png', 200, 200))->assertSessionHasNoErrors();
        $primero = (string) $this->negocio->fresh()->logo_path;

        $this->subir(UploadedFile::fake()->image('dos.jpg', 250, 250))->assertSessionHasNoErrors();
        $segundo = (string) $this->negocio->fresh()->logo_path;

        $this->assertNotSame($primero, $segundo);
        Storage::disk('local')->assertMissing($primero);
        Storage::disk('local')->assertExists($segundo);
    }

    public function test_el_logotipo_subido_se_usa_en_el_qr(): void
    {
        $this->subir(UploadedFile::fake()->image('logo.png', 300, 300))->assertSessionHasNoErrors();
        $producto = Producto::factory()->for($this->negocio)->create();

        $respuesta = $this->actingAs($this->admin)->get(route('productos.qr.show', [
            $producto, 'formato' => 'svg', 'tamano' => 512, 'incluir_logo' => 1,
        ]));

        $respuesta->assertOk();
        $this->assertStringContainsString('<image', (string) $respuesta->getContent());
    }

    public function test_un_cliente_no_puede_subir_logotipos(): void
    {
        $cliente = User::factory()->cliente($this->negocio)->create();

        $this->actingAs($cliente)->post(route('admin.negocios.logo.store', $this->negocio), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertForbidden();

        $this->assertNull($this->negocio->fresh()->logo_path);
    }

    private function subir(UploadedFile $archivo): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.negocios.show', $this->negocio))
            ->post(route('admin.negocios.logo.store', $this->negocio), ['logo' => $archivo]);
    }
}
