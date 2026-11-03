<?php

namespace App\Jobs;

use App\Models\Paginas\EventoPagina;
use App\Services\Estadisticas\ClasificadorAgente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra una visita, clic o envío de contacto de una Página de enlaces y
 * actualiza los totales diarios. Se ejecuta después de responder (defer) o
 * en la cola. El payload no contiene la IP ni URLs completas: solo el host
 * de procedencia y una huella diaria irreversible (visitas).
 */
class RegistrarEventoPagina implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $paginaId,
        public readonly int $negocioId,
        public readonly string $tipo,
        public readonly ?string $bloqueId,
        public readonly ?string $subpagina,
        public readonly string $ocurridoEn,
        public readonly ?string $agente,
        public readonly ?string $referente,
        public readonly ?string $huellaDiaria,
    ) {}

    public function handle(ClasificadorAgente $clasificador): void
    {
        $clasificacion = $clasificador->clasificar($this->agente);
        $momento = Carbon::parse($this->ocurridoEn);
        $esBot = $clasificacion['es_bot'];
        $dispositivo = $clasificacion['dispositivo']->value;

        DB::transaction(function () use ($momento, $esBot, $dispositivo) {
            EventoPagina::query()->create([
                'pagina_id' => $this->paginaId,
                'negocio_id' => $this->negocioId,
                'tipo' => $this->tipo,
                'bloque_id' => $this->bloqueId,
                'subpagina' => $this->subpagina,
                'ocurrido_en' => $momento,
                'fecha' => $momento->toDateString(),
                'hora' => (int) $momento->format('G'),
                'dispositivo' => $dispositivo,
                'referente' => $this->referente,
                'es_bot' => $esBot,
                'huella_diaria' => $esBot ? null : $this->huellaDiaria,
            ]);

            DB::table('paginas_enlaces_diarios')->upsert(
                [[
                    'pagina_id' => $this->paginaId,
                    'negocio_id' => $this->negocioId,
                    'fecha' => $momento->toDateString(),
                    'tipo' => $this->tipo,
                    'bloque_id' => $this->tipo === EventoPagina::VISITA ? '' : (string) $this->bloqueId,
                    'total' => $esBot ? 0 : 1,
                    'bots' => $esBot ? 1 : 0,
                ]],
                ['pagina_id', 'fecha', 'tipo', 'bloque_id'],
                [
                    'total' => DB::raw('total + '.($esBot ? 0 : 1)),
                    'bots' => DB::raw('bots + '.($esBot ? 1 : 0)),
                ],
            );
        });
    }
}
