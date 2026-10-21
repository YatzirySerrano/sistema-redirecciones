<?php

namespace Database\Seeders;

use App\Actions\Negocios\CambiarEstadoNegocio;
use App\Actions\Negocios\CrearNegocio;
use App\Actions\Productos\CambiarDestinoProducto;
use App\Actions\Productos\CambiarEstadoProducto;
use App\Actions\Productos\CrearProducto;
use App\Enums\CategoriaDispositivo;
use App\Enums\EstadoCuenta;
use App\Enums\EstadoProducto;
use App\Enums\ResultadoAcceso;
use App\Enums\RolUsuario;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos ficticios para desarrollo local.
 *
 * - Idempotente: busca por correo / nombre antes de crear y solo genera
 *   eventos para productos que aún no tienen ninguno. No borra nada.
 * - Usa las mismas acciones que la aplicación, por lo que también genera
 *   historial de destinos y auditoría coherentes.
 * - Se niega a ejecutarse en producción.
 */
class DemoSeeder extends Seeder
{
    private const ADMIN = ['name' => 'Administración DASTI', 'email' => 'admin@dasti.test'];

    /**
     * @var list<array{negocio: array{nombre_comercial: string, responsable: string, email_contacto: string, telefono: string, notas_internas?: string}, usuario: array{name: string, email: string}, suspendido?: bool, productos: list<array<string, mixed>>}>
     */
    private const NEGOCIOS = [
        [
            'negocio' => ['nombre_comercial' => 'Café La Esquina', 'responsable' => 'Mariana López', 'email_contacto' => 'contacto@cafelaesquina.test', 'telefono' => '55 1234 5678', 'notas_internas' => 'Pedido inicial: 3 tarjetas NFC y 2 QR de mesa.'],
            'usuario' => ['name' => 'Mariana López', 'email' => 'cafe@cliente.test'],
            'productos' => [
                ['nombre' => 'Tarjeta NFC mostrador', 'tipo' => 'nfc', 'destinos' => ['https://www.instagram.com/cafelaesquina.demo', 'https://g.page/r/cafe-la-esquina-demo/review'], 'peso' => 9],
                ['nombre' => 'Tarjeta NFC gerente', 'tipo' => 'nfc', 'destinos' => ['https://wa.me/525512345678'], 'peso' => 3],
                ['nombre' => 'Tarjeta NFC Instagram', 'tipo' => 'nfc', 'destinos' => ['https://www.instagram.com/cafelaesquina.demo'], 'peso' => 5],
                ['nombre' => 'QR menú de mesa', 'tipo' => 'qr', 'destinos' => ['https://example.com/cafe-la-esquina/menu-verano', 'https://example.com/cafe-la-esquina/menu-otono'], 'peso' => 14],
                ['nombre' => 'QR reseñas de Google', 'tipo' => 'combinado', 'destinos' => ['https://g.page/r/cafe-la-esquina-demo/review'], 'peso' => 6],
            ],
        ],
        [
            'negocio' => ['nombre_comercial' => 'Estética Brisa', 'responsable' => 'Daniela Herrera', 'email_contacto' => 'hola@esteticabrisa.test', 'telefono' => '33 2345 6789'],
            'usuario' => ['name' => 'Daniela Herrera', 'email' => 'estetica@cliente.test'],
            'productos' => [
                ['nombre' => 'QR de WhatsApp', 'tipo' => 'qr', 'destinos' => ['https://wa.me/523323456789?text=Hola%2C%20quiero%20una%20cita'], 'peso' => 8],
                ['nombre' => 'Tarjeta NFC recepción', 'tipo' => 'nfc', 'destinos' => ['https://example.com/estetica-brisa/agenda'], 'peso' => 4],
                ['nombre' => 'QR catálogo de servicios', 'tipo' => 'qr', 'destinos' => ['https://example.com/estetica-brisa/catalogo'], 'peso' => 0, 'suspendido' => true],
                ['nombre' => 'Tarjeta NFC + QR promociones', 'tipo' => 'combinado', 'destinos' => [], 'peso' => 0],
            ],
        ],
        [
            'negocio' => ['nombre_comercial' => 'Taller Mecánico Ruiz', 'responsable' => 'Jorge Ruiz', 'email_contacto' => 'taller@ruiz.test', 'telefono' => '81 3456 7890'],
            'usuario' => ['name' => 'Jorge Ruiz', 'email' => 'taller@cliente.test'],
            'productos' => [
                ['nombre' => 'Tarjeta NFC del dueño', 'tipo' => 'nfc', 'destinos' => ['https://example.com/taller-ruiz', 'https://example.com/taller-ruiz/contacto'], 'peso' => 2],
                ['nombre' => 'QR ubicación', 'tipo' => 'qr', 'destinos' => ['https://maps.google.com/?q=Taller+Mecanico+Ruiz+Monterrey'], 'peso' => 3],
                ['nombre' => 'QR reseñas', 'tipo' => 'combinado', 'destinos' => ['https://g.page/r/taller-ruiz-demo/review'], 'peso' => 2],
            ],
        ],
        [
            'negocio' => ['nombre_comercial' => 'Florería Jazmín', 'responsable' => 'Lucía Méndez', 'email_contacto' => 'ventas@floreriajazmin.test', 'telefono' => '22 4567 8901', 'notas_internas' => 'Cuenta suspendida a solicitud del cliente (cierre temporal).'],
            'usuario' => ['name' => 'Lucía Méndez', 'email' => 'floreria@cliente.test'],
            'suspendido' => true,
            'productos' => [
                ['nombre' => 'QR pedidos en línea', 'tipo' => 'qr', 'destinos' => ['https://example.com/floreria-jazmin/pedidos'], 'peso' => 1],
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function correos(): array
    {
        return [self::ADMIN['email'], ...array_map(fn (array $n) => $n['usuario']['email'], self::NEGOCIOS)];
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no pueden cargarse en producción.');
        }

        mt_srand(2026);

        $admin = $this->administrador();
        Auth::setUser($admin);

        foreach (self::NEGOCIOS as $especificacion) {
            $negocio = $this->negocio($especificacion);

            foreach ($especificacion['productos'] as $indice => $datosProducto) {
                $producto = $this->producto($negocio, $datosProducto, $admin, $indice);
                $this->eventos($producto, (int) $datosProducto['peso']);
            }

            if (($especificacion['suspendido'] ?? false) && $negocio->estaActivo()) {
                app(CambiarEstadoNegocio::class)->ejecutar($negocio, EstadoCuenta::Suspendido, 'Datos de demostración');
            }
        }

        Auth::forgetUser();

        $this->command->info('Datos de demostración listos. Consulta las credenciales con: php artisan dasti:credenciales-demo');
    }

    private function administrador(): User
    {
        $admin = User::query()->firstOrNew(['email' => self::ADMIN['email']]);

        if (! $admin->exists) {
            $admin->fill(['name' => self::ADMIN['name'], 'password' => config('dasti.demo_password')]);
            $admin->forceFill([
                'rol' => RolUsuario::Admin,
                'estado' => EstadoCuenta::Activo,
                'email_verified_at' => now(),
                'debe_cambiar_password' => false,
            ])->save();
        }

        return $admin;
    }

    /**
     * @param  array{negocio: array{nombre_comercial: string, responsable: string, email_contacto: string, telefono: string, notas_internas?: string}, usuario: array{name: string, email: string}}  $especificacion
     */
    private function negocio(array $especificacion): Negocio
    {
        $existente = Negocio::query()->where('nombre_comercial', $especificacion['negocio']['nombre_comercial'])->first();

        if ($existente !== null) {
            return $existente;
        }

        if (User::query()->where('email', $especificacion['usuario']['email'])->exists()) {
            throw new RuntimeException("El correo {$especificacion['usuario']['email']} ya existe con otro negocio; no se modificará.");
        }

        $negocio = app(CrearNegocio::class)->ejecutar(
            [...$especificacion['negocio'], 'estado' => EstadoCuenta::Activo->value],
            [...$especificacion['usuario'], 'password' => (string) config('dasti.demo_password')],
        );

        // En demostración se omite el cambio obligatorio de contraseña.
        User::query()->where('email', $especificacion['usuario']['email'])->update(['debe_cambiar_password' => false]);

        $hace = now()->subDays(95);
        $negocio->forceFill(['created_at' => $hace, 'updated_at' => $hace])->saveQuietly();

        return $negocio;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function producto(Negocio $negocio, array $datos, User $admin, int $indice): Producto
    {
        $existente = Producto::query()->deNegocio($negocio->id)->where('nombre', $datos['nombre'])->first();

        if ($existente !== null) {
            return $existente;
        }

        /** @var list<string> $destinos */
        $destinos = $datos['destinos'];
        $creadoEn = now()->subDays(90 - $indice * 3);

        [$producto] = app(CrearProducto::class)->ejecutar($negocio, [
            'nombre' => (string) $datos['nombre'],
            'tipo' => (string) $datos['tipo'],
            'url_destino' => $destinos[0] ?? null,
            'estado_preparacion' => $destinos === [] ? 'pendiente' : 'entregado',
            'fecha_entrega' => $destinos === [] ? null : $creadoEn->addDays(2)->toDateString(),
            'referencia_comercial' => 'PED-2026-'.str_pad((string) ($negocio->id * 10 + $indice), 4, '0', STR_PAD_LEFT),
        ], $admin);

        $cliente = User::query()->where('negocio_id', $negocio->id)->first() ?? $admin;

        foreach (array_slice($destinos, 1) as $destino) {
            app(CambiarDestinoProducto::class)->ejecutar($producto, $destino, $cliente);
        }

        // Fechas realistas: alta hace ~3 meses y cambios espaciados.
        $producto->forceFill(['created_at' => $creadoEn, 'updated_at' => $creadoEn])->saveQuietly();
        $cambios = CambioDestino::query()->where('producto_id', $producto->id)->orderBy('id')->get();

        foreach ($cambios as $posicion => $cambio) {
            $fecha = $creadoEn->addDays($posicion * 35);
            $cambio->forceFill(['created_at' => $fecha])->saveQuietly();
            $producto->forceFill(['destino_actualizado_en' => $fecha])->saveQuietly();
        }

        if ($datos['suspendido'] ?? false) {
            app(CambiarEstadoProducto::class)->ejecutar($producto, EstadoProducto::Suspendido, 'Datos de demostración');
        }

        return $producto->refresh();
    }

    /**
     * Genera accesos sintéticos de los últimos 60 días con una tendencia y
     * variación semanal verosímiles. Solo si el producto no tiene eventos.
     */
    private function eventos(Producto $producto, int $peso): void
    {
        if ($peso === 0 || $producto->eventosAcceso()->exists()) {
            return;
        }

        $dispositivos = [CategoriaDispositivo::Movil->value => 78, CategoriaDispositivo::Tablet->value => 6, CategoriaDispositivo::Escritorio->value => 12, CategoriaDispositivo::Bot->value => 4];
        $navegadores = ['Chrome' => 44, 'Safari' => 36, 'Samsung Internet' => 8, 'Instagram' => 6, 'Firefox' => 3, 'Edge' => 3];

        $eventos = [];
        $diarios = [];
        $total = 0;
        $ultimo = null;
        $hoy = CarbonImmutable::today();

        for ($dias = 59; $dias >= 0; $dias--) {
            $fecha = $hoy->subDays($dias);
            $finDeSemana = $fecha->isWeekend() ? 1.4 : 1.0;
            $tendencia = 0.6 + (60 - $dias) / 75;
            $cantidad = (int) round($peso * $tendencia * $finDeSemana * mt_rand(40, 140) / 100);
            $humanos = 0;
            $bots = 0;

            for ($i = 0; $i < $cantidad; $i++) {
                $momento = $fecha->setTime(mt_rand(8, 22), mt_rand(0, 59), mt_rand(0, 59));
                $dispositivo = $this->elegir($dispositivos);
                $esBot = $dispositivo === CategoriaDispositivo::Bot->value;
                $esBot ? $bots++ : $humanos++;
                $esMovil = in_array($dispositivo, ['movil', 'tablet'], true);

                $eventos[] = [
                    'producto_id' => $producto->id,
                    'negocio_id' => $producto->negocio_id,
                    'ocurrido_en' => $momento,
                    'resultado' => ResultadoAcceso::Redirigido->value,
                    'dispositivo' => $dispositivo,
                    'navegador' => $esBot ? 'Otro' : $this->elegir($navegadores),
                    'sistema_operativo' => $esBot ? 'Otro' : ($esMovil ? (mt_rand(0, 100) < 55 ? 'Android' : 'iOS') : (mt_rand(0, 100) < 60 ? 'Windows' : 'macOS')),
                    'es_bot' => $esBot,
                    // Huella sintética: ~70 % de visitantes distintos por día.
                    'huella_diaria' => $dias < (int) config('dasti.dias_conservacion_huella')
                        ? hash('sha256', $producto->id.'|'.$fecha->toDateString().'|'.mt_rand(1, max(1, (int) ceil($cantidad * 0.7))))
                        : null,
                ];

                $ultimo = $esBot ? $ultimo : ($ultimo === null || $momento->greaterThan($ultimo) ? $momento : $ultimo);
            }

            if ($cantidad > 0) {
                $diarios[] = ['producto_id' => $producto->id, 'negocio_id' => $producto->negocio_id, 'fecha' => $fecha->toDateString(), 'total' => $humanos, 'bots' => $bots];
                $total += $humanos;
            }
        }

        DB::transaction(function () use ($eventos, $diarios, $producto, $total, $ultimo) {
            foreach (array_chunk($eventos, 500) as $lote) {
                DB::table('eventos_acceso')->insert($lote);
            }

            DB::table('accesos_diarios')->insertOrIgnore($diarios);
            $producto->forceFill(['total_accesos' => $total, 'ultimo_acceso_en' => $ultimo])->saveQuietly();
        });
    }

    /**
     * @param  array<string, int>  $pesos
     */
    private function elegir(array $pesos): string
    {
        $tirada = mt_rand(1, array_sum($pesos));

        foreach ($pesos as $valor => $peso) {
            $tirada -= $peso;

            if ($tirada <= 0) {
                return (string) $valor;
            }
        }

        return (string) array_key_first($pesos);
    }
}
