<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;

/**
 * Ajustes de la plataforma editables por el administrador.
 *
 * Cada ajuste tiene un valor por defecto, de modo que la aplicación funciona
 * sin registros previos en la tabla `configuraciones`.
 */
class AjustesPlataforma
{
    private const CACHE_KEY = 'dasti.ajustes';

    /**
     * @var array<string, mixed>
     */
    public const PREDETERMINADOS = [
        // Permite destinos http:// además de https://.
        'permitir_destinos_http' => false,
        // Los clientes pueden descargar el QR de sus productos.
        'clientes_descargan_qr' => true,
        // Los clientes pueden consultar estadísticas de sus productos.
        'clientes_ven_estadisticas' => true,
        // Dominios (y sus subdominios) que no se aceptan como destino.
        'dominios_bloqueados' => ['bit.ly', 'tinyurl.com', 'is.gd', 't.co', 'cutt.ly', 'shorturl.at'],
        // Días que se conservan los eventos individuales de acceso.
        'dias_retencion_eventos' => 395,
    ];

    /**
     * @return array<string, mixed>
     */
    public function todos(): array
    {
        /** @var array<string, mixed> $guardados */
        $guardados = Cache::rememberForever(self::CACHE_KEY, fn () => Configuracion::query()
            ->pluck('valor', 'clave')
            ->all());

        return array_merge(self::PREDETERMINADOS, array_intersect_key($guardados, self::PREDETERMINADOS));
    }

    public function obtener(string $clave): mixed
    {
        return $this->todos()[$clave] ?? null;
    }

    public function booleano(string $clave): bool
    {
        return (bool) $this->obtener($clave);
    }

    /**
     * @return list<string>
     */
    public function dominiosBloqueados(): array
    {
        $dominios = $this->obtener('dominios_bloqueados');

        return is_array($dominios) ? array_values(array_map('strval', $dominios)) : [];
    }

    /**
     * Guarda los ajustes indicados y devuelve los valores anteriores de las
     * claves que realmente cambiaron (útil para auditoría).
     *
     * @param  array<string, mixed>  $valores
     * @return array{anteriores: array<string, mixed>, nuevos: array<string, mixed>}
     */
    public function guardar(array $valores): array
    {
        $actuales = $this->todos();
        $anteriores = [];
        $nuevos = [];

        foreach (array_intersect_key($valores, self::PREDETERMINADOS) as $clave => $valor) {
            if ($actuales[$clave] === $valor) {
                continue;
            }

            Configuracion::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
            $anteriores[$clave] = $actuales[$clave];
            $nuevos[$clave] = $valor;
        }

        Cache::forget(self::CACHE_KEY);

        return ['anteriores' => $anteriores, 'nuevos' => $nuevos];
    }
}
