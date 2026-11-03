<?php

use App\Http\Controllers\Publico\MedioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Archivos publicados por los negocios
|--------------------------------------------------------------------------
|
| Imágenes y videos de las Páginas de enlaces y de las tarjetas de
| Fidelidad. Sin sesión ni cookies; la ruta es relativa para que funcione
| también en los dominios personalizados.
|
*/

Route::get('medios/{uuid}.{extension}', MedioController::class)
    ->where(['uuid' => '[0-9a-f-]{36}', 'extension' => 'webp|png|jpg|gif|mp4|webm'])
    ->middleware('throttle:600,1')
    ->name('medios.show');
