<?php

use App\Http\Controllers\Cuenta\ContrasenaInicialController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\QrController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web del panel
|--------------------------------------------------------------------------
|
| No existe registro público: las cuentas las crea DASTI. Los enlaces
| permanentes (/r/{codigo}) viven en routes/redirecciones.php, fuera del
| grupo "web" (sin sesión ni cookies).
|
*/

Route::get('/', fn () => auth()->check() ? redirect()->route('inicio') : redirect()->route('login'))->name('home');

Route::middleware(['auth', VerificarCuentaActiva::class])->group(function () {
    Route::get('cambiar-contrasena', [ContrasenaInicialController::class, 'edit'])->name('contrasena-inicial.edit');
    Route::put('cambiar-contrasena', [ContrasenaInicialController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('contrasena-inicial.update');

    Route::middleware(RequerirContrasenaDefinitiva::class)->group(function () {
        Route::get('inicio', InicioController::class)->name('inicio');

        // QR: compartido por administrador y cliente propietario (ProductoPolicy).
        Route::get('productos/{producto}/qr', [QrController::class, 'show'])
            ->middleware('throttle:qr')
            ->name('productos.qr.show');
        Route::put('productos/{producto}/qr', [QrController::class, 'update'])->name('productos.qr.update');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/cliente.php';
require __DIR__.'/settings.php';
