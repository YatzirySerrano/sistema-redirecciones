<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Roles\EliminarRol;
use App\Actions\Roles\GuardarRol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarRolRequest;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Http\Resources\RolResource;
use App\Http\Resources\UsuarioResource;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\GuardianRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo «Roles y permisos».
 */
class RolController extends Controller
{
    public function __construct(private readonly GuardianRoles $guardian) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'ambito' => ['nullable', Rule::in([CatalogoPermisos::AMBITO_PLATAFORMA, CatalogoPermisos::AMBITO_NEGOCIO])],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $roles = Rol::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions')
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('descripcion', 'like', $like));
            })
            ->when($filtros['ambito'] ?? null, fn (Builder $q, string $ambito) => $q->where('ambito', $ambito))
            ->orderByRaw('clave IS NULL')
            ->orderBy('ambito', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/roles/Index', [
            'roles' => RolResource::collection($roles),
            'filtros' => ['buscar' => $buscar, 'ambito' => $filtros['ambito'] ?? ''],
            'ambitos' => $this->ambitos(),
            'total_permisos' => [
                CatalogoPermisos::AMBITO_PLATAFORMA => count(CatalogoPermisos::delAmbito(CatalogoPermisos::AMBITO_PLATAFORMA)),
                CatalogoPermisos::AMBITO_NEGOCIO => count(CatalogoPermisos::delAmbito(CatalogoPermisos::AMBITO_NEGOCIO)),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/roles/Form', [
            'rol' => null,
            ...$this->datosFormulario($request),
        ]);
    }

    public function store(GuardarRolRequest $request, GuardarRol $accion): RedirectResponse
    {
        /** @var array{name: string, descripcion?: string|null, ambito: string, permisos?: list<string>} $datos */
        $datos = $request->validated();
        $rol = $accion->crear($request->user(), $datos);
        $this->notificar("Rol «{$rol->name}» creado.");

        return redirect()->route('admin.roles.show', $rol);
    }

    public function show(Rol $rol): Response
    {
        $rol->load('permissions')->loadCount(['users', 'permissions']);

        $usuarios = User::query()
            ->with('negocio')
            ->whereHas('roles', fn (Builder $q) => $q->whereKey($rol->id))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return Inertia::render('admin/roles/Show', [
            'rol' => new RolResource($rol),
            'matriz' => CatalogoPermisos::matriz(),
            'usuarios' => UsuarioResource::collection($usuarios),
            'historial' => RegistroAuditoriaResource::collection(
                RegistroAuditoria::query()->with(['usuario', 'negocio'])
                    ->where('auditable_type', 'rol')->where('auditable_id', $rol->id)
                    ->latest('created_at')->latest('id')->limit(30)->get()
            ),
            'puede' => $this->acciones($rol),
        ]);
    }

    public function edit(Request $request, Rol $rol): Response
    {
        $rol->load('permissions')->loadCount('users');

        return Inertia::render('admin/roles/Form', [
            'rol' => new RolResource($rol),
            ...$this->datosFormulario($request),
        ]);
    }

    public function update(GuardarRolRequest $request, Rol $rol, GuardarRol $accion): RedirectResponse
    {
        /** @var array{name: string, descripcion?: string|null, permisos?: list<string>} $datos */
        $datos = $request->validated();
        $accion->actualizar($request->user(), $rol, $datos);
        $this->notificar('Rol actualizado.');

        return redirect()->route('admin.roles.show', $rol);
    }

    public function destroy(Request $request, Rol $rol, EliminarRol $accion): RedirectResponse
    {
        $accion->ejecutar($request->user(), $rol);
        $this->notificar('Rol eliminado.');

        return redirect()->route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        /** @var User $actor */
        $actor = $request->user();

        return [
            'matriz' => CatalogoPermisos::matriz(),
            'ambitos' => $this->ambitos(),
            // Permisos de plataforma que el actor puede otorgar o retirar; los
            // demás se muestran bloqueados (el servidor vuelve a validarlo).
            'otorgables' => $actor->esSuperadministrador()
                ? CatalogoPermisos::todos()
                : $actor->permisosEfectivos(),
            'puede_administrar_permisos' => $actor->tienePermiso('roles.administrar_permisos'),
            'es_superadministrador' => $actor->esSuperadministrador(),
            'rol_propio_id' => $actor->rolAsignado()?->id,
        ];
    }

    /**
     * @return array{editar: bool, eliminar: bool}
     */
    private function acciones(Rol $rol): array
    {
        /** @var User $actor */
        $actor = request()->user();
        $editar = $actor->tienePermiso('roles.editar') && (! $rol->esSuperadministrador() || $actor->esSuperadministrador());

        return [
            'editar' => $editar,
            'eliminar' => $editar && ! $rol->esDelSistema() && (int) $rol->users_count === 0 && $this->guardian->puedeAsignar($actor, $rol),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function ambitos(): array
    {
        return [
            ['value' => CatalogoPermisos::AMBITO_PLATAFORMA, 'label' => CatalogoPermisos::etiquetaAmbito(CatalogoPermisos::AMBITO_PLATAFORMA)],
            ['value' => CatalogoPermisos::AMBITO_NEGOCIO, 'label' => CatalogoPermisos::etiquetaAmbito(CatalogoPermisos::AMBITO_NEGOCIO)],
        ];
    }
}
