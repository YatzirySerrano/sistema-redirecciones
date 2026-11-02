<?php

use App\Http\Controllers\Pagos\WebhookStripeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks de proveedores externos
|--------------------------------------------------------------------------
|
| Sin el grupo "web" (sin sesión, cookies ni CSRF): la autenticidad se
| comprueba con la firma de cada proveedor.
|
*/

Route::post('stripe/webhook', WebhookStripeController::class)
    ->middleware('throttle:120,1')
    ->name('stripe.webhook');
