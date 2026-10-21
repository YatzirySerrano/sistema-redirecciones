<?php

namespace Tests\Feature\Consola;

use App\Enums\RolUsuario;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComandosYSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_administrador_desde_consola(): void
    {
        $this->artisan('dasti:crear-administrador', ['--nombre' => 'Admin Real', '--email' => 'Admin@Dasti.cloud'])
            ->expectsQuestion('Contraseña', 'Clave-Segura-2026')
            ->expectsQuestion('Confirma la contraseña', 'Clave-Segura-2026')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@dasti.cloud')->sole();
        $this->assertSame(RolUsuario::Admin, $admin->rol);
        $this->assertTrue(Hash::check('Clave-Segura-2026', $admin->password));
    }

    public function test_crear_administrador_rechaza_contrasenas_que_no_coinciden(): void
    {
        $this->artisan('dasti:crear-administrador', ['--nombre' => 'X', '--email' => 'x@dasti.cloud'])
            ->expectsQuestion('Contraseña', 'Clave-Segura-2026')
            ->expectsQuestion('Confirma la contraseña', 'otra')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_el_seeder_de_demostracion_es_repetible(): void
    {
        $this->seed(DemoSeeder::class);
        $conteos = fn () => [User::query()->count(), Negocio::query()->count(), Producto::query()->count(), EventoAcceso::query()->count(), DB::table('cambios_destino')->count()];
        $primera = $conteos();

        $this->seed(DemoSeeder::class);

        $this->assertSame($primera, $conteos());
        $this->assertGreaterThanOrEqual(3, Negocio::query()->count());
        $this->assertTrue(Producto::query()->where('tipo', 'qr')->exists());
        $this->assertTrue(Producto::query()->where('tipo', 'nfc')->exists());
        $this->assertTrue(Producto::query()->where('tipo', 'combinado')->exists());
        $this->assertTrue(Producto::query()->where('estado', 'suspendido')->exists());
    }

    public function test_los_seeders_se_niegan_a_ejecutarse_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        (new DemoSeeder)->run();
    }

    public function test_depurar_eventos_aplica_la_politica_de_conservacion(): void
    {
        $producto = Producto::factory()->create();
        $base = ['producto_id' => $producto->id, 'negocio_id' => $producto->negocio_id, 'resultado' => 'redirigido', 'dispositivo' => 'movil', 'es_bot' => false, 'huella_diaria' => str_repeat('a', 64)];
        DB::table('eventos_acceso')->insert([
            [...$base, 'ocurrido_en' => now()->subDays(2)],
            [...$base, 'ocurrido_en' => now()->subDays(45)],
            [...$base, 'ocurrido_en' => now()->subDays(500)],
        ]);

        $this->artisan('dasti:depurar-eventos')->assertSuccessful();

        $this->assertSame(2, EventoAcceso::query()->count());
        $this->assertSame(1, EventoAcceso::query()->whereNotNull('huella_diaria')->count());
    }
}
