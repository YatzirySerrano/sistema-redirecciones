<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Solo carga datos de demostración y nunca en producción. El
     * administrador real se crea con `php artisan dasti:crear-administrador`.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no pueden cargarse en producción.');
        }

        $this->call(DemoSeeder::class);
    }
}
