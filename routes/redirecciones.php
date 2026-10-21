<?php

use App\Http\Controllers\Publico\RedireccionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Enlaces permanentes públicos
|--------------------------------------------------------------------------
|
| URL grabada en NFC e impresa en QR: {APP_URL}/r/{codigo}. Se registra sin
| el grupo "web" (sin sesión, cookies ni CSRF) para responder lo más rápido
| posible y no dejar rastro en el navegador del visitante.
|
*/

Route::get('r/{codigo}', RedireccionController::class)
    ->where('codigo', '[A-Za-z0-9]{1,32}')
    ->middleware('throttle:redirecciones')
    ->name('redireccion');
