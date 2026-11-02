<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Actions\Usuarios\AsignarRol;
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
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\GuardianRoles;
use Illuminate\Auth\Access\AuthorizationException;
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
    public function __construct(private readonly GuardianRoles $guardian) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::enum(RolUsuario::class)],
            'rol_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::enum(EstadoCuenta::class)],
            'negocio_id' => ['nullable', 'integer'],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $usuarios = User::query()
            ->with(['negocio', 'roles'])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filtros['rol'] ?? null, fn (Builder $q, string $rol) => $q->where('rol', $rol))
            ->when($filtros['rol_id'] ?? null, fn (Builder $q, int|string $id) => $q->whereHas('roles', fn (Builder $r) => $r->whereKey((int) $id)))
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
                'rol_id' => isset($filtros['rol_id']) ? (string) $filtros['rol_id'] : '',
                'estado' => $filtros['estado'] ?? '',
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
            ],
            'negocio_filtro' => $this->opcionNegocio($filtros['negocio_id'] ?? null),
            'tipos' => RolUsuario::opciones(),
            'roles' => Rol::query()->orderByRaw('clave IS NULL')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Rol $r) => ['value' => (string) $r->id, 'label' => $r->name])->values()->all(),
            'estados' => EstadoCuenta::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $negocioId = $request->integer('negocio_id') ?: null;

        return Inertia::render('admin/usuarios/Create', [
            'negocio_id' => $negocioId,
            'negocio_inicial' => $this->opcionNegocio($negocioId),
            'tipos' => RolUsuario::opciones(),
            'roles' => [
                RolUsuario::Admin->value => $this->opcionesRoles($actor, CatalogoPermisos::AMBITO_PLATAFORMA),
                RolUsuario::Cliente->value => $this->opcionesRoles($actor, CatalogoPermisos::AMBITO_NEGOCIO),
            ],
            'roles_predeterminados' => [
                RolUsuario::Admin->value => (string) ($this->guardian->rolPredeterminado(CatalogoPermisos::AMBITO_PLATAFORMA)->id ?? ''),
                RolUsuario::Cliente->value => (string) ($this->guardian->rolPredeterminado(CatalogoPermisos::AMBITO_NEGOCIO)->id ?? ''),
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request, CrearUsuario $accion): RedirectResponse
    {
        $rol = RolUsuario::from((string) $request->validated('rol'));
        $negocio = $rol === RolUsuario::Cliente ? Negocio::query()->findOrFail($request->integer('negocio_id')) : null;

        // Sin rol explícito se usa el predeterminado del tipo de cuenta; en
        // ambos casos debe ser un rol que el actor pueda asignar.
        $rolPermisos = $request->filled('rol_id')
            ? Rol::query()->findOrFail($request->integer('rol_id'))
            : $this->guardian->rolPredeterminado($rol === RolUsuario::Cliente ? CatalogoPermisos::AMBITO_NEGOCIO : CatalogoPermisos::AMBITO_PLATAFORMA);

        if ($rolPermisos === null || ! $this->guardian->puedeAsignar($request->user(), $rolPermisos)) {
            throw new AuthorizationException('No puedes asignar un rol con permisos que tú no tienes.');
        }

        $usuario = $accion->ejecutar($rol, [
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'password' => $request->validated('metodo') === 'temporal' ? (string) $request->validated('password') : null,
        ], $negocio, $rolPermisos);

        $this->notificarAcceso("Acceso creado para {$usuario->email}.", $usuario, $request->validated('metodo') !== 'temporal');

        return $negocio !== null
            ? redirect()->route('admin.negocios.show', $negocio)
            : redirect()->route('admin.usuarios.edit', $usuario);
    }

    public function edit(Request $request, User $usuario): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $usuario->load(['negocio', 'roles.permissions']);
        $gestionable = $this->guardian->puedeGestionar($actor, $usuario) && ! $usuario->is($actor);

        return Inertia::render('admin/usuarios/Edit', [
            'usuario' => new UsuarioResource($usuario),
            'rol' => [
                'actual' => $usuario->rolAsignado() === null ? null : [
                    'id' => $usuario->rolAsignado()->id,
                    'nombre' => $usuario->rolAsignado()->name,
                    'descripcion' => $usuario->rolAsignado()->descripcion,
                ],
                'permisos_efectivos' => array_map(
                    fn (string $p) => ['nombre' => $p, 'etiqueta' => CatalogoPermisos::etiquetaCompleta($p)],
                    $usuario->permisosEfectivos(),
                ),
                'opciones' => $gestionable && $actor->tienePermiso('roles.asignar')
                    ? $this->opcionesRoles($actor, $usuario->ambito())
                    : [],
                'puede_cambiar' => $gestionable && $actor->tienePermiso('roles.asignar'),
            ],
            'puede' => [
                'editar' => $actor->tienePermiso('usuarios.editar') && ($gestionable || $usuario->is($actor)),
                'gestionar_accesos' => $actor->tienePermiso('usuarios.gestionar_accesos') && $gestionable,
            ],
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
        $this->exigirGestionable($request, $usuario, permitirPropio: true);

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

    public function invitacion(Request $request, User $usuario, EnviarInvitacion $accion): RedirectResponse
    {
        $this->exigirGestionable($request, $usuario);

        match ($accion->ejecutar($usuario)) {
            ResultadoInvitacion::Enviada => $this->notificar("Se envió un enlace de acceso a {$usuario->email}. Los enlaces anteriores dejaron de funcionar."),
            ResultadoInvitacion::Fallida => $this->notificar("No se pudo enviar el correo a {$usuario->email}. Revisa la configuración de correo e inténtalo de nuevo.", 'error'),
            ResultadoInvitacion::NoPermitida => $this->notificar('No se puede enviar el enlace: el acceso o su negocio están suspendidos.', 'error'),
        };

        return back();
    }

    public function contrasena(ContrasenaTemporalRequest $request, User $usuario, EstablecerContrasenaTemporal $accion): RedirectResponse
    {
        $this->exigirGestionable($request, $usuario);

        $accion->ejecutar($usuario, (string) $request->validated('password'));
        $this->notificar('Contraseña temporal asignada. El usuario deberá cambiarla al entrar.');

        return back();
    }

    public function rol(Request $request, User $usuario, AsignarRol $accion): RedirectResponse
    {
        $datos = $request->validate(['rol_id' => ['required', 'integer', Rule::exists('roles', 'id')]], [], ['rol_id' => 'rol']);
        $rol = Rol::query()->findOrFail((int) $datos['rol_id']);

        $cambio = $accion->ejecutar($request->user(), $usuario, $rol);
        $this->notificar($cambio ? "Rol actualizado: ahora es «{$rol->name}»." : 'El usuario ya tenía ese rol.', $cambio ? 'success' : 'info');

        return back();
    }

    /**
     * Personal con menos permisos no modifica cuentas con más permisos (ni
     * restablece su contraseña, que sería una vía de escalamiento).
     */
    private function exigirGestionable(Request $request, User $usuario, bool $permitirPropio = false): void
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($permitirPropio && $usuario->is($actor)) {
            return;
        }

        if (! $this->guardian->puedeGestionar($actor, $usuario)) {
            throw new AuthorizationException('No puedes modificar el acceso de un usuario con permisos que tú no tienes.');
        }
    }

    /**
     * @return list<array{value: string, label: string, descripcion: string}>
     */
    private function opcionesRoles(User $actor, string $ambito): array
    {
        return array_values($this->guardian->rolesAsignables($actor, $ambito)
            ->map(fn (Rol $r) => ['value' => (string) $r->id, 'label' => $r->name, 'descripcion' => (string) $r->descripcion])
            ->all());
    }

    /**
     * Opción ya elegida de un selector remoto de negocios.
     *
     * @return array{value: string, label: string}|null
     */
    private function opcionNegocio(int|string|null $id): ?array
    {
        $negocio = $id ? Negocio::query()->find((int) $id, ['id', 'nombre_comercial']) : null;

        return $negocio === null ? null : ['value' => (string) $negocio->id, 'label' => $negocio->nombre_comercial];
    }
}
