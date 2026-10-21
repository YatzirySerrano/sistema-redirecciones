<?php

namespace App\Jobs;

use App\Enums\ResultadoAcceso;
use App\Models\EventoAcceso;
use App\Models\Producto;
use App\Services\Estadisticas\ClasificadorAgente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persiste un acceso a un enlace permanente y actualiza los agregados.
 *
 * Se ejecuta después de enviar la redirección al visitante (defer) o en la
 * cola si DASTI_EVENTOS_EN_COLA=true, de modo que nunca retrasa la respuesta.
 * El payload no contiene la IP: solo una huella diaria irreversible.
 */
class RegistrarEventoAcceso implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $productoId,
        public readonly int $negocioId,
        public readonly ResultadoAcceso $resultado,
        public readonly string $ocurridoEn,
        public readonly ?string $agente,
        public readonly ?string $huellaDiaria,
    ) {}

    public function handle(ClasificadorAgente $clasificador): void
    {
        $clasificacion = $clasificador->clasificar($this->agente);
        $momento = Carbon::parse($this->ocurridoEn);

        DB::transaction(function () use ($clasificacion, $momento) {
            EventoAcceso::query()->create([
                'producto_id' => $this->productoId,
                'negocio_id' => $this->negocioId,
                'ocurrido_en' => $momento,
                'resultado' => $this->resultado,
                'dispositivo' => $clasificacion['dispositivo'],
                'navegador' => $clasificacion['navegador'],
                'sistema_operativo' => $clasificacion['sistema_operativo'],
                'es_bot' => $clasificacion['es_bot'],
                'huella_diaria' => $this->huellaDiaria,
            ]);

            // Solo los accesos redirigidos cuentan en los agregados.
            if ($this->resultado !== ResultadoAcceso::Redirigido) {
                return;
            }

            $esBot = $clasificacion['es_bot'];

            DB::table('accesos_diarios')->upsert(
                [[
                    'producto_id' => $this->productoId,
                    'negocio_id' => $this->negocioId,
                    'fecha' => $momento->toDateString(),
                    'total' => $esBot ? 0 : 1,
                    'bots' => $esBot ? 1 : 0,
                ]],
                ['producto_id', 'negocio_id', 'fecha'],
                [
                    'total' => DB::raw('total + '.($esBot ? 0 : 1)),
                    'bots' => DB::raw('bots + '.($esBot ? 1 : 0)),
                ],
            );

            if ($esBot) {
                return;
            }

            // Los contadores del producto pertenecen al periodo de propiedad
            // vigente: si se transfirió mientras el evento esperaba, no se tocan.
            $vigente = Producto::query()->whereKey($this->productoId)->where('negocio_id', $this->negocioId);

            $vigente->clone()->update(['total_accesos' => DB::raw('total_accesos + 1')]);

            // Con varios procesos de cola los eventos pueden llegar en
            // desorden: el último acceso nunca retrocede.
            $vigente->clone()
                ->where(fn ($q) => $q->whereNull('ultimo_acceso_en')->orWhere('ultimo_acceso_en', '<', $momento))
                ->update(['ultimo_acceso_en' => $momento]);
        });
    }
}
