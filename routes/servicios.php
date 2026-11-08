<?php

use App\Http\Controllers\Cliente\ServicioController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Servicios del negocio (panel del cliente)
|--------------------------------------------------------------------------
|
| Catálogo (incluidos, disponibles y contratados) y compra en línea con
| Openpay. Cada servicio adicional se contrata por separado. El retorno del
| formulario de pago solo muestra el estado verificado con Openpay.
|
| Todo el apartado exige «servicios.ver» (cuenta de negocio): sin él no se
| ve, no se abre por URL y no se puede iniciar un pago. Pagar exige además
| «servicios.contratar». Los productos QR/NFC no dependen de este permiso.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:usar-panel-cliente', 'can:servicios.ver'])
    ->prefix('panel/servicios')
    ->name('cliente.servicios.')
    ->group(function () {
        Route::get('/', [ServicioController::class, 'index'])->name('index');
        Route::get('pago/retorno', [ServicioController::class, 'retorno'])
            ->middleware('throttle:30,1')
            ->name('retorno');
        Route::get('{clave}', [ServicioController::class, 'show'])
            ->where('clave', '[a-z_]{3,40}')
            ->name('show');
        Route::post('{clave}/pago', [ServicioController::class, 'pago'])
            ->where('clave', '[a-z_]{3,40}')
            ->middleware(['can:servicios.contratar', 'throttle:10,1'])
            ->name('pago');
    });
