<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Requiere en el servidor la entrada de cron:
| * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::command('dasti:depurar-eventos')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('dasti:vencer-contrataciones')->hourly()->withoutOverlapping();
Schedule::command('dasti:conciliar-pagos')->everyTenMinutes()->withoutOverlapping();

// Página de enlaces: conservación de eventos y revisión de dominios propios.
Schedule::command('dasti:depurar-paginas')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('dasti:revisar-dominios')->everyTenMinutes()->withoutOverlapping();
