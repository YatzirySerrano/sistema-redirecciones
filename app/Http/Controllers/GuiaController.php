<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guía de uso dentro de la plataforma. El contenido vive en la interfaz;
 * aquí solo se indica el ámbito (personal DASTI o negocio) para mostrar
 * los temas que corresponden a cada tipo de cuenta.
 */
class GuiaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('guia/Index', [
            'ambito' => $usuario->esAdministrador() ? 'admin' : 'cliente',
        ]);
    }
}
