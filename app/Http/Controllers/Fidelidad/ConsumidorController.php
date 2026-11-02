<?php

namespace App\Http\Controllers\Fidelidad;

use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Recompensa;
use App\Services\Fidelidad\FichaConsumidor;
use App\Services\Fidelidad\GestorConsumidores;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConsumidorController extends ControladorFidelidad
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $negocio = $this->negocio($request);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $consumidores = Consumidor::query()->deNegocio($negocio->id)
            ->where('estado', '!=', Consumidor::FUSIONADO)
            ->withSum(['participaciones as visitas_totales' => fn (Builder $q) => $q->where('estado', '!=', 'fusionada')], 'visitas_totales')
            ->withCount(['recompensas as recompensas_disponibles' => fn (Builder $q) => $q->where('estado', 'disponible')])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $digitos = preg_replace('/\D/', '', $buscar);
                $q->where(fn (Builder $w) => $w->where('nombre', 'like', $like)->orWhere('alias', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->when(strlen((string) $digitos) >= 4, fn (Builder $t) => $t->orWhere('telefono', 'like', '%'.$digitos.'%')));
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('cliente/fidelidad/consumidores/Index', [
            ...$this->contexto($request),
            'consumidores' => [
                'data' => $consumidores->getCollection()->map(fn (Consumidor $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'alias' => $c->alias,
                    'telefono' => FichaConsumidor::enmascarar($c->telefono),
                    'email' => FichaConsumidor::enmascararCorreo($c->email),
                    'estado' => $c->estado,
                    'visitas_totales' => (int) ($c->getAttribute('visitas_totales') ?? 0),
                    'recompensas_disponibles' => (int) $c->getAttribute('recompensas_disponibles'),
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values(),
                'meta' => [
                    'current_page' => $consumidores->currentPage(),
                    'from' => $consumidores->firstItem(),
                    'to' => $consumidores->lastItem(),
                    'total' => $consumidores->total(),
                    'last_page' => $consumidores->lastPage(),
                    'per_page' => $consumidores->perPage(),
                    'links' => $consumidores->linkCollection()->toArray(),
                ],
            ],
            'filtros' => ['buscar' => $buscar],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('cliente/fidelidad/consumidores/Form', [
            ...$this->contexto($request),
            'consumidor' => null,
            'negocio' => $this->negocio($request)->nombre_comercial,
            'version_aviso' => GestorConsumidores::VERSION_AVISO,
        ]);
    }

    public function store(Request $request, GestorConsumidores $gestor): RedirectResponse
    {
        $negocio = $this->negocio($request);
        $datos = $this->validar($request, $negocio->id, null);
        $consumidor = $gestor->registrar($negocio, $datos, $this->usuario($request));

        $this->notificar($consumidor->email !== null
            ? 'Consumidor registrado. Enviamos un correo para verificar su dirección.'
            : 'Consumidor registrado.');

        return redirect()->route('cliente.fidelidad.consumidores.show', $consumidor);
    }

    public function show(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): Response
    {
        $this->delNegocio($request, $consumidor);

        return Inertia::render('cliente/fidelidad/consumidores/Show', [
            ...$this->contexto($request),
            'consumidor' => FichaConsumidor::resumen($consumidor),
            'fusionado_en' => $consumidor->fusionado_en_id,
            'credenciales' => $consumidor->credenciales()->latest('id')->get()->map(fn (Credencial $c) => [
                'id' => $c->id,
                'tipo' => $c->tipo->value,
                'tipo_etiqueta' => $c->tipo->etiqueta(),
                'codigo' => $c->codigo,
                'url' => $c->urlPublica(),
                'estado' => $c->estado,
                'motivo' => $c->motivo,
                'created_at' => $c->created_at?->toIso8601String(),
                'revocada_en' => $c->revocada_en?->toIso8601String(),
            ]),
            'movimientos' => Movimiento::query()->with('autorizador:id,name')
                ->where('consumidor_id', $consumidor->id)->latest('id')->limit(50)->get()
                ->map(fn (Movimiento $m) => [
                    'id' => $m->id,
                    'tipo' => $m->tipo->value,
                    'tipo_etiqueta' => $m->tipo->etiqueta(),
                    'cantidad' => $m->cantidad,
                    'ciclo' => $m->ciclo,
                    'participacion_id' => $m->participacion_id,
                    'motivo' => $m->motivo,
                    'autorizado_por' => $m->autorizador?->name,
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
            'historial_recompensas' => Recompensa::query()->with('canjeador:id,name')
                ->where('consumidor_id', $consumidor->id)->latest('id')->limit(30)->get()
                ->map(fn (Recompensa $r) => [
                    'id' => $r->id,
                    'descripcion' => $r->descripcion,
                    'estado' => $r->estado->value,
                    'estado_etiqueta' => $r->estado->etiqueta(),
                    'vence_en' => $r->vence_en?->toIso8601String(),
                    'canjeada_en' => $r->canjeada_en?->toIso8601String(),
                    'canjeada_por' => $r->canjeador?->name,
                    'motivo' => $r->motivo,
                ]),
            'duplicados' => $gestor->posiblesDuplicados($consumidor)->map(fn (Consumidor $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'telefono' => FichaConsumidor::enmascarar($c->telefono),
                'email' => FichaConsumidor::enmascararCorreo($c->email),
            ]),
            // El token del enlace digital se muestra una sola vez (al emitirlo).
            'enlace_nuevo' => $request->session()->get('enlace_fidelidad'),
        ]);
    }

    public function edit(Request $request, Consumidor $consumidor): Response
    {
        $this->delNegocio($request, $consumidor);

        return Inertia::render('cliente/fidelidad/consumidores/Form', [
            ...$this->contexto($request),
            'consumidor' => FichaConsumidor::resumen($consumidor, contactoCompleto: true),
            'negocio' => $this->negocio($request)->nombre_comercial,
            'version_aviso' => GestorConsumidores::VERSION_AVISO,
        ]);
    }

    public function update(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        abort_unless($consumidor->estaActivo(), 422, 'La cuenta no está activa.');
        $datos = $this->validar($request, $consumidor->negocio_id, $consumidor->id);
        $gestor->actualizar($consumidor, $datos, $this->usuario($request));

        $this->notificar('Datos del consumidor actualizados.');

        return redirect()->route('cliente.fidelidad.consumidores.show', $consumidor);
    }

    public function verificacion(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        $gestor->enviarVerificacion($consumidor);
        $this->notificar('Enviamos de nuevo el correo de verificación.');

        return back();
    }

    public function fusion(Request $request, Consumidor $consumidor, GestorConsumidores $gestor): RedirectResponse
    {
        $this->delNegocio($request, $consumidor);
        $datos = $request->validate([
            'destino_id' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['destino_id' => 'cuenta que se conserva', 'motivo' => 'motivo']);

        $destino = $this->delNegocio($request, Consumidor::query()->findOrFail((int) $datos['destino_id']));
        $gestor->fusionar($consumidor, $destino, $datos['motivo'], $this->usuario($request));
        $this->notificar('Cuentas fusionadas. El historial se conservó en la cuenta elegida.');

        return redirect()->route('cliente.fidelidad.consumidores.show', $destino);
    }

    /**
     * Nombre obligatorio y al menos un contacto (teléfono de 10 dígitos o
     * correo), únicos dentro del negocio. En el alta se exige el
     * consentimiento informado.
     *
     * @return array{nombre: string, alias: string|null, telefono: string|null, email: string|null}
     */
    private function validar(Request $request, int $negocioId, ?int $ignorar): array
    {
        $telefono = preg_replace('/\D/', '', (string) $request->input('telefono'));

        if (strlen((string) $telefono) === 12 && str_starts_with((string) $telefono, '52')) {
            $telefono = substr((string) $telefono, 2);
        }

        $request->merge([
            'nombre' => is_string($request->input('nombre')) ? preg_replace('/\s+/', ' ', trim($request->input('nombre'))) : $request->input('nombre'),
            'alias' => $request->filled('alias') ? trim((string) $request->input('alias')) : null,
            'telefono' => $telefono !== '' ? $telefono : null,
            'email' => $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null,
        ]);

        $unico = fn (string $campo) => Rule::unique('fidelidad_consumidores', $campo)
            ->where('negocio_id', $negocioId)->ignore($ignorar);

        /** @var array{nombre: string, alias: string|null, telefono: string|null, email: string|null} $validados */
        $validados = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'alias' => ['nullable', 'string', 'max:60'],
            'telefono' => ['nullable', 'required_without:email', 'digits:10', $unico('telefono')],
            'email' => ['nullable', 'required_without:telefono', 'email:rfc', 'max:255', $unico('email')],
            'consentimiento' => $ignorar === null ? ['accepted'] : ['nullable'],
        ], [
            'telefono.required_without' => 'Registra al menos un medio de contacto: teléfono o correo.',
            'email.required_without' => 'Registra al menos un medio de contacto: teléfono o correo.',
            'telefono.digits' => 'El teléfono debe tener 10 dígitos.',
            'telefono.unique' => 'Ya hay un consumidor con ese teléfono en tu negocio. Búscalo en lugar de crear otra cuenta.',
            'email.unique' => 'Ya hay un consumidor con ese correo en tu negocio. Búscalo en lugar de crear otra cuenta.',
            'consentimiento.accepted' => 'El consumidor debe aceptar el aviso de privacidad para registrarse.',
        ], ['nombre' => 'nombre', 'telefono' => 'teléfono', 'email' => 'correo']);

        return [
            'nombre' => $validados['nombre'],
            'alias' => $validados['alias'] ?? null,
            'telefono' => $validados['telefono'] ?? null,
            'email' => $validados['email'] ?? null,
        ];
    }
}
