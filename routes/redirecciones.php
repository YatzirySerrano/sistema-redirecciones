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
    // Se acepta cualquier segmento: el controlador descarta los formatos
    // inválidos con la misma página neutra (404) que un código inexistente.
    ->where('codigo', '[^/]{1,64}')
    ->middleware('throttle:redirecciones')
    ->name('redireccion');
