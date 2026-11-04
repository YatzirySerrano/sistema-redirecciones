<?php

use App\Http\Controllers\Admin\DisenoClientesController;
use App\Http\Controllers\Fidelidad\DisenoController;
use App\Http\Controllers\Paginas\EditorController;
use App\Http\Controllers\Paginas\MedioController;
use App\Http\Middleware\DisenarParaNegocio;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequerirDosPasosAdministrador;
use App\Http\Middleware\RequiereServicio;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Diseño para clientes (personal DASTI)
|--------------------------------------------------------------------------
|
| El personal DASTI autorizado abre los editores de Fidelidad y Página de
| enlaces de un negocio elegido. Reutiliza los mismos controladores que el
| panel del negocio; DisenarParaNegocio exige el permiso
| `<modulo>.disenar_negocios` y fija el negocio de la ruta como negocio de
| trabajo. RequiereServicio sigue exigiendo la contratación: consultar para
| abrir el editor y operar (activa y vigente) para guardar o publicar.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', RequerirDosPasosAdministrador::class])
    ->prefix('admin')
    ->name('admin.disenos.')
    ->group(function () {
        Route::get('disenar', [DisenoClientesController::class, 'index'])->middleware('can:disenar-para-negocios')->name('index');

        // Fidelidad
        $consulta = RequiereServicio::class.':fidelidad';
        $opera = RequiereServicio::class.':fidelidad,operar';

        Route::prefix('disenar/negocios/{negocio}/fidelidad')
            ->middleware(['can:fidelidad.disenar_negocios', DisenarParaNegocio::class.':fidelidad', $consulta])
            ->name('fidelidad.')
            ->group(function () use ($opera) {
                Route::get('/', [DisenoController::class, 'edit'])->name('edit');
                Route::put('/', [DisenoController::class, 'guardar'])->middleware([$opera, 'throttle:60,1'])->name('update');
                Route::post('publicacion', [DisenoController::class, 'publicar'])->middleware([$opera, 'throttle:20,1'])->name('publicar');
                Route::post('versiones/{version}/restauracion', [DisenoController::class, 'restaurar'])->middleware([$opera, 'throttle:20,1'])->name('restaurar');
                Route::delete('variantes/{campana}', [DisenoController::class, 'eliminarVariante'])->middleware($opera)->name('variantes.destroy');
                Route::post('imagenes', [DisenoController::class, 'subirImagen'])->middleware([$opera, 'throttle:20,1'])->name('imagenes.store');
            });

        // Página de enlaces (sin dirección pública, dominios, mensajes ni
        // estadísticas: esos los administra el negocio).
        $consultar = RequiereServicio::class.':pagina_enlaces';
        $operar = RequiereServicio::class.':pagina_enlaces,operar';

        Route::prefix('disenar/negocios/{negocio}/pagina')
            ->middleware(['can:paginas.disenar_negocios', DisenarParaNegocio::class.':paginas', $consultar])
            ->name('paginas.')
            ->group(function () use ($operar) {
                Route::get('/', [EditorController::class, 'show'])->name('editor');
                Route::put('borrador', [EditorController::class, 'guardar'])->middleware([$operar, 'throttle:60,1'])->name('borrador');
                Route::post('publicar', [EditorController::class, 'publicar'])->middleware([$operar, 'throttle:20,1'])->name('publicar');
                Route::post('despublicar', [EditorController::class, 'despublicar'])->middleware([$operar, 'throttle:20,1'])->name('despublicar');
                Route::get('versiones', [EditorController::class, 'versiones'])->name('versiones');
                Route::get('versiones/{version}', [EditorController::class, 'version'])->name('versiones.show');
                Route::post('versiones/{version}/restaurar', [EditorController::class, 'restaurar'])->middleware([$operar, 'throttle:20,1'])->name('versiones.restaurar');
                Route::get('medios', [MedioController::class, 'index'])->name('medios.index');
                Route::post('medios', [MedioController::class, 'store'])->middleware([$operar, 'throttle:30,1'])->name('medios.store');
                Route::delete('medios/{uuid}', [MedioController::class, 'destroy'])->middleware($operar)->whereUuid('uuid')->name('medios.destroy');
            });
    });
