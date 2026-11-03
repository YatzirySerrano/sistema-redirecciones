<?php

namespace App\Console\Commands;

use App\Models\Paginas\EventoPagina;
use Illuminate\Console\Command;

/**
 * Conservación de datos de las Páginas de enlaces (programada a diario):
 *
 * 1. Elimina la huella diaria anónima tras DASTI_DIAS_CONSERVACION_HUELLA.
 * 2. Elimina los eventos individuales tras DASTI_PAGINAS_DIAS_RETENCION.
 *    Los totales diarios (paginas_enlaces_diarios) se conservan.
 */
class DepurarEventosPagina extends Command
{
    protected $signature = 'dasti:depurar-paginas';

    protected $description = 'Aplica la política de conservación a los eventos de las Páginas de enlaces';

    public function handle(): int
    {
        $limiteHuella = now()->subDays(max(1, (int) config('dasti.dias_conservacion_huella', 30)));
        $limiteEventos = now()->subDays(max(30, (int) config('dasti.paginas.dias_retencion_eventos', 400)));

        $huellas = 0;

        do {
            $lote = EventoPagina::query()->whereNotNull('huella_diaria')->where('ocurrido_en', '<', $limiteHuella)
                ->limit(5000)->update(['huella_diaria' => null]);
            $huellas += $lote;
        } while ($lote > 0);

        $eliminados = 0;

        do {
            $lote = EventoPagina::query()->where('ocurrido_en', '<', $limiteEventos)->limit(5000)->delete();
            $eliminados += $lote;
        } while ($lote > 0);

        $this->info("Huellas anonimizadas: {$huellas}. Eventos eliminados: {$eliminados}.");

        return self::SUCCESS;
    }
}
