<?php

namespace App\Console\Commands;

use App\Services\Servicios\GestorContrataciones;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marca como vencidos los periodos contratados cuya vigencia terminó. El
 * acceso ya se calcula por fecha; esto deja el estado y la auditoría al día.
 * Nunca cobra ni renueva.
 */
#[Signature('dasti:vencer-contrataciones')]
#[Description('Marca como vencidas las contrataciones cuya vigencia terminó')]
class VencerContrataciones extends Command
{
    public function handle(GestorContrataciones $gestor): int
    {
        $total = $gestor->vencerPeriodos();
        $this->info("Contrataciones vencidas: {$total}.");

        return self::SUCCESS;
    }
}
