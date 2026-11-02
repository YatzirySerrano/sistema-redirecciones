<?php

namespace App\Http\Controllers\Fidelidad;

use App\Actions\Usuarios\AsignarRol;
use App\Actions\Usuarios\CambiarEstadoUsuario;
use App\Actions\Usuarios\CrearUsuario;
use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El propietario administra al personal de SU negocio: alta por invitación,
 * suspensión y rol. Solo puede asignar roles de negocio cuyos permisos sean
 * un subconjunto de los suyos.
 */
class PersonalController extends ControladorFidelidad
{
    public function index(Request $request): Response
    {
        $negocio = $this->negocio($request);
        $actor = $this->usuario($request);

        return Inertia::render('cliente/fidelidad/Personal', [
            ...$this->contexto($request),
            'usuarios' => User::query()->with('roles')->where('negocio_id', $negocio->id)->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'estado' => $u->estado->value,
                    'estado_etiqueta' => $u->estado->etiqueta(),
                    'rol_id' => $u->rolAsignado()?->id,
                    'rol' => $u->rolAsignado()?->name,
                    'invitacion' => $u->estadoInvitacion(),
                    'ultimo_acceso_en' => $u->ultimo_acceso_en?->toIso8601String(),
                    'es_actual' => $u->is($actor),
                ]),
            'roles' => $this->rolesAsignables($actor)->map(fn (Rol $r) => [
                'value' => (string) $r->id, 'label' => $r->name, 'descripcion' => (string) $r->descripcion,
            ])->values(),
        ]);
    }

    public function store(Request $request, CrearUsuario $accion): RedirectResponse
    {
        $actor = $this->usuario($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'rol_id' => ['required', 'integer'],
        ], ['email.unique' => 'Ya existe un usuario con ese correo.'], ['name' => 'nombre', 'email' => 'correo', 'rol_id' => 'rol']);

        $rol = $this->rolPermitido($actor, (int) $datos['rol_id']);
        $usuario = $accion->ejecutar(RolUsuario::Cliente, ['name' => $datos['name'], 'email' => $datos['email'], 'password' => null], $this->negocio($request), $rol);

        $this->notificarAcceso("Acceso creado para {$usuario->email}.", $usuario, true);

        return back();
    }

    public function estado(Request $request, User $usuario, CambiarEstadoUsuario $accion): RedirectResponse
    {
        $this->delNegocio($request, $usuario);
        $this->exigirSubordinado($this->usuario($request), $usuario);
        $datos = $request->validate(['estado' => ['required', Rule::enum(EstadoCuenta::class)], 'motivo' => ['nullable', 'string', 'max:255']]);

        $accion->ejecutar($usuario, EstadoCuenta::from($datos['estado']), $this->usuario($request), $datos['motivo'] ?? null);
        $this->notificar($datos['estado'] === 'suspendido' ? 'Acceso suspendido y sesiones cerradas.' : 'Acceso reactivado.');

        return back();
    }

    public function rol(Request $request, User $usuario, AsignarRol $accion): RedirectResponse
    {
        $this->delNegocio($request, $usuario);
        $actor = $this->usuario($request);
        $datos = $request->validate(['rol_id' => ['required', 'integer']]);

        $this->exigirSubordinado($actor, $usuario);

        $accion->ejecutar($actor, $usuario, $this->rolPermitido($actor, (int) $datos['rol_id']));
        $this->notificar('Rol actualizado.');

        return back();
    }

    /**
     * Nadie modifica a quien tiene permisos que él no tiene.
     */
    private function exigirSubordinado(User $actor, User $usuario): void
    {
        $actual = $usuario->rolAsignado();

        if ($actual !== null && array_diff($actual->nombresPermisos(), $actor->permisosEfectivos()) !== []) {
            throw new AuthorizationException('No puedes modificar a un usuario con permisos que tú no tienes.');
        }
    }

    /**
     * @return Collection<int, Rol>
     */
    private function rolesAsignables(User $actor): Collection
    {
        return Rol::query()->with('permissions')->delAmbito(CatalogoPermisos::AMBITO_NEGOCIO)->orderBy('name')->get()
            ->filter(fn (Rol $r) => array_diff($r->nombresPermisos(), $actor->permisosEfectivos()) === [])
            ->values();
    }

    private function rolPermitido(User $actor, int $rolId): Rol
    {
        $rol = $this->rolesAsignables($actor)->firstWhere('id', $rolId);

        if ($rol === null) {
            throw new AuthorizationException('No puedes asignar ese rol.');
        }

        return $rol;
    }
}
