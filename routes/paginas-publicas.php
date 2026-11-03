<?php

use App\Http\Controllers\Publico\AutorizacionDominioController;
use App\Http\Controllers\Publico\PaginaPublicaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Páginas de enlaces públicas (dominio de DASTI)
|--------------------------------------------------------------------------
|
| /{prefijo}/{slug}[/{subpagina}], sin el grupo "web": sin sesión, cookies
| ni CSRF. Los dominios personalizados no pasan por aquí: los atiende
| EnrutarDominiosPagina antes del enrutador. Los límites por IP se aplican
| en el controlador (comunes a ambos casos).
|
*/

$prefijo = trim((string) config('dasti.paginas.prefijo', 'l'), '/');
$slug = '[A-Za-z0-9-]{1,40}';
$uuid = '[0-9a-f-]{36}';

Route::get("{$prefijo}/{slug}/qr.svg", [PaginaPublicaController::class, 'qr'])
    ->where('slug', $slug)
    ->name('paginas.publica.qr');

Route::match(['get', 'post'], "{$prefijo}/{slug}/ir/{bloque}", [PaginaPublicaController::class, 'clic'])
    ->where(['slug' => $slug, 'bloque' => $uuid])
    ->name('paginas.publica.clic');

Route::post("{$prefijo}/{slug}/contacto", [PaginaPublicaController::class, 'contacto'])
    ->where('slug', $slug)
    ->name('paginas.publica.contacto');

Route::get("{$prefijo}/{slug}/{subpagina}", [PaginaPublicaController::class, 'mostrar'])
    ->where(['slug' => $slug, 'subpagina' => '[A-Za-z0-9-]{1,40}'])
    ->name('paginas.publica.subpagina');

Route::get("{$prefijo}/{slug}", [PaginaPublicaController::class, 'mostrar'])
    ->where('slug', $slug)
    ->name('paginas.publica');

// Autorización de certificados bajo demanda (Caddy on_demand_tls).
Route::get('interno/dominios/autorizar', AutorizacionDominioController::class)
    ->name('paginas.dominios.autorizar');
