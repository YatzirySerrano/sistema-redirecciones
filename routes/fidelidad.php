<?php

use App\Http\Controllers\Fidelidad\ActividadController;
use App\Http\Controllers\Fidelidad\AjusteController;
use App\Http\Controllers\Fidelidad\CampanaController;
use App\Http\Controllers\Fidelidad\ConsumidorController;
use App\Http\Controllers\Fidelidad\CredencialController;
use App\Http\Controllers\Fidelidad\DisenoController;
use App\Http\Controllers\Fidelidad\InicioController;
use App\Http\Controllers\Fidelidad\MostradorController;
use App\Http\Controllers\Fidelidad\PersonalController;
use App\Http\Controllers\Fidelidad\PublicoController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequiereServicio;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fidelidad DASTI
|--------------------------------------------------------------------------
|
| Consumidor (público, solo lectura): /f/{codigo} (tarjeta NFC/QR) y
| /mi-fidelidad/{token} (enlace digital). Nunca registran visitas.
|
| Negocio (/panel/fidelidad): permiso del rol + contratación del negocio.
| Consultar exige el servicio en estado activo, restringido o vencido;
| operar (visitas, canjes, altas) exige el servicio activo y vigente.
|
*/

// Recuperación por correo: límite propio por IP y por correo (evita
// enumeración y abuso del envío).
RateLimiter::for('fidelidad-recuperacion', fn (Request $request) => [
    Limit::perMinute(5)->by('ip|'.$request->ip()),
    Limit::perHour(5)->by('correo|'.mb_strtolower((string) $request->input('email'))),
]);
RateLimiter::for('fidelidad-publico', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

Route::middleware('throttle:fidelidad-publico')->group(function () {
    Route::get('f/{codigo}', [PublicoController::class, 'credencial'])->name('fidelidad.publico.credencial');
    Route::get('mi-fidelidad/recuperar', [PublicoController::class, 'recuperar'])->name('fidelidad.publico.recuperar');
    Route::post('mi-fidelidad/recuperar', [PublicoController::class, 'enviarRecuperacion'])
        ->withoutMiddleware('throttle:fidelidad-publico')
        ->middleware('throttle:fidelidad-recuperacion')
        ->name('fidelidad.publico.recuperar.enviar');
    Route::get('mi-fidelidad/verificar/{token}', [PublicoController::class, 'verificar'])->name('fidelidad.publico.verificar');
    Route::get('mi-fidelidad/renovar/{token}', [PublicoController::class, 'renovar'])->name('fidelidad.publico.renovar');
    Route::get('mi-fidelidad/{token}', [PublicoController::class, 'enlace'])->name('fidelidad.publico.enlace');
});

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:usar-panel-cliente'])
    ->prefix('panel/fidelidad')
    ->name('cliente.fidelidad.')
    ->group(function () {
        // Contratación: ahora vive en «Servicios» (se conserva la dirección
        // anterior como redirección).
        Route::redirect('contratacion', '/panel/servicios/fidelidad')->name('contratacion');

        $consulta = RequiereServicio::class.':fidelidad';
        $opera = RequiereServicio::class.':fidelidad,operar';

        Route::middleware($consulta)->group(function () use ($opera) {
            Route::get('/', InicioController::class)->middleware('can:fidelidad.ver')->name('inicio');

            // Mostrador: identificar, registrar visitas y canjear.
            Route::get('mostrador', [MostradorController::class, 'show'])->middleware('can:fidelidad.ver')->name('mostrador');
            // Búsqueda mientras se escribe (con espera en la interfaz): el
            // límite contempla ráfagas de tecleo; nunca registra visitas.
            Route::get('mostrador/identificar', [MostradorController::class, 'identificar'])
                ->middleware(['can:fidelidad.ver', 'throttle:180,1'])->name('mostrador.identificar');
            Route::get('mostrador/consumidores/{consumidor}', [MostradorController::class, 'consumidor'])
                ->middleware(['can:fidelidad.ver', 'throttle:120,1'])->name('mostrador.consumidor');
            Route::post('visitas', [MostradorController::class, 'visita'])
                ->middleware([$opera, 'can:fidelidad.visitas', 'throttle:60,1'])->name('visitas.store');
            Route::post('recompensas/{recompensa}/canje', [MostradorController::class, 'canje'])
                ->middleware([$opera, 'can:fidelidad.canjear', 'throttle:60,1'])->name('recompensas.canje');

            // Consumidores
            Route::get('consumidores', [ConsumidorController::class, 'index'])->middleware('can:fidelidad.ver')->name('consumidores.index');
            Route::get('consumidores/create', [ConsumidorController::class, 'create'])->middleware([$opera, 'can:fidelidad.consumidores'])->name('consumidores.create');
            Route::post('consumidores', [ConsumidorController::class, 'store'])->middleware([$opera, 'can:fidelidad.consumidores'])->name('consumidores.store');
            Route::get('consumidores/{consumidor}', [ConsumidorController::class, 'show'])->middleware('can:fidelidad.ver')->name('consumidores.show');
            Route::get('consumidores/{consumidor}/edit', [ConsumidorController::class, 'edit'])->middleware([$opera, 'can:fidelidad.consumidores'])->name('consumidores.edit');
            Route::put('consumidores/{consumidor}', [ConsumidorController::class, 'update'])->middleware([$opera, 'can:fidelidad.consumidores'])->name('consumidores.update');
            Route::post('consumidores/{consumidor}/verificacion', [ConsumidorController::class, 'verificacion'])
                ->middleware([$opera, 'can:fidelidad.consumidores', 'throttle:6,1'])->name('consumidores.verificacion');
            Route::post('consumidores/{consumidor}/fusion', [ConsumidorController::class, 'fusion'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('consumidores.fusion');

            // Credenciales y enlace digital
            Route::post('consumidores/{consumidor}/credenciales', [CredencialController::class, 'store'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('credenciales.store');
            Route::post('credenciales/{credencial}/reemplazo', [CredencialController::class, 'reemplazo'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('credenciales.reemplazo');
            Route::delete('credenciales/{credencial}', [CredencialController::class, 'destroy'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('credenciales.destroy');
            Route::get('credenciales/{credencial}/qr', [CredencialController::class, 'qr'])
                ->middleware('can:fidelidad.ver')->name('credenciales.qr');
            Route::post('consumidores/{consumidor}/enlace', [CredencialController::class, 'enlace'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('consumidores.enlace');
            Route::delete('consumidores/{consumidor}/enlace', [CredencialController::class, 'revocarEnlace'])
                ->middleware([$opera, 'can:fidelidad.credenciales'])->name('consumidores.enlace.destroy');

            // Ajustes y anulaciones (con motivo)
            Route::post('participaciones/{participacion}/ajuste', [AjusteController::class, 'ajuste'])
                ->middleware([$opera, 'can:fidelidad.ajustes'])->name('participaciones.ajuste');
            Route::post('movimientos/{movimiento}/anulacion', [AjusteController::class, 'anularVisita'])
                ->middleware([$opera, 'can:fidelidad.ajustes'])->name('movimientos.anulacion');
            Route::post('recompensas/{recompensa}/anulacion', [AjusteController::class, 'anularRecompensa'])
                ->middleware([$opera, 'can:fidelidad.ajustes'])->name('recompensas.anulacion');

            // Campañas
            Route::get('campanas', [CampanaController::class, 'index'])->middleware('can:fidelidad.ver')->name('campanas.index');
            Route::get('campanas/create', [CampanaController::class, 'create'])->middleware([$opera, 'can:fidelidad.campanas'])->name('campanas.create');
            Route::post('campanas', [CampanaController::class, 'store'])->middleware([$opera, 'can:fidelidad.campanas'])->name('campanas.store');
            Route::get('campanas/{campana}/edit', [CampanaController::class, 'edit'])->middleware([$opera, 'can:fidelidad.campanas'])->name('campanas.edit');
            Route::put('campanas/{campana}', [CampanaController::class, 'update'])->middleware([$opera, 'can:fidelidad.campanas'])->name('campanas.update');
            Route::patch('campanas/{campana}/estado', [CampanaController::class, 'estado'])->middleware([$opera, 'can:fidelidad.campanas'])->name('campanas.estado');

            // Personal del negocio
            Route::get('personal', [PersonalController::class, 'index'])->middleware('can:fidelidad.personal')->name('personal.index');
            Route::post('personal', [PersonalController::class, 'store'])->middleware([$opera, 'can:fidelidad.personal', 'throttle:10,1'])->name('personal.store');
            Route::patch('personal/{usuario}/estado', [PersonalController::class, 'estado'])->middleware('can:fidelidad.personal')->name('personal.estado');
            Route::put('personal/{usuario}/rol', [PersonalController::class, 'rol'])->middleware('can:fidelidad.personal')->name('personal.rol');

            // Diseño de la tarjeta digital (solo apariencia: nunca cambia
            // visitas, metas ni recompensas).
            Route::get('diseno', [DisenoController::class, 'edit'])->middleware('can:fidelidad.diseno')->name('diseno.edit');
            Route::put('diseno', [DisenoController::class, 'guardar'])
                ->middleware([$opera, 'can:fidelidad.diseno', 'throttle:60,1'])->name('diseno.update');
            Route::post('diseno/publicacion', [DisenoController::class, 'publicar'])
                ->middleware([$opera, 'can:fidelidad.diseno', 'throttle:20,1'])->name('diseno.publicar');
            Route::post('diseno/versiones/{version}/restauracion', [DisenoController::class, 'restaurar'])
                ->middleware([$opera, 'can:fidelidad.diseno', 'throttle:20,1'])->name('diseno.restaurar');
            Route::delete('diseno/variantes/{campana}', [DisenoController::class, 'eliminarVariante'])
                ->middleware([$opera, 'can:fidelidad.diseno'])->name('diseno.variantes.destroy');
            Route::post('diseno/imagenes', [DisenoController::class, 'subirImagen'])
                ->middleware([$opera, 'can:fidelidad.diseno', 'throttle:20,1'])->name('diseno.imagenes.store');

            // Actividad y reporte por empleado
            Route::get('actividad', ActividadController::class)->middleware('can:fidelidad.auditoria')->name('actividad');
        });
    });
