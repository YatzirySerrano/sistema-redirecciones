<?php

namespace App\Console\Commands;

use App\Services\Pagos\PasarelaPago;
use App\Services\Pagos\ServicioPagos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Respaldo de las notificaciones: consulta a Openpay los cobros que siguen
 * abiertos (p. ej. si una notificación no llegó) y aplica su estado real;
 * los que superan la vigencia configurada se dan por caducados. Nunca crea
 * cargos ni cobra.
 */
#[Signature('dasti:conciliar-pagos')]
#[Description('Consulta a Openpay los pagos pendientes y aplica su estado confirmado')]
class ConciliarPagos extends Command
{
    public function handle(ServicioPagos $pagos, PasarelaPago $pasarela): int
    {
        if (! $pasarela->configurada()) {
            $this->warn('Openpay no está configurado; no hay nada que conciliar.');

            return self::SUCCESS;
        }

        $total = $pagos->conciliarPendientes();
        $this->info("Intentos de cobro consultados: {$total}.");

        return self::SUCCESS;
    }
}
