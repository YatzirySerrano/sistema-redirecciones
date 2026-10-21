<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Muestra las cuentas de demostración (solo en entorno local).
 */
class CredencialesDemo extends Command
{
    protected $signature = 'dasti:credenciales-demo';

    protected $description = 'Muestra los usuarios de demostración y su contraseña (solo entorno local)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Este comando solo está disponible con APP_ENV=local.');

            return self::FAILURE;
        }

        $usuarios = User::query()->with('negocio')->whereIn('email', DemoSeeder::correos())->orderBy('rol')->get();

        if ($usuarios->isEmpty()) {
            $this->warn('No hay datos de demostración. Ejecuta: php artisan db:seed --class=DemoSeeder');

            return self::SUCCESS;
        }

        $this->table(
            ['Rol', 'Correo', 'Negocio'],
            $usuarios->map(fn (User $u) => [$u->rol->etiqueta(), $u->email, $u->negocio->nombre_comercial ?? '—'])->all(),
        );
        $this->line('Contraseña de todas las cuentas: <info>'.config('dasti.demo_password').'</info> (DASTI_DEMO_PASSWORD)');

        return self::SUCCESS;
    }
}
