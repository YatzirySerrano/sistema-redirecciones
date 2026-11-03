<?php

namespace Tests\Concerns;

use App\Enums\EstadoPago;
use App\Enums\OrigenContratacion;
use App\Models\ArchivoPublico;
use App\Models\Negocio;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Medios\AlmacenMedios;
use App\Services\Paginas\Dominios\ComprobadorHttps;
use App\Services\Paginas\Dominios\ResolvedorDns;
use App\Services\Paginas\GestorPaginas;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait PreparaPaginas
{
    protected ?ResolvedorDnsFalso $dns = null;

    protected ?ComprobadorHttpsFalso $https = null;

    protected function negocioConPaginas(?Negocio $negocio = null, OrigenContratacion $origen = OrigenContratacion::Manual): Negocio
    {
        $negocio ??= Negocio::factory()->create();
        $servicio = Servicio::paginaEnlaces();
        $pago = null;

        if ($origen === OrigenContratacion::Openpay) {
            $pago = Pago::query()->create([
                'negocio_id' => $negocio->id,
                'servicio_id' => $servicio->id,
                'proveedor' => Pago::PROVEEDOR_OPENPAY,
                'estado' => EstadoPago::Pagado,
                'importe_centavos' => 149900,
                'moneda' => 'MXN',
                'pagado_en' => now(),
            ]);
        }

        app(GestorContrataciones::class)->activar($negocio, $servicio, $origen, $pago, motivo: 'Prueba');

        return $negocio;
    }

    protected function propietario(Negocio $negocio): User
    {
        return User::factory()->cliente($negocio)->create();
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function documento(array $bloques, array $extra = []): array
    {
        return array_replace_recursive([
            'perfil' => ['nombre' => 'Café Aurora', 'descripcion' => 'Café de especialidad'],
            'paginas' => [['id' => (string) Str::uuid(), 'titulo' => 'Inicio', 'bloques' => $bloques]],
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function enlace(string $titulo = 'Mi sitio', string $url = 'https://cafe-aurora.mx', array $extra = []): array
    {
        return ['id' => (string) Str::uuid(), 'tipo' => 'enlace', 'titulo' => $titulo, 'url' => $url, ...$extra];
    }

    /**
     * Página publicada con el documento indicado.
     *
     * @param  array<string, mixed>  $documento
     */
    protected function publicar(Negocio $negocio, array $documento, ?User $usuario = null): PaginaEnlaces
    {
        $usuario ??= $this->propietario($negocio);
        $gestor = app(GestorPaginas::class);
        $pagina = $gestor->paraNegocio($negocio);
        $pagina = $gestor->guardarBorrador($pagina, $documento, $pagina->version, $usuario);

        return $gestor->publicar($pagina, $pagina->version, $usuario)->fresh() ?? $pagina;
    }

    protected function imagen(Negocio $negocio, ?User $usuario = null): ArchivoPublico
    {
        return app(AlmacenMedios::class)->guardarImagen(
            UploadedFile::fake()->image('foto.png', 40, 40),
            $negocio,
            ArchivoPublico::MODULO_PAGINAS,
            $usuario,
        );
    }

    protected function falsearDns(): ResolvedorDnsFalso
    {
        $this->dns = new ResolvedorDnsFalso;
        $this->https = new ComprobadorHttpsFalso;
        $this->app->instance(ResolvedorDns::class, $this->dns);
        $this->app->instance(ComprobadorHttps::class, $this->https);

        return $this->dns;
    }
}
