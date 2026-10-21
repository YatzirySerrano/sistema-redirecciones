<?php

namespace App\Console\Commands;

use App\Models\EventoAcceso;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Console\Command;

/**
 * Política de conservación de datos de acceso (programada a diario):
 *
 * 1. Elimina la huella diaria anónima tras DASTI_DIAS_CONSERVACION_HUELLA.
 * 2. Elimina los eventos individuales tras los días de retención
 *    configurados. Los totales diarios (accesos_diarios) se conservan.
 */
class DepurarEventosAcceso extends Command
{
    protected $signature = 'dasti:depurar-eventos';

    protected $description = 'Aplica la política de conservación a los eventos de acceso';

    public function handle(AjustesPlataforma $ajustes): int
    {
        $limiteHuella = now()->subDays(max(1, (int) config('dasti.dias_conservacion_huella')));
        $limiteEventos = now()->subDays(max(30, (int) $ajustes->obtener('dias_retencion_eventos')));

        $huellas = 0;
        EventoAcceso::query()
            ->whereNotNull('huella_diaria')
            ->where('ocurrido_en', '<', $limiteHuella)
            ->chunkById(1000, function ($eventos) use (&$huellas) {
                $huellas += EventoAcceso::query()->whereKey($eventos->modelKeys())->update(['huella_diaria' => null]);
            });

        $eliminados = 0;
        do {
            $lote = EventoAcceso::query()->where('ocurrido_en', '<', $limiteEventos)->limit(5000)->delete();
            $eliminados += $lote;
        } while ($lote > 0);

        $this->info("Huellas anonimizadas: {$huellas}. Eventos eliminados: {$eliminados}.");

        return self::SUCCESS;
    }
}
