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
| a productos concretos se valida además con ProductoPolicy. Cada ruta exige
| también su permiso dinámico, que nunca amplía el alcance al negocio.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:usar-panel-cliente'])
    ->prefix('panel')
    ->name('cliente.')
    ->group(function () {
        Route::get('/', InicioController::class)->middleware('can:panel.ver')->name('inicio');
        Route::get('productos', [ProductoController::class, 'index'])->middleware('can:productos.ver')->name('productos.index');
        Route::get('productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
        Route::put('productos/{producto}/destino', [ProductoController::class, 'destino'])
            ->middleware('throttle:cambios-destino')
            ->name('productos.destino');
        // Solo el nombre descriptivo: requiere «productos.editar_nombre»
        // (ProductoPolicy::editarNombre); ignora cualquier otro campo.
        Route::patch('productos/{producto}/nombre', [ProductoController::class, 'nombre'])
            ->middleware('throttle:cambios-destino')
            ->name('productos.nombre');
        // Ampliar a 2 QR o reducir a 1: requiere «productos.cambiar_cantidad_qr»
        // (ningún rol de cliente lo tiene de inicio; ProductoPolicy).
        Route::patch('productos/{producto}/cantidad-qr', [ProductoController::class, 'cantidadQr'])
            ->middleware('throttle:cambios-destino')
            ->name('productos.cantidad-qr');
        Route::get('estadisticas', EstadisticaController::class)
            ->middleware('can:ver-estadisticas-cliente')
            ->name('estadisticas.index');
    });
