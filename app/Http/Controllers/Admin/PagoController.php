<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoIntentoPago;
use App\Enums\EstadoPago;
use App\Http\Controllers\Controller;
use App\Models\AjustePago;
use App\Models\IntentoPago;
use App\Models\NotificacionPago;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Pagos\PasarelaPago;
use App\Services\Pagos\ServicioPagos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Órdenes de pago de servicios: intentos de cobro, reembolsos, contracargos
 * e incidencias pendientes de revisión. No hay datos de tarjeta que mostrar
 * (solo marca y últimos 4 dígitos que reporta Openpay).
 */
class PagoController extends Controller
{
    public function index(Request $request, PasarelaPago $pasarela): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoPago::class)],
            'revision' => ['nullable', 'boolean'],
            'servicio' => ['nullable', Rule::in(Servicio::ADICIONALES)],
            'buscar' => ['nullable', 'string', 'max:80'],
        ]);
        /** @var User $usuario */
        $usuario = $request->user();

        $pagos = Pago::query()->with([
            'negocio:id,nombre_comercial', 'servicio:id,nombre,clave', 'iniciador:id,name',
            'intentos' => fn ($q) => $q->latest('id'),
            'ajustes' => fn ($q) => $q->latest('id'),
        ])
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $e) => $q->where('estado', $e))
            ->when($request->boolean('revision'), fn (Builder $q) => $q->where('requiere_revision', true))
            ->when($filtros['servicio'] ?? null, fn (Builder $q, string $c) => $q->whereHas('servicio', fn (Builder $s) => $s->where('clave', $c)))
            ->when(trim((string) ($filtros['buscar'] ?? '')), function (Builder $q, string $texto) {
                $patron = '%'.addcslashes($texto, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('folio', 'like', $patron)
                    ->orWhereHas('negocio', fn (Builder $n) => $n->where('nombre_comercial', 'like', $patron)));
            })
            ->latest('id')->paginate(25)->withQueryString();

        $verificacion = NotificacionPago::query()->where('tipo', 'verification')->latest('id')->first();

        return Inertia::render('admin/pagos/Index', [
            'pagos' => [
                'data' => $pagos->getCollection()->map(fn (Pago $p) => [
                    'id' => $p->id,
                    'folio' => $p->folio,
                    'negocio' => ['id' => $p->negocio->id, 'nombre_comercial' => $p->negocio->nombre_comercial],
                    'servicio' => $p->servicio->nombre,
                    'proveedor' => $p->etiquetaProveedor(),
                    'en_linea' => $p->proveedor === Pago::PROVEEDOR_OPENPAY,
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
                    'reembolsable' => $p->proveedor === Pago::PROVEEDOR_OPENPAY
                        && in_array($p->estado, [EstadoPago::Pagado, EstadoPago::ReembolsoParcial], true)
                        && $p->reembolsado_centavos < $p->importe_centavos,
                    'consultable' => $p->proveedor === Pago::PROVEEDOR_OPENPAY && $p->intentos->contains(fn (IntentoPago $i) => $i->transaccion_id !== null),
                    'intentos' => $p->intentos->map(fn (IntentoPago $i) => [
                        'id' => $i->id,
                        'orden' => $i->orden_externa,
                        'transaccion' => $i->transaccion_id,
                        'estado' => $i->estado->value,
                        'estado_etiqueta' => $i->estado->etiqueta(),
                        'estado_externo' => $i->estado_externo,
                        'mensaje_error' => $i->mensaje_error,
                        'tarjeta' => $i->marca_tarjeta !== null ? trim(ucfirst($i->marca_tarjeta).' '.($i->tipo_tarjeta === 'debit' ? 'débito' : ($i->tipo_tarjeta === 'credit' ? 'crédito' : '')).($i->terminacion_tarjeta ? ' ••'.$i->terminacion_tarjeta : '')) : null,
                        'autorizacion' => $i->autorizacion,
                        'livemode' => $i->livemode,
                        'consultado_en' => $i->consultado_en?->toIso8601String(),
                        'created_at' => $i->created_at?->toIso8601String(),
                    ])->values(),
                    'ajustes' => $p->ajustes->map(fn (AjustePago $a) => [
                        'id' => $a->id,
                        'etiqueta' => $a->etiqueta(),
                        'estado' => $a->estado,
                        'importe_centavos' => $a->importe_centavos,
                        'detectado_en' => $a->detectado_en->toIso8601String(),
                        'resuelto_en' => $a->resuelto_en?->toIso8601String(),
                    ])->values(),
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
            'filtros' => [
                'estado' => $filtros['estado'] ?? '',
                'revision' => $request->boolean('revision') ? '1' : '',
                'servicio' => $filtros['servicio'] ?? '',
                'buscar' => $filtros['buscar'] ?? '',
            ],
            'estados' => EstadoPago::opciones(),
            'pendientes_revision' => Pago::query()->where('requiere_revision', true)->count(),
            'openpay' => [
                'configurado' => $pasarela->configurada(),
                'modo_real' => $pasarela->modoReal(),
                'notificaciones' => (string) config('services.openpay.webhook_user') !== '' && (string) config('services.openpay.webhook_password') !== '',
                'url_notificaciones' => route('openpay.notificaciones'),
                'codigo_verificacion' => $usuario->tienePermiso('pagos.gestionar') ? $verificacion?->codigo_verificacion : null,
                'verificacion_recibida_en' => $verificacion?->created_at?->toIso8601String(),
                'ultima_notificacion_en' => NotificacionPago::query()->where('tipo', '!=', 'verification')->latest('id')->value('created_at')?->toIso8601String(),
                'abiertos' => IntentoPago::query()->whereIn('estado', [EstadoIntentoPago::Creado, EstadoIntentoPago::Pendiente])->count(),
            ],
            'puede_gestionar' => $usuario->tienePermiso('pagos.gestionar'),
            'puede_reembolsar' => $usuario->tienePermiso('pagos.reembolsar'),
        ]);
    }

    public function revision(Request $request, Pago $pago, RegistradorAuditoria $auditoria): RedirectResponse
    {
        // La nota es opcional: la auditoría ya registra quién revisó y cuándo.
        $datos = $request->validate(['nota' => ['nullable', 'string', 'max:255']], [], ['nota' => 'nota de revisión']);
        $nota = trim((string) ($datos['nota'] ?? ''));
        /** @var User $actor */
        $actor = $request->user();

        $pago->forceFill(['requiere_revision' => false, 'revisado_por' => $actor->id, 'revisado_en' => now()])->save();
        $auditoria->registrar(
            accion: 'pago.revisado',
            descripcion: "Incidencia del pago {$pago->folio} revisada por DASTI",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['requiere_revision' => false, 'nota' => $nota === '' ? null : $nota],
            anteriores: ['requiere_revision' => true],
        );
        $this->notificar('Incidencia marcada como revisada.');

        return back();
    }

    /**
     * Consulta a Openpay el estado vigente de los cargos de la orden y lo
     * aplica (útil si una notificación no llegó).
     */
    public function consulta(Pago $pago, ServicioPagos $servicio, RegistradorAuditoria $auditoria): RedirectResponse
    {
        abort_unless($pago->proveedor === Pago::PROVEEDOR_OPENPAY, 422, 'Este pago no se realizó en línea.');

        $resultados = IntentoPago::query()->where('pago_id', $pago->id)->whereNotNull('transaccion_id')->get()
            ->map(fn (IntentoPago $i) => $servicio->consultarYAplicar($i, 'administración'));

        $auditoria->registrar(
            accion: 'pago.consultado',
            descripcion: "Estado del pago {$pago->folio} consultado a Openpay",
            recurso: $pago,
            negocio: $pago->negocio_id,
            nuevos: ['resultados' => $resultados->filter()->values()->all()],
        );

        $resultados->contains(null)
            ? $this->notificar('No pudimos consultar Openpay en este momento. Inténtalo nuevamente.', 'error')
            : $this->notificar('Estado actualizado con la información de Openpay.');

        return back();
    }

    public function reembolso(Request $request, Pago $pago, ServicioPagos $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['total', 'parcial'])],
            'importe' => ['exclude_unless:tipo,parcial', 'required', 'numeric', 'min:1', 'max:999999.99', 'decimal:0,2'],
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ], [], ['importe' => 'importe', 'motivo' => 'motivo']);
        /** @var User $actor */
        $actor = $request->user();

        $centavos = $datos['tipo'] === 'parcial' ? (int) round(((float) $datos['importe']) * 100) : null;
        $resultado = $servicio->reembolsar($pago, $centavos, (string) $datos['motivo'], $actor);

        $this->notificar("Reembolso enviado a Openpay ({$resultado}).");

        return back();
    }
}
