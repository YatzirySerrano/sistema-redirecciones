<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoContratacion;
use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Fidelidad\ResumenFidelidad;
use App\Services\Pagos\PasarelaPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Servicios comerciales y contrataciones de los negocios (sin datos de
 * consumidores).
 */
class ServicioController extends Controller
{
    public function index(Request $request, ResumenFidelidad $resumen, PasarelaPago $pasarela): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoContratacion::class)],
            'negocio_id' => ['nullable', 'integer'],
            'servicio' => ['nullable', Rule::in(Servicio::ADICIONALES)],
        ]);
        /** @var User $usuario */
        $usuario = $request->user();

        $contrataciones = Contratacion::query()->with(['negocio:id,nombre_comercial,estado', 'servicio:id,nombre,clave', 'pago:id,folio,estado,importe_centavos,moneda,proveedor', 'creador:id,name'])
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $e) => $q->where('estado', $e))
            ->when($filtros['servicio'] ?? null, fn (Builder $q, string $c) => $q->whereHas('servicio', fn (Builder $s) => $s->where('clave', $c)))
            ->when($filtros['negocio_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('negocio_id', (int) $id))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/servicios/Index', [
            'servicios' => Servicio::query()->orderBy('orden')->orderBy('id')->get()->map(fn (Servicio $s) => [
                'id' => $s->id,
                'clave' => $s->clave,
                'nombre' => $s->nombre,
                'descripcion' => $s->descripcion,
                'beneficios' => $s->beneficios ?? [],
                'es_base' => $s->es_base,
                'precio_centavos' => $s->precio_centavos,
                'moneda' => $s->moneda,
                'impuestos_incluidos' => $s->impuestos_incluidos,
                'vigencia_meses' => $s->vigencia_meses,
                'contratable_en_linea' => $s->contratable_en_linea,
                'activo' => $s->activo,
                'contrataciones_activas' => Contratacion::query()->vigentes()->where('servicio_id', $s->id)->count(),
            ]),
            'contrataciones' => [
                'data' => $contrataciones->getCollection()->map(fn (Contratacion $c) => [
                    'id' => $c->id,
                    'negocio' => ['id' => $c->negocio->id, 'nombre_comercial' => $c->negocio->nombre_comercial],
                    'servicio' => $c->servicio->nombre,
                    'servicio_clave' => $c->servicio->clave,
                    'creado_por' => $c->creador?->name,
                    'created_at' => $c->created_at?->toIso8601String(),
                    'estado' => $c->estado->value,
                    'estado_etiqueta' => $c->estado->etiqueta(),
                    'vencida' => $c->estado === EstadoContratacion::Activa && $c->estaVencida(),
                    'origen' => $c->origen->etiqueta(),
                    'inicia_en' => $c->inicia_en?->toIso8601String(),
                    'vence_en' => $c->vence_en?->toIso8601String(),
                    'motivo' => $c->motivo,
                    'restriccion_manual' => $c->restriccion_manual,
                    'pago' => $c->pago === null ? null : [
                        'folio' => $c->pago->folio,
                        'estado' => $c->pago->estado->etiqueta(),
                        'importe_centavos' => $c->pago->importe_centavos,
                        'moneda' => $c->pago->moneda,
                    ],
                ])->values(),
                'meta' => [
                    'current_page' => $contrataciones->currentPage(),
                    'from' => $contrataciones->firstItem(),
                    'to' => $contrataciones->lastItem(),
                    'total' => $contrataciones->total(),
                    'last_page' => $contrataciones->lastPage(),
                    'per_page' => $contrataciones->perPage(),
                    'links' => $contrataciones->linkCollection()->toArray(),
                ],
            ],
            'filtros' => [
                'estado' => $filtros['estado'] ?? '',
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
                'servicio' => $filtros['servicio'] ?? '',
            ],
            'negocio_filtro' => isset($filtros['negocio_id'])
                ? Negocio::query()->whereKey((int) $filtros['negocio_id'])->get(['id', 'nombre_comercial'])
                    ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])->first()
                : null,
            'estados' => EstadoContratacion::opciones(),
            'resumen' => $resumen->paraPlataforma(),
            'pasarela' => [
                'nombre' => 'Openpay',
                'configurado' => $pasarela->configurada(),
                'modo_real' => $pasarela->modoReal(),
                'notificaciones' => (string) config('services.openpay.webhook_user') !== '' && (string) config('services.openpay.webhook_password') !== '',
            ],
            'puede_gestionar' => $usuario->tienePermiso('servicios.gestionar'),
        ]);
    }

    public function update(Request $request, Servicio $servicio, RegistradorAuditoria $auditoria): RedirectResponse
    {
        abort_if($servicio->es_base, 422, 'El servicio base no se configura aquí.');

        $datos = $request->validate([
            'precio' => ['nullable', 'numeric', 'min:10', 'max:999999.99', 'decimal:0,2'],
            'impuestos_incluidos' => ['boolean'],
            'vigencia_meses' => ['nullable', 'integer', 'between:1,120'],
            'contratable_en_linea' => ['boolean'],
            'activo' => ['boolean'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'beneficios' => ['nullable', 'array', 'max:8'],
            'beneficios.*' => ['required', 'string', 'min:3', 'max:140'],
        ], [], ['precio' => 'precio', 'vigencia_meses' => 'vigencia', 'descripcion' => 'descripción', 'beneficios.*' => 'beneficio']);

        if (($datos['contratable_en_linea'] ?? false) && ! isset($datos['precio'])) {
            throw ValidationException::withMessages(['precio' => 'Define el precio antes de habilitar la contratación en línea.']);
        }

        $nuevos = [
            'precio_centavos' => isset($datos['precio']) ? (int) round(((float) $datos['precio']) * 100) : null,
            'impuestos_incluidos' => (bool) ($datos['impuestos_incluidos'] ?? true),
            'vigencia_meses' => $datos['vigencia_meses'] ?? null,
            'contratable_en_linea' => (bool) ($datos['contratable_en_linea'] ?? false),
            'activo' => (bool) ($datos['activo'] ?? true),
            'descripcion' => isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : $servicio->descripcion,
            'beneficios' => array_key_exists('beneficios', $datos)
                ? array_values(array_map(fn ($b) => trim((string) $b), (array) ($datos['beneficios'] ?? [])))
                : $servicio->beneficios,
        ];
        $anteriores = $servicio->only(array_keys($nuevos));
        $servicio->forceFill($nuevos);

        if ($servicio->isDirty()) {
            $cambios = array_intersect_key($servicio->only(array_keys($nuevos)), $servicio->getDirty());
            $servicio->save();
            $auditoria->registrar(
                accion: 'servicio.configurado',
                descripcion: "Configuración comercial de {$servicio->nombre} actualizada",
                recurso: $servicio,
                anteriores: array_intersect_key($anteriores, $cambios),
                nuevos: $cambios,
            );
        }

        $this->notificar('Configuración del servicio guardada. Aplica a las contrataciones nuevas.');

        return back();
    }
}
