<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Negocios\ActualizarNegocio;
use App\Actions\Negocios\CambiarEstadoNegocio;
use App\Actions\Negocios\CrearNegocio;
use App\Enums\EstadoCuenta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CambiarEstadoRequest;
use App\Http\Requests\Admin\GuardarNegocioRequest;
use App\Http\Resources\CambioDestinoResource;
use App\Http\Resources\NegocioResource;
use App\Http\Resources\ProductoResource;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Http\Resources\UsuarioResource;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class NegocioController extends Controller
{
    private const ORDENES = [
        'nombre' => 'nombre_comercial',
        'registro' => 'created_at',
        'productos' => 'productos_count',
        'actividad' => 'ultima_actividad',
    ];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(EstadoCuenta::class)],
            'orden' => ['nullable', Rule::in(array_keys(self::ORDENES))],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $orden = self::ORDENES[$filtros['orden'] ?? 'registro'];
        $direccion = $filtros['direccion'] ?? ($orden === 'nombre_comercial' ? 'asc' : 'desc');
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $negocios = Negocio::query()
            ->conResumen()
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('nombre_comercial', 'like', $like)
                    ->orWhere('responsable', 'like', $like)
                    ->orWhere('email_contacto', 'like', $like)
                    ->orWhere('telefono', 'like', $like));
            })
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => $q->where('estado', $estado))
            ->orderBy($orden, $direccion)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/negocios/Index', [
            'negocios' => NegocioResource::collection($negocios),
            'filtros' => [
                'buscar' => $buscar,
                'estado' => $filtros['estado'] ?? '',
                'orden' => $filtros['orden'] ?? 'registro',
                'direccion' => $direccion,
            ],
            'estados' => EstadoCuenta::opciones(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/negocios/Create', [
            'estados' => EstadoCuenta::opciones(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(GuardarNegocioRequest $request, CrearNegocio $accion): RedirectResponse
    {
        $negocio = $accion->ejecutar($request->datosNegocio(), $request->datosAcceso());

        $this->notificar("Negocio «{$negocio->nombre_comercial}» registrado.");

        return redirect()->route('admin.negocios.show', $negocio);
    }

    public function show(Negocio $negocio, ConsultasEstadisticas $estadisticas): Response
    {
        $negocio = Negocio::query()->conResumen()->findOrFail($negocio->id);
        $filtro = FiltroEstadisticas::ultimosDias(30, $negocio->id);

        return Inertia::render('admin/negocios/Show', [
            'negocio' => new NegocioResource($negocio),
            'usuarios' => UsuarioResource::collection($negocio->usuarios()->with('negocio')->orderBy('name')->get()),
            'productos' => ProductoResource::collection(
                $negocio->productos()->with('negocio')->orderBy('nombre')->get()
            ),
            'estadisticas' => [
                'totales' => $estadisticas->totales($filtro),
                'serie' => $estadisticas->serieDiaria($filtro),
            ],
            'cambios' => CambioDestinoResource::collection(
                CambioDestino::query()->with(['producto', 'usuario'])->where('negocio_id', $negocio->id)
                    ->latest('created_at')->latest('id')->limit(10)->get()
            ),
            'auditoria' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])->where('negocio_id', $negocio->id)
                    ->latest('created_at')->latest('id')->limit(15)->get()
            ),
        ]);
    }

    public function edit(Negocio $negocio): Response
    {
        return Inertia::render('admin/negocios/Edit', [
            'negocio' => new NegocioResource($negocio),
        ]);
    }

    public function update(GuardarNegocioRequest $request, Negocio $negocio, ActualizarNegocio $accion): RedirectResponse
    {
        $accion->ejecutar($negocio, $request->datosNegocio());

        $this->notificar('Datos del negocio actualizados.');

        return redirect()->route('admin.negocios.show', $negocio);
    }

    public function estado(CambiarEstadoRequest $request, Negocio $negocio, CambiarEstadoNegocio $accion): RedirectResponse
    {
        $estado = EstadoCuenta::from((string) $request->validated('estado'));
        $accion->ejecutar($negocio, $estado, $request->validated('motivo'));

        $this->notificar($estado === EstadoCuenta::Suspendido
            ? 'Negocio suspendido: sus enlaces dejaron de redirigir y sus usuarios no pueden entrar.'
            : 'Negocio reactivado.');

        return back();
    }
}
