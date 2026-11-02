<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\EnlaceController;
use App\Http\Controllers\Admin\EstadisticaController;
use App\Http\Controllers\Admin\LogoNegocioController;
use App\Http\Controllers\Admin\NegocioController;
use App\Http\Controllers\Admin\OpcionesController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\PersonalizacionController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\RolController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Middleware\RequerirContrasenaDefinitiva;
use App\Http\Middleware\RequerirDosPasosAdministrador;
use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel administrativo de DASTI
|--------------------------------------------------------------------------
|
| El grupo exige el ámbito de plataforma (personal de DASTI); cada ruta
| exige además su permiso dinámico (CatalogoPermisos). Las acciones con
| reglas adicionales (estado, escalamiento de privilegios, propiedad) las
| vuelven a comprobar sus Form Requests, Actions y Policies.
|
*/

Route::middleware(['auth', VerificarCuentaActiva::class, RequerirContrasenaDefinitiva::class, 'can:administrar-plataforma', RequerirDosPasosAdministrador::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', PanelController::class)->middleware('can:panel.ver')->name('panel');

        // Negocios
        Route::get('negocios', [NegocioController::class, 'index'])->middleware('can:negocios.ver')->name('negocios.index');
        Route::get('negocios/create', [NegocioController::class, 'create'])->middleware('can:negocios.crear')->name('negocios.create');
        Route::post('negocios', [NegocioController::class, 'store'])->middleware('can:negocios.crear')->name('negocios.store');
        Route::get('negocios/{negocio}', [NegocioController::class, 'show'])->middleware('can:negocios.ver')->name('negocios.show');
        Route::get('negocios/{negocio}/edit', [NegocioController::class, 'edit'])->middleware('can:negocios.editar')->name('negocios.edit');
        Route::match(['put', 'patch'], 'negocios/{negocio}', [NegocioController::class, 'update'])->middleware('can:negocios.editar')->name('negocios.update');
        // Suspender o reactivar: el permiso concreto depende del estado
        // solicitado y se comprueba en CambiarEstadoRequest.
        Route::patch('negocios/{negocio}/estado', [NegocioController::class, 'estado'])->name('negocios.estado');
        Route::get('negocios/{negocio}/logo', [LogoNegocioController::class, 'show'])->middleware('can:negocios.ver')->name('negocios.logo.show');
        Route::post('negocios/{negocio}/logo', [LogoNegocioController::class, 'store'])->middleware('can:negocios.editar')->name('negocios.logo.store');
        Route::delete('negocios/{negocio}/logo', [LogoNegocioController::class, 'destroy'])->middleware('can:negocios.editar')->name('negocios.logo.destroy');

        // Usuarios y accesos
        Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('can:usuarios.ver')->name('usuarios.index');
        Route::get('usuarios/create', [UsuarioController::class, 'create'])->middleware('can:usuarios.crear')->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('can:usuarios.crear')->name('usuarios.store');
        Route::get('usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])->middleware('can:usuarios.ver')->name('usuarios.edit');
        Route::match(['put', 'patch'], 'usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('can:usuarios.editar')->name('usuarios.update');
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->middleware('can:usuarios.gestionar_accesos')->name('usuarios.estado');
        Route::post('usuarios/{usuario}/invitacion', [UsuarioController::class, 'invitacion'])
            ->middleware(['can:usuarios.gestionar_accesos', 'throttle:6,1'])
            ->name('usuarios.invitacion');
        Route::put('usuarios/{usuario}/contrasena', [UsuarioController::class, 'contrasena'])->middleware('can:usuarios.gestionar_accesos')->name('usuarios.contrasena');
        Route::put('usuarios/{usuario}/rol', [UsuarioController::class, 'rol'])->middleware('can:roles.asignar')->name('usuarios.rol');

        // Productos
        Route::get('productos', [ProductoController::class, 'index'])->middleware('can:productos.ver')->name('productos.index');
        Route::get('productos/create', [ProductoController::class, 'create'])->middleware('can:productos.crear')->name('productos.create');
        Route::post('productos', [ProductoController::class, 'store'])->middleware('can:productos.crear')->name('productos.store');
        Route::get('productos/{producto}', [ProductoController::class, 'show'])->middleware('can:productos.ver')->name('productos.show');
        Route::get('productos/{producto}/edit', [ProductoController::class, 'edit'])->middleware('can:productos.editar')->name('productos.edit');
        Route::match(['put', 'patch'], 'productos/{producto}', [ProductoController::class, 'update'])->middleware('can:productos.editar')->name('productos.update');
        Route::delete('productos/{producto}', [ProductoController::class, 'destroy'])->middleware('can:productos.eliminar')->name('productos.destroy');
        Route::put('productos/{producto}/destino', [ProductoController::class, 'destino'])->middleware('can:redirecciones.modificar_destino')->name('productos.destino');
        Route::patch('productos/{producto}/estado', [ProductoController::class, 'estado'])->name('productos.estado');
        Route::post('productos/{producto}/transferencia', [ProductoController::class, 'transferir'])->middleware('can:productos.transferir')->name('productos.transferir');

        Route::get('enlaces', [EnlaceController::class, 'index'])->middleware('can:redirecciones.ver')->name('enlaces.index');
        Route::get('estadisticas', EstadisticaController::class)->middleware('can:estadisticas.ver')->name('estadisticas.index');
        Route::get('auditoria', AuditoriaController::class)->middleware('can:auditoria.ver')->name('auditoria.index');

        // Configuración y personalización
        Route::get('configuracion', [ConfiguracionController::class, 'edit'])->middleware('can:configuracion.ver')->name('configuracion.edit');
        Route::put('configuracion', [ConfiguracionController::class, 'update'])->middleware('can:configuracion.editar')->name('configuracion.update');
        Route::get('configuracion/apariencia', [PersonalizacionController::class, 'edit'])->middleware('can:personalizacion.ver')->name('personalizacion.edit');
        Route::put('configuracion/apariencia', [PersonalizacionController::class, 'update'])->middleware('can:personalizacion.editar')->name('personalizacion.update');
        Route::delete('configuracion/apariencia', [PersonalizacionController::class, 'destroy'])->middleware('can:personalizacion.editar')->name('personalizacion.destroy');

        // Roles y permisos
        Route::get('roles', [RolController::class, 'index'])->middleware('can:roles.ver')->name('roles.index');
        Route::get('roles/create', [RolController::class, 'create'])->middleware('can:roles.crear')->name('roles.create');
        Route::post('roles', [RolController::class, 'store'])->middleware('can:roles.crear')->name('roles.store');
        Route::get('roles/{rol}', [RolController::class, 'show'])->middleware('can:roles.ver')->name('roles.show');
        Route::get('roles/{rol}/edit', [RolController::class, 'edit'])->middleware('can:roles.editar')->name('roles.edit');
        Route::put('roles/{rol}', [RolController::class, 'update'])->middleware('can:roles.editar')->name('roles.update');
        Route::delete('roles/{rol}', [RolController::class, 'destroy'])->middleware('can:roles.editar')->name('roles.destroy');

        // Opciones de los selectores con búsqueda remota (paginadas).
        Route::get('opciones/negocios', [OpcionesController::class, 'negocios'])->middleware('throttle:120,1')->name('opciones.negocios');
        Route::get('opciones/usuarios', [OpcionesController::class, 'usuarios'])->middleware('throttle:120,1')->name('opciones.usuarios');
        Route::get('opciones/productos', [OpcionesController::class, 'productos'])->middleware('throttle:120,1')->name('opciones.productos');
    });
