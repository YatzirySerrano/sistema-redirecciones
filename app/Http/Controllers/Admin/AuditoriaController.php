<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Services\Auditoria\CatalogoAcciones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', Rule::in(array_keys(CatalogoAcciones::ETIQUETAS))],
            'negocio_id' => ['nullable', 'integer'],
            'usuario_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $registros = RegistroAuditoria::query()
            ->with(['usuario', 'negocio'])
            ->when($buscar !== '', fn (Builder $q) => $q->where('descripcion', 'like', '%'.addcslashes($buscar, '%_\\').'%'))
            ->when($filtros['accion'] ?? null, fn (Builder $q, string $v) => $q->where('accion', $v))
            ->when($filtros['negocio_id'] ?? null, fn (Builder $q, int|string $v) => $q->where('negocio_id', (int) $v))
            ->when($filtros['usuario_id'] ?? null, fn (Builder $q, int|string $v) => $q->where('user_id', (int) $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/auditoria/Index', [
            'registros' => RegistroAuditoriaResource::collection($registros),
            'filtros' => [
                'buscar' => $buscar,
                'accion' => $filtros['accion'] ?? '',
                'negocio_id' => isset($filtros['negocio_id']) ? (string) $filtros['negocio_id'] : '',
                'usuario_id' => isset($filtros['usuario_id']) ? (string) $filtros['usuario_id'] : '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
            ],
            'acciones' => CatalogoAcciones::opciones(),
            'negocio_filtro' => isset($filtros['negocio_id'])
                ? Negocio::query()->whereKey((int) $filtros['negocio_id'])->get(['id', 'nombre_comercial'])
                    ->map(fn (Negocio $n) => ['value' => (string) $n->id, 'label' => $n->nombre_comercial])->first()
                : null,
            'usuario_filtro' => isset($filtros['usuario_id'])
                ? User::query()->whereKey((int) $filtros['usuario_id'])->get(['id', 'name', 'email'])
                    ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name, 'descripcion' => $u->email])->first()
                : null,
        ]);
    }
}
