<?php

use App\Http\Controllers\Paginas\DireccionController;
use App\Http\Controllers\Paginas\DominioController;
use App\Http\Controllers\Paginas\EditorController;
use App\Http\Controllers\Paginas\EstadisticaController;
use App\Http\Controllers\Paginas\InicioController;
use App\Http\Controllers\Paginas\MedioController;
use App\Http\Controllers\Paginas\MensajeController;
use App\Http\Controllers\Paginas\SupervisionController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequerirDosPasosAdministrador;
use App\Http\Middleware\RequiereServicio;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Página de enlaces (panel del negocio y administración)
|--------------------------------------------------------------------------
|
| Rutas del módulo Página de enlaces. Las páginas públicas y los dominios
| personalizados se registran en routes/paginas-publicas.php (sin sesión).
|
| Cada ruta del negocio exige su permiso Y que el negocio tenga el servicio
| contratado (RequiereServicio): consultar (activo, restringido o vencido)
| u operar (activo y vigente). El permiso por sí solo nunca habilita el
| módulo.
|
*/

$consultar = RequiereServicio::class.':pagina_enlaces';
$operar = RequiereServicio::class.':pagina_enlaces,operar';

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:usar-panel-cliente'])
    ->prefix('panel/paginas')
    ->name('cliente.paginas.')
    ->group(function () use ($consultar, $operar) {
        Route::get('/', InicioController::class)->middleware(['can:paginas.ver', $consultar])->name('inicio');

        // Editor visual
        Route::get('editor', [EditorController::class, 'show'])->middleware(['can:paginas.ver', $consultar])->name('editor');
        Route::put('borrador', [EditorController::class, 'guardar'])->middleware(['can:paginas.editar', $operar, 'throttle:60,1'])->name('borrador');
        Route::post('publicar', [EditorController::class, 'publicar'])->middleware(['can:paginas.publicar', $operar, 'throttle:20,1'])->name('publicar');
        Route::post('despublicar', [EditorController::class, 'despublicar'])->middleware(['can:paginas.publicar', $operar, 'throttle:20,1'])->name('despublicar');
        Route::get('versiones', [EditorController::class, 'versiones'])->middleware(['can:paginas.ver', $consultar])->name('versiones');
        Route::get('versiones/{version}', [EditorController::class, 'version'])->middleware(['can:paginas.ver', $consultar])->name('versiones.show');
        Route::post('versiones/{version}/restaurar', [EditorController::class, 'restaurar'])->middleware(['can:paginas.editar', $operar, 'throttle:20,1'])->name('versiones.restaurar');

        // Imágenes y videos
        Route::get('medios', [MedioController::class, 'index'])->middleware(['can:paginas.ver', $consultar])->name('medios.index');
        Route::post('medios', [MedioController::class, 'store'])->middleware(['can:paginas.editar', $operar, 'throttle:30,1'])->name('medios.store');
        Route::delete('medios/{uuid}', [MedioController::class, 'destroy'])->middleware(['can:paginas.editar', $operar])->whereUuid('uuid')->name('medios.destroy');

        // Dirección pública y QR
        Route::get('direccion/disponible', [DireccionController::class, 'disponible'])->middleware(['can:paginas.publicar', $consultar, 'throttle:60,1'])->name('direccion.disponible');
        Route::put('direccion', [DireccionController::class, 'update'])->middleware(['can:paginas.publicar', $operar, 'throttle:10,1'])->name('direccion.update');
        Route::get('qr', [DireccionController::class, 'qr'])->middleware(['can:paginas.ver', $consultar, 'throttle:qr'])->name('qr');

        // Estadísticas
        Route::get('estadisticas', [EstadisticaController::class, 'index'])->middleware(['can:paginas.estadisticas', $consultar])->name('estadisticas.index');
        Route::get('estadisticas/exportar', [EstadisticaController::class, 'exportar'])->middleware(['can:paginas.estadisticas', $consultar, 'throttle:20,1'])->name('estadisticas.exportar');

        // Mensajes del formulario de contacto
        Route::get('mensajes', [MensajeController::class, 'index'])->middleware(['can:paginas.mensajes', $consultar])->name('mensajes.index');
        Route::patch('mensajes/{mensaje}', [MensajeController::class, 'update'])->middleware(['can:paginas.mensajes', $consultar])->name('mensajes.update');
        Route::delete('mensajes/{mensaje}', [MensajeController::class, 'destroy'])->middleware(['can:paginas.mensajes', $consultar])->name('mensajes.destroy');

        // Dominio propio
        Route::get('dominios', [DominioController::class, 'index'])->middleware(['can:paginas.dominios', $consultar])->name('dominios.index');
        Route::post('dominios', [DominioController::class, 'store'])->middleware(['can:paginas.dominios', $operar, 'throttle:10,1'])->name('dominios.store');
        Route::post('dominios/{dominio}/verificar', [DominioController::class, 'verificar'])->middleware(['can:paginas.dominios', $operar, 'throttle:12,1'])->name('dominios.verificar');
        Route::patch('dominios/{dominio}/principal', [DominioController::class, 'principal'])->middleware(['can:paginas.dominios', $operar])->name('dominios.principal');
        Route::delete('dominios/{dominio}', [DominioController::class, 'destroy'])->middleware(['can:paginas.dominios', $consultar])->name('dominios.destroy');
    });

// Supervisión de DASTI (sin mensajes de visitantes ni datos personales).
Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', RequerirDosPasosAdministrador::class, 'can:paginas.supervisar'])
    ->prefix('admin/paginas')
    ->name('admin.paginas.')
    ->group(function () {
        Route::get('/', SupervisionController::class)->name('index');
    });
