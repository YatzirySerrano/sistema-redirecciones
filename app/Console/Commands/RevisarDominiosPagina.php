<?php

namespace App\Console\Commands;

use App\Models\Paginas\DominioPagina;
use App\Services\Paginas\Dominios\GestorDominios;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revisa periódicamente los dominios personalizados (DNS y HTTPS): activa
 * los pendientes que ya se configuraron y detecta los que dejaron de
 * apuntar a DASTI. Programada cada 10 minutos; cada dominio define su
 * próxima revisión (más frecuente mientras está pendiente).
 */
class RevisarDominiosPagina extends Command
{
    protected $signature = 'dasti:revisar-dominios {--todos : Revisa todos sin esperar a su próxima revisión}';

    protected $description = 'Verifica DNS y HTTPS de los dominios personalizados de las Páginas de enlaces';

    public function handle(GestorDominios $dominios): int
    {
        $revisados = 0;

        DominioPagina::query()
            ->when(! $this->option('todos'), fn ($q) => $q->where(fn ($q) => $q->whereNull('proxima_revision_en')->orWhere('proxima_revision_en', '<=', now())))
            ->orderBy('proxima_revision_en')
            ->limit(200)
            ->get()
            ->each(function (DominioPagina $dominio) use ($dominios, &$revisados) {
                try {
                    $dominios->verificar($dominio);
                    $revisados++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Dominios revisados: {$revisados}.");

        return self::SUCCESS;
    }
}
