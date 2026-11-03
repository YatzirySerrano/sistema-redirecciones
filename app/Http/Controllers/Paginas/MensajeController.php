<?php

namespace App\Http\Controllers\Paginas;

use App\Models\Paginas\MensajePagina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bandeja de mensajes del formulario de contacto (permiso
 * paginas.mensajes). Leer, marcar y eliminar se permite también en modo de
 * solo lectura: son datos personales que el negocio debe poder gestionar.
 */
class MensajeController extends ControladorPaginas
{
    public function index(Request $request): Response
    {
        $negocio = $this->negocio($request);
        $filtro = $request->query('filtro') === 'sin_leer' ? 'sin_leer' : 'todos';

        $mensajes = MensajePagina::query()->where('negocio_id', $negocio->id)
            ->when($filtro === 'sin_leer', fn ($q) => $q->whereNull('leido_en'))
            ->latest('id')->paginate(20)->withQueryString()
            ->through(fn (MensajePagina $m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'email' => $m->email,
                'telefono' => $m->telefono,
                'mensaje' => $m->mensaje,
                'leido' => $m->leido_en !== null,
                'recibido_en' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('cliente/paginas/Mensajes', [
            ...$this->contexto($request),
            'mensajes' => self::paginado($mensajes),
            'filtro' => $filtro,
            'sin_leer' => MensajePagina::query()->where('negocio_id', $negocio->id)->whereNull('leido_en')->count(),
        ]);
    }

    public function update(Request $request, MensajePagina $mensaje): RedirectResponse
    {
        $this->delNegocio($request, $mensaje);
        $request->validate(['leido' => ['required', 'boolean']]);
        $mensaje->forceFill(['leido_en' => $request->boolean('leido') ? now() : null])->save();

        return back();
    }

    public function destroy(Request $request, MensajePagina $mensaje): RedirectResponse
    {
        $this->delNegocio($request, $mensaje)->delete();
        $this->notificar('Mensaje eliminado.');

        return back();
    }
}
