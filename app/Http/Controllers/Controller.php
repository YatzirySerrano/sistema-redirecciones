<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Notificación discreta (toast) para la siguiente vista.
     */
    protected function notificar(string $mensaje, string $tipo = 'success'): void
    {
        Inertia::flash('toast', ['type' => $tipo, 'message' => $mensaje]);
    }
}
