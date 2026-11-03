<?php

use App\Http\Controllers\Pagos\NotificacionOpenpayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notificaciones de proveedores externos
|--------------------------------------------------------------------------
|
| Sin el grupo "web" (sin sesión, cookies ni CSRF). Openpay se autentica con
| HTTP Basic y su contenido se verifica consultando la API.
|
*/

Route::post('openpay/notificaciones', NotificacionOpenpayController::class)
    ->middleware('throttle:240,1')
    ->name('openpay.notificaciones');
