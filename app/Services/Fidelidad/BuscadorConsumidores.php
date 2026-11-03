<?php

namespace App\Services\Fidelidad;

use App\Enums\EstadoRecompensa;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Búsqueda del mostrador (mientras el empleado escribe). SOLO LECTURA:
 * nunca crea movimientos ni modifica nada.
 *
 * - Código de tarjeta (escrito, escaneado o URL /f/{codigo}): coincidencia
 *   exacta y única → se puede preseleccionar.
 * - Teléfono: solo dígitos (se ignoran espacios, guiones y la lada +52);
 *   desde 4 dígitos.
 * - Nombre o alias: cada palabra debe aparecer (en cualquier orden). La
 *   intercalación de la base (utf8mb4_unicode_ci) ignora mayúsculas y
 *   acentos: «jose» encuentra «José».
 * - Relevancia: código exacto > teléfono exacto > correo exacto > nombre
 *   que empieza con lo escrito > palabra que empieza > contiene.
 * - Aislado por negocio; solo consumidores activos; resultados paginados.
 *
 * Los resultados muestran lo mínimo para distinguir homónimos: nombre,
 * alias, teléfono enmascarado (••••1234) y campañas en las que participa.
 */
class BuscadorConsumidores
{
    public const POR_PAGINA = 8;

    public const MIN_CARACTERES = 2;

    public const MIN_DIGITOS = 4;

    /**
     * @return array{resultados: list<array<string, mixed>>, hay_mas: bool, pagina: int, preseleccion: int|null, credencial: array{codigo: string, tipo: string}|null, mensaje: string|null}
     */
    public function buscar(Negocio $negocio, string $consulta, int $pagina = 1): array
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $consulta));
        $pagina = max(1, min(50, $pagina));
        $vacio = ['resultados' => [], 'hay_mas' => false, 'pagina' => $pagina, 'preseleccion' => null, 'credencial' => null, 'mensaje' => null];

        // Código de tarjeta: exacto (también la URL completa del QR/NFC).
        $codigo = strtolower((string) preg_replace('#^.*/f/#', '', $texto));

        if (Codigos::esCodigoValido($codigo)) {
            $credencial = Credencial::query()->with('consumidor')->where('codigo', $codigo)->where('negocio_id', $negocio->id)->first();

            if ($credencial !== null && ! $credencial->estaActiva()) {
                return ['mensaje' => 'Esta tarjeta fue revocada. Pide la credencial vigente o busca al consumidor por nombre.'] + $vacio;
            }

            if ($credencial !== null && $credencial->consumidor->estaActivo()) {
                return [
                    'resultados' => $this->presentar(new Collection([$credencial->consumidor]), [$credencial->consumidor_id => 'codigo']),
                    'preseleccion' => $credencial->consumidor_id,
                    'credencial' => ['codigo' => $credencial->codigo, 'tipo' => $credencial->tipo->etiqueta()],
                ] + $vacio;
            }

            if (str_contains($texto, '/')) {
                return ['mensaje' => 'Esta tarjeta no pertenece a tu negocio o no existe.'] + $vacio;
            }
            // Si no es una tarjeta del negocio se sigue buscando como texto.
        }

        if (mb_strlen($texto) < self::MIN_CARACTERES) {
            return ['mensaje' => 'Escribe al menos '.self::MIN_CARACTERES.' caracteres o escanea la tarjeta.'] + $vacio;
        }

        $digitos = self::digitos($texto);
        $esTelefono = $digitos !== '' && preg_match('/^[\d\s().+-]+$/', $texto) === 1;

        if ($esTelefono && strlen($digitos) < self::MIN_DIGITOS) {
            return ['mensaje' => 'Escribe al menos '.self::MIN_DIGITOS.' dígitos del teléfono.'] + $vacio;
        }

        $palabras = $esTelefono ? [] : array_slice(array_filter(explode(' ', $texto), fn ($p) => $p !== ''), 0, 5);
        $correo = str_contains($texto, '@') ? mb_strtolower($texto) : null;

        $consulta = Consumidor::query()->deNegocio($negocio->id)->where('estado', Consumidor::ACTIVO)
            ->where(function (Builder $q) use ($palabras, $digitos, $esTelefono, $correo) {
                if ($esTelefono) {
                    $q->where('telefono', 'like', '%'.$digitos.'%');
                }

                if ($palabras !== []) {
                    $q->orWhere(function (Builder $nombre) use ($palabras) {
                        foreach ($palabras as $palabra) {
                            $like = '%'.self::escaparLike($palabra).'%';
                            $nombre->where(fn (Builder $w) => $w->where('nombre', 'like', $like)->orWhere('alias', 'like', $like));
                        }
                    });
                }

                if ($correo !== null) {
                    $q->orWhere('email', $correo);
                }
            });

        // Orden por relevancia (los parámetros van enlazados, nunca concatenados).
        $inicio = self::escaparLike($texto).'%';
        $palabraInicio = '% '.self::escaparLike($texto).'%';
        $consulta->orderByRaw(
            'CASE WHEN telefono = ? THEN 0 WHEN email = ? THEN 1 WHEN nombre LIKE ? OR alias LIKE ? THEN 2 WHEN nombre LIKE ? THEN 3 ELSE 4 END',
            [$esTelefono ? $digitos : '', $correo ?? '', $inicio, $inicio, $palabraInicio],
        )->orderBy('nombre')->orderBy('id');

        $encontrados = $consulta->offset(($pagina - 1) * self::POR_PAGINA)->limit(self::POR_PAGINA + 1)->get();
        $hayMas = $encontrados->count() > self::POR_PAGINA;
        $encontrados = $encontrados->take(self::POR_PAGINA);

        $tipos = [];

        foreach ($encontrados as $c) {
            $tipos[$c->id] = match (true) {
                $esTelefono && $c->telefono === $digitos => 'telefono_exacto',
                $esTelefono => 'telefono',
                $correo !== null && $c->email === $correo => 'correo',
                default => 'nombre',
            };
        }

        return [
            'resultados' => $this->presentar($encontrados, $tipos),
            'hay_mas' => $hayMas,
            'mensaje' => $encontrados->isEmpty() ? 'Sin resultados. Revisa lo que escribiste o registra al cliente.' : null,
        ] + $vacio;
    }

    /**
     * Dígitos de un teléfono escrito con espacios, guiones o lada (+52).
     */
    public static function digitos(string $texto): string
    {
        $digitos = (string) preg_replace('/\D/', '', $texto);

        return strlen($digitos) === 12 && str_starts_with($digitos, '52') ? substr($digitos, 2) : $digitos;
    }

    private static function escaparLike(string $texto): string
    {
        return addcslashes($texto, '%_\\');
    }

    /**
     * @param  Collection<int, Consumidor>  $lista
     * @param  array<int, string>  $tipos
     * @return list<array<string, mixed>>
     */
    private function presentar(Collection $lista, array $tipos): array
    {
        if ($lista->isEmpty()) {
            return [];
        }

        $lista->load(['participaciones.campana']);
        $recompensas = Recompensa::query()
            ->whereIn('consumidor_id', $lista->pluck('id'))->where('estado', EstadoRecompensa::Disponible)
            ->where(fn ($q) => $q->whereNull('vence_en')->orWhere('vence_en', '>', now()))
            ->selectRaw('consumidor_id, count(*) as total')->groupBy('consumidor_id')->pluck('total', 'consumidor_id');

        return array_values($lista->map(fn (Consumidor $c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'alias' => $c->alias,
            'telefono' => $c->telefono !== null ? '••••'.substr($c->telefono, -4) : null,
            'coincidencia' => $tipos[$c->id] ?? 'nombre',
            'campanas' => $c->participaciones
                ->filter(fn (Participacion $p) => $p->estado !== Participacion::FUSIONADA)
                ->map(fn (Participacion $p) => $p->campana->nombre)->unique()->values()->take(3)->all(),
            'recompensas' => (int) ($recompensas[$c->id] ?? 0),
        ])->all());
    }
}
