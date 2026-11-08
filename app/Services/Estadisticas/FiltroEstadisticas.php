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
        // Canal del enlace: nfc, qr o combinado (enlace compartido entre QR y
        // NFC, cuyo canal no es distinguible). null = todos.
        public ?string $canal = null,
        // Producto físico: incluye todos sus enlaces (frente NFC y reverso QR
        // de un combinado con destinos independientes).
        public ?int $productoFisicoId = null,
    ) {}

    /** Canales admitidos en el filtro (COALESCE(medio, tipo) del enlace). */
    public const CANALES = ['nfc', 'qr', 'combinado'];

    /**
     * Construye el filtro desde la petición (parámetros desde/hasta en
     * formato Y-m-d). Por defecto: últimos 30 días.
     */
    public static function desdePeticion(Request $request, ?int $negocioId = null, ?int $productoId = null, bool $soloProductosVigentes = false, ?string $canal = null, ?int $productoFisicoId = null): self
    {
        $canal = in_array($canal, self::CANALES, true) ? $canal : null;

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

        return new self($desde->startOfDay(), $hasta->endOfDay(), $negocioId, $productoId, $soloProductosVigentes, $canal, $productoFisicoId);
    }

    /** Días naturales que abarca el periodo (incluidos ambos extremos). */
    public function dias(): int
    {
        return (int) $this->desde->startOfDay()->diffInDays($this->hasta->startOfDay()) + 1;
    }

    /**
     * Periodo inmediatamente anterior con la misma duración y el mismo
     * alcance (p. ej. 1–30 de junio frente a 2–31 de mayo).
     */
    public function anterior(): self
    {
        $dias = $this->dias();
        $hasta = $this->desde->subDay()->endOfDay();

        return new self($hasta->subDays($dias - 1)->startOfDay(), $hasta, $this->negocioId, $this->productoId, $this->soloProductosVigentes, $this->canal, $this->productoFisicoId);
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
