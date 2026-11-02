<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPago;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagos de servicios: estados, reembolsos parciales y disputas pendientes
 * de revisión. No hay datos de tarjeta que mostrar (nunca se guardan).
 */
class PagoController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoPago::class)],
            'revision' => ['nullable', 'boolean'],
        ]);

        $pagos = Pago::query()->with(['negocio:id,nombre_comercial', 'servicio:id,nombre', 'iniciador:id,name'])
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $e) => $q->where('estado', $e))
            ->when($request->boolean('revision'), fn (Builder $q) => $q->where('requiere_revision', true))
            ->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('admin/pagos/Index', [
            'pagos' => [
                'data' => $pagos->getCollection()->map(fn (Pago $p) => [
                    'id' => $p->id,
                    'negocio' => ['id' => $p->negocio->id, 'nombre_comercial' => $p->negocio->nombre_comercial],
                    'servicio' => $p->servicio->nombre,
                    'proveedor' => $p->proveedor === Pago::PROVEEDOR_STRIPE ? 'Stripe' : 'Fuera de línea',
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'importe_centavos' => $p->importe_centavos,
                    'reembolsado_centavos' => $p->reembolsado_centavos,
                    'moneda' => $p->moneda,
                    'referencia' => $p->referencia,
                    'motivo' => $p->motivo,
                    'requiere_revision' => $p->requiere_revision,
                    'livemode' => $p->livemode,
                    'iniciado_por' => $p->iniciador?->name,
                    'pagado_en' => $p->pagado_en?->toIso8601String(),
                    'created_at' => $p->created_at?->toIso8601String(),
                ])->values(),
                'meta' => [
                    'current_page' => $pagos->currentPage(),
                    'from' => $pagos->firstItem(),
                    'to' => $pagos->lastItem(),
                    'total' => $pagos->total(),
                    'last_page' => $pagos->lastPage(),
                    'per_page' => $pagos->perPage(),
                    'links' => $pagos->linkCollection()->toArray(),
                ],
            ],
            'filtros' => ['estado' => $filtros['estado'] ?? '', 'revision' => $request->boolean('revision') ? '1' : ''],
            'estados' => EstadoPago::opciones(),
            'pendientes_revision' => Pago::query()->where('requiere_revision', true)->count(),
        ]);
    }

    public function revision(Request $request, Pago $pago, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $datos = $request->validate(['nota' => ['required', 'string', 'min:5', 'max:255']], [], ['nota' => 'nota de revisión']);

        $pago->forceFill(['requiere_revision' => false])->save();
        $auditoria->registrar(
            accion: 'pago.revisado',
            descripcion: 'Incidencia de pago revisada por DASTI',
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['nota' => $datos['nota']],
        );
        $this->notificar('Incidencia marcada como revisada.');

        return back();
    }
}
