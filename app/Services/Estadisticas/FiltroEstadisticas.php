<?php

namespace App\Services\Estadisticas;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Periodo y alcance de una consulta estadística.
 */
final readonly class FiltroEstadisticas
{
    public const DIAS_MAXIMOS = 366;

    public function __construct(
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
        public ?int $negocioId = null,
        public ?int $productoId = null,
        // Para clientes: limitar a productos que el negocio posee hoy, de modo
        // que un producto transferido deje de mostrarse al dueño anterior.
        public bool $soloProductosVigentes = false,
    ) {}

    /**
     * Construye el filtro desde la petición (parámetros desde/hasta en
     * formato Y-m-d). Por defecto: últimos 30 días.
     */
    public static function desdePeticion(Request $request, ?int $negocioId = null, ?int $productoId = null, bool $soloProductosVigentes = false): self
    {
        $hoy = CarbonImmutable::today();
        $hasta = self::fecha($request->query('hasta')) ?? $hoy;
        $desde = self::fecha($request->query('desde')) ?? $hasta->subDays(29);

        if ($hasta->greaterThan($hoy)) {
            $hasta = $hoy;
        }

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        if ($desde->diffInDays($hasta) > self::DIAS_MAXIMOS) {
            $desde = $hasta->subDays(self::DIAS_MAXIMOS);
        }

        return new self($desde->startOfDay(), $hasta->endOfDay(), $negocioId, $productoId, $soloProductosVigentes);
    }

    public static function ultimosDias(int $dias, ?int $negocioId = null, bool $soloProductosVigentes = false): self
    {
        $hoy = CarbonImmutable::today();

        return new self($hoy->subDays($dias - 1)->startOfDay(), $hoy->endOfDay(), $negocioId, null, $soloProductosVigentes);
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public function aArreglo(): array
    {
        return ['desde' => $this->desde->toDateString(), 'hasta' => $this->hasta->toDateString()];
    }

    private static function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valor)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
