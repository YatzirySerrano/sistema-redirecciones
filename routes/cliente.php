<?php

use App\Http\Controllers\Cliente\EstadisticaController;
use App\Http\Controllers\Cliente\InicioController;
use App\Http\Controllers\Cliente\ProductoController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel del cliente
|--------------------------------------------------------------------------
|
| Todas las consultas parten del negocio del usuario autenticado; el acceso
| a productos concretos se valida además con ProductoPolicy.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:usar-panel-cliente'])
    ->prefix('panel')
    ->name('cliente.')
    ->group(function () {
        Route::get('/', InicioController::class)->name('inicio');
        Route::get('productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
        Route::put('productos/{producto}/destino', [ProductoController::class, 'destino'])
            ->middleware('throttle:cambios-destino')
            ->name('productos.destino');
        Route::get('estadisticas', EstadisticaController::class)
            ->middleware('can:ver-estadisticas-cliente')
            ->name('estadisticas.index');
    });
