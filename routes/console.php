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
