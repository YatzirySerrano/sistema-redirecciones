<?php

use App\Http\Controllers\Admin\ValidacionCorreoController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Validaciones en vivo del panel administrativo
|--------------------------------------------------------------------------
|
| Consultas ligeras usadas por los formularios mientras se escribe. Laravel
| vuelve a validar todo al guardar (Form Requests).
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', 'throttle:60,1'])
    ->prefix('admin/validaciones')
    ->name('admin.validaciones.')
    ->group(function () {
        Route::post('correo', ValidacionCorreoController::class)->name('correo');
    });
