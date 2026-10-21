<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Actions\Usuarios\CambiarEstadoUsuario;
use App\Actions\Usuarios\CrearUsuario;
use App\Actions\Usuarios\EnviarInvitacion;
use App\Actions\Usuarios\EstablecerContrasenaTemporal;
use App\Enums\EstadoCuenta;
use App\Enums\ResultadoInvitacion;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CambiarEstadoRequest;
use App\Http\Requests\Admin\ContrasenaTemporalRequest;
use App\Http\Requests\Admin\GuardarUsuarioRequest;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Http\Resources\UsuarioResource;
use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Usuarios y accesos: administradores de DASTI y usuarios de clientes.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::enum(RolUsuario::class)],
            'estado' => ['nullable', Rule::enum(EstadoCuenta::class)],
            'negocio_id' => ['nullable', 'integer'],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $usuarios = User::query()
            ->with('negocio')
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filtros['rol'] ?? null, fn (Builder $q, string $rol) => $q->where('rol', $rol))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => $q->where('estado', $estado))
            ->when($filtros['negocio_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('negocio_id', (int) $id))
            ->orderBy('rol')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/usuarios/Index', [
            'usuarios' => UsuarioResource::collection($usuarios),
            'filtros' => [
                'buscar' => $buscar,
                'rol' => $filtros['rol'] ?? '',
                'estado' => $filtros['estado'] ?? '',
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
            ],
            'negocios' => $this->opcionesNegocios(),
            'roles' => RolUsuario::opciones(),
            'estados' => EstadoCuenta::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/usuarios/Create', [
            'negocios' => $this->opcionesNegocios(soloActivos: true),
            'negocio_id' => $request->integer('negocio_id') ?: null,
            'roles' => RolUsuario::opciones(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request, CrearUsuario $accion): RedirectResponse
    {
        $rol = RolUsuario::from((string) $request->validated('rol'));
        $negocio = $rol === RolUsuario::Cliente ? Negocio::query()->findOrFail($request->integer('negocio_id')) : null;

        $usuario = $accion->ejecutar($rol, [
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'password' => $request->validated('metodo') === 'temporal' ? (string) $request->validated('password') : null,
        ], $negocio);

        $this->notificarAcceso("Acceso creado para {$usuario->email}.", $usuario, $request->validated('metodo') !== 'temporal');

        return $negocio !== null
            ? redirect()->route('admin.negocios.show', $negocio)
            : redirect()->route('admin.usuarios.edit', $usuario);
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('admin/usuarios/Edit', [
            'usuario' => new UsuarioResource($usuario->load('negocio')),
            'auditoria' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])
                    ->where(fn (Builder $q) => $q->where('user_id', $usuario->id)
                        ->orWhere(fn (Builder $w) => $w->where('auditable_type', 'usuario')->where('auditable_id', $usuario->id)))
                    ->latest('created_at')->latest('id')->limit(15)->get()
            ),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario, ActualizarUsuario $accion): RedirectResponse
    {
        /** @var array{name: string, email: string} $datos */
        $datos = $request->safe()->only(['name', 'email']);
        $accion->ejecutar($usuario, $datos);

        $this->notificar('Usuario actualizado.');

        return back();
    }

    public function estado(CambiarEstadoRequest $request, User $usuario, CambiarEstadoUsuario $accion): RedirectResponse
    {
        $estado = EstadoCuenta::from((string) $request->validated('estado'));
        $accion->ejecutar($usuario, $estado, $request->user(), $request->validated('motivo'));

        $this->notificar($estado === EstadoCuenta::Suspendido ? 'Acceso suspendido y sesiones cerradas.' : 'Acceso reactivado.');

        return back();
    }

    public function invitacion(User $usuario, EnviarInvitacion $accion): RedirectResponse
    {
        match ($accion->ejecutar($usuario)) {
            ResultadoInvitacion::Enviada => $this->notificar("Se envió un enlace de acceso a {$usuario->email}. Los enlaces anteriores dejaron de funcionar."),
            ResultadoInvitacion::Fallida => $this->notificar("No se pudo enviar el correo a {$usuario->email}. Revisa la configuración de correo e inténtalo de nuevo.", 'error'),
            ResultadoInvitacion::NoPermitida => $this->notificar('No se puede enviar el enlace: el acceso o su negocio están suspendidos.', 'error'),
        };

        return back();
    }

    public function contrasena(ContrasenaTemporalRequest $request, User $usuario, EstablecerContrasenaTemporal $accion): RedirectResponse
    {
        $accion->ejecutar($usuario, (string) $request->validated('password'));
        $this->notificar('Contraseña temporal asignada. El usuario deberá cambiarla al entrar.');

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function opcionesNegocios(bool $soloActivos = false): array
    {
        return array_values(Negocio::query()
            ->when($soloActivos, fn (Builder $q) => $q->where('estado', EstadoCuenta::Activo))
            ->orderBy('nombre_comercial')
            ->get(['id', 'nombre_comercial'])
            ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])
            ->values()
            ->all());
    }
}
