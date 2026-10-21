<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\EnlaceController;
use App\Http\Controllers\Admin\EstadisticaController;
use App\Http\Controllers\Admin\LogoNegocioController;
use App\Http\Controllers\Admin\NegocioController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequerirDosPasosAdministrador;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel administrativo de DASTI
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', RequerirDosPasosAdministrador::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', PanelController::class)->name('panel');

        Route::resource('negocios', NegocioController::class)->except(['destroy']);
        Route::patch('negocios/{negocio}/estado', [NegocioController::class, 'estado'])->name('negocios.estado');
        Route::get('negocios/{negocio}/logo', [LogoNegocioController::class, 'show'])->name('negocios.logo.show');
        Route::post('negocios/{negocio}/logo', [LogoNegocioController::class, 'store'])->name('negocios.logo.store');
        Route::delete('negocios/{negocio}/logo', [LogoNegocioController::class, 'destroy'])->name('negocios.logo.destroy');

        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->name('usuarios.estado');
        Route::post('usuarios/{usuario}/invitacion', [UsuarioController::class, 'invitacion'])
            ->middleware('throttle:6,1')
            ->name('usuarios.invitacion');
        Route::put('usuarios/{usuario}/contrasena', [UsuarioController::class, 'contrasena'])->name('usuarios.contrasena');

        Route::resource('productos', ProductoController::class);
        Route::put('productos/{producto}/destino', [ProductoController::class, 'destino'])->name('productos.destino');
        Route::patch('productos/{producto}/estado', [ProductoController::class, 'estado'])->name('productos.estado');
        Route::post('productos/{producto}/transferencia', [ProductoController::class, 'transferir'])->name('productos.transferir');

        Route::get('enlaces', [EnlaceController::class, 'index'])->name('enlaces.index');
        Route::get('estadisticas', EstadisticaController::class)->name('estadisticas.index');
        Route::get('auditoria', AuditoriaController::class)->name('auditoria.index');

        Route::get('configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    });
