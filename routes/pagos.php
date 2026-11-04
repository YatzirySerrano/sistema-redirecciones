<?php

use App\Http\Controllers\Admin\ContratacionController;
use App\Http\Controllers\Admin\PagoController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequerirDosPasosAdministrador;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Servicios contratados y pagos (panel de DASTI)
|--------------------------------------------------------------------------
|
| El personal de DASTI ve contrataciones y pagos, nunca los datos de los
| consumidores de Fidelidad de cada negocio.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', RequerirDosPasosAdministrador::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('servicios', [ServicioController::class, 'index'])->middleware('can:servicios.ver')->name('servicios.index');
        Route::put('servicios/{servicio}', [ServicioController::class, 'update'])->middleware('can:servicios.gestionar')->name('servicios.update');

        Route::post('contrataciones', [ContratacionController::class, 'store'])->middleware('can:servicios.gestionar')->name('contrataciones.store');
        Route::patch('contrataciones/{contratacion}/estado', [ContratacionController::class, 'estado'])->middleware('can:servicios.gestionar')->name('contrataciones.estado');

        Route::get('pagos', [PagoController::class, 'index'])->middleware('can:pagos.ver')->name('pagos.index');
        Route::patch('pagos/{pago}/revision', [PagoController::class, 'revision'])->middleware('can:pagos.gestionar')->name('pagos.revision');
        Route::post('pagos/{pago}/consulta', [PagoController::class, 'consulta'])->middleware(['can:pagos.gestionar', 'throttle:20,1'])->name('pagos.consulta');
        Route::post('pagos/{pago}/reembolso', [PagoController::class, 'reembolso'])->middleware(['can:pagos.reembolsar', 'throttle:10,1'])->name('pagos.reembolso');
    });
