<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfiguracionRequest;
use App\Models\Producto;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function edit(AjustesPlataforma $ajustes): Response
    {
        return Inertia::render('admin/configuracion/Edit', [
            'ajustes' => $ajustes->todos(),
            'entorno' => [
                'url_publica' => config('dasti.url_publica'),
                'ejemplo_enlace' => Producto::construirUrlPermanente('abcd2345ef'),
                'eventos_en_cola' => (bool) config('dasti.eventos_en_cola'),
                'dias_conservacion_huella' => (int) config('dasti.dias_conservacion_huella'),
                'limite_redirecciones_por_minuto' => (int) config('dasti.limite_redirecciones_por_minuto'),
                'zona_horaria' => config('app.timezone'),
            ],
        ]);
    }

    public function update(ConfiguracionRequest $request, AjustesPlataforma $ajustes, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $cambios = $ajustes->guardar($request->validated());

        if ($cambios['nuevos'] !== []) {
            $auditoria->registrar(
                accion: 'configuracion.actualizada',
                descripcion: 'Actualización de la configuración de la plataforma',
                anteriores: $cambios['anteriores'],
                nuevos: $cambios['nuevos'],
            );
        }

        $this->notificar($cambios['nuevos'] === [] ? 'No hubo cambios que guardar.' : 'Configuración guardada.', $cambios['nuevos'] === [] ? 'info' : 'success');

        return back();
    }
}
