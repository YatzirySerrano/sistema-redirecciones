<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada tras iniciar sesión: envía a cada rol a su panel.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()?->esAdministrador()
            ? redirect()->route('admin.panel')
            : redirect()->route('cliente.inicio');
    }
}
