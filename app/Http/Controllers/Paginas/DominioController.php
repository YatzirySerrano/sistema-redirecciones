<?php

namespace App\Http\Controllers\Paginas;

use App\Models\Paginas\DominioPagina;
use App\Services\Paginas\Dominios\GestorDominios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dominios personalizados (permiso paginas.dominios). Un dominio de otro
 * negocio responde 404.
 */
class DominioController extends ControladorPaginas
{
    public function index(Request $request, GestorDominios $gestor): Response
    {
        $pagina = $this->pagina($request);

        return Inertia::render('cliente/paginas/Dominios', [
            ...$this->contexto($request),
            'pagina' => $this->resumenPagina($pagina),
            'dominios' => $pagina->dominios()->oldest('id')->get()->map(fn (DominioPagina $d) => [
                'id' => $d->id,
                'host' => $d->host,
                'host_unicode' => $d->host_unicode,
                'estado' => $d->estado->value,
                'estado_etiqueta' => $d->estado->etiqueta(),
                'es_principal' => $d->es_principal,
                'reemplaza_a' => $d->reemplaza_a_id,
                'ultimo_error' => $d->ultimo_error,
                'ultima_revision_en' => $d->ultima_revision_en?->toIso8601String(),
                'proxima_revision_en' => $d->proxima_revision_en?->toIso8601String(),
                'https_verificado_en' => $d->https_verificado_en?->toIso8601String(),
                'diagnostico' => $d->diagnostico,
                'instrucciones' => $gestor->instrucciones($d),
            ]),
            'destino_cname' => config('dasti.paginas.dominios.cname'),
            'maximo' => (int) config('dasti.paginas.dominios.max_por_pagina', 3),
        ]);
    }

    public function store(Request $request, GestorDominios $gestor): RedirectResponse
    {
        $request->validate(['host' => ['required', 'string', 'max:260'], 'reemplaza' => ['nullable', 'integer']]);
        $pagina = $this->pagina($request);
        $reemplaza = $request->filled('reemplaza')
            ? $this->delNegocio($request, DominioPagina::query()->findOrFail((int) $request->input('reemplaza')))
            : null;

        $gestor->registrar($pagina, (string) $request->input('host'), $this->usuario($request), $reemplaza);
        $this->notificar('Dominio agregado. Ahora crea los registros DNS que te indicamos.');

        return back();
    }

    public function verificar(Request $request, DominioPagina $dominio, GestorDominios $gestor): RedirectResponse
    {
        $this->delNegocio($request, $dominio);
        $dominio = $gestor->verificar($dominio, $this->usuario($request));

        $this->notificar(match ($dominio->estado->value) {
            'activo' => '¡Listo! Tu dominio funciona con HTTPS.',
            'dns_verificado' => 'DNS verificado. Estamos activando HTTPS; puede tardar unos minutos.',
            default => $dominio->ultimo_error ?? 'Aún no detectamos la configuración.',
        }, $dominio->estado->value === 'activo' ? 'success' : 'info');

        return back();
    }

    public function principal(Request $request, DominioPagina $dominio, GestorDominios $gestor): RedirectResponse
    {
        $gestor->hacerPrincipal($this->delNegocio($request, $dominio), $this->usuario($request));
        $this->notificar('Dominio principal actualizado.');

        return back();
    }

    public function destroy(Request $request, DominioPagina $dominio, GestorDominios $gestor): RedirectResponse
    {
        $gestor->eliminar($this->delNegocio($request, $dominio), $this->usuario($request));
        $this->notificar('Dominio desvinculado. Tu página sigue disponible en la dirección de DASTI.');

        return back();
    }
}
