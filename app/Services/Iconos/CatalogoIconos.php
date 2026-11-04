<?php

namespace App\Services\Iconos;

use App\Services\Fidelidad\Diseno\DocumentoDiseno;
use App\Services\Paginas\CatalogoPagina;
use RuntimeException;

/**
 * Biblioteca de íconos de los editores (Página de enlaces y Fidelidad).
 *
 * Lee resources/iconos/catalogo.json (generado y versionado con
 * scripts/generar-catalogo-iconos.mjs; trazos de Lucide, ISC, más íconos
 * propios). No necesita Node en producción. El archivo se lee una sola vez
 * por proceso.
 *
 * Valores válidos de un ícono en los documentos:
 * - `i:<nombre>`: ícono del catálogo (nombre kebab).
 * - `red:<red>`: glifo de una red social (CatalogoPagina::REDES).
 * - Claves heredadas de cada módulo (CatalogoPagina::ICONOS y
 *   DocumentoDiseno::ICONOS), para no romper documentos guardados.
 */
class CatalogoIconos
{
    public const PREFIJO = 'i:';

    public const RUTA = 'resources/iconos/catalogo.json';

    /** Máximo de íconos distintos que se incrustan en una página pública. */
    public const MAX_INCRUSTADOS = 300;

    private const PATRON_NOMBRE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array{categorias: list<array{clave: string, nombre: string, iconos: list<string>}>, redes: array<string, array{nombre: string, palabras: string}>, iconos: array<string, array{nombre: string, palabras: string, auto?: bool, propio?: bool, nodos: list<array{0: string, 1: array<string, string|int|float>}>}>}|null */
    private static ?array $datos = null;

    /**
     * ¿Es un valor de ícono admitido (biblioteca, red o clave heredada)?
     */
    public function existe(string $valor): bool
    {
        if ($valor === '' || strlen($valor) > 64) {
            return false;
        }

        if (str_starts_with($valor, self::PREFIJO)) {
            $nombre = substr($valor, strlen(self::PREFIJO));

            return preg_match(self::PATRON_NOMBRE, $nombre) === 1 && isset($this->datos()['iconos'][$nombre]);
        }

        if (str_starts_with($valor, 'red:')) {
            return array_key_exists(substr($valor, 4), CatalogoPagina::REDES);
        }

        return in_array($valor, CatalogoPagina::ICONOS, true) || in_array($valor, DocumentoDiseno::ICONOS, true);
    }

    /**
     * Trazos SVG de los íconos de la biblioteca indicados (solo `i:`; las
     * claves heredadas y las redes ya vienen en el código de la página).
     * Los valores desconocidos se omiten.
     *
     * @param  array<mixed>  $valores
     * @return array<string, list<array{0: string, 1: array<string, string|int|float>}>> valor => nodos
     */
    public function nodos(array $valores): array
    {
        $mapa = [];
        $iconos = $this->datos()['iconos'];

        foreach ($valores as $valor) {
            if (count($mapa) >= self::MAX_INCRUSTADOS) {
                break;
            }

            if (! is_string($valor) || ! str_starts_with($valor, self::PREFIJO) || ! $this->existe($valor)) {
                continue;
            }

            $mapa[$valor] = $iconos[substr($valor, strlen(self::PREFIJO))]['nodos'];
        }

        ksort($mapa);

        return $mapa;
    }

    /**
     * Valores de ícono de la biblioteca (`i:`) presentes en un documento:
     * cualquier campo cuya clave sea `icono` o contenga `icono` como
     * palabra (`icono_pendiente`, `sello_icono`…).
     *
     * @param  array<mixed>  $documento
     * @return list<string>
     */
    public static function usados(array $documento): array
    {
        $usados = [];

        array_walk_recursive($documento, function (mixed $valor, int|string $clave) use (&$usados) {
            if (is_string($clave) && is_string($valor) && str_starts_with($valor, self::PREFIJO)
                && preg_match('/(^|_)icono(_|$)/', $clave) === 1) {
                $usados[$valor] = true;
            }
        });

        return array_keys($usados);
    }

    /**
     * Mapa listo para incrustar en una página pública: solo los trazos de
     * los íconos usados por los documentos.
     *
     * @param  list<array<mixed>|null>  $documentos
     * @return array<string, list<array{0: string, 1: array<string, string|int|float>}>>
     */
    public function paraDocumentos(array $documentos): array
    {
        $valores = [];

        foreach ($documentos as $documento) {
            if (is_array($documento)) {
                $valores = [...$valores, ...self::usados($documento)];
            }
        }

        return $this->nodos(array_values(array_unique($valores)));
    }

    /**
     * Búsqueda (misma lógica que el selector del editor): ignora acentos,
     * mayúsculas y palabras vacías; cada palabra debe aparecer en el nombre
     * o en las palabras clave (admite plurales simples).
     *
     * @return list<string> valores (`i:` o `red:`) ordenados por relevancia
     */
    public function buscar(string $consulta, int $limite = 60): array
    {
        $palabras = self::palabrasConsulta($consulta);

        if ($palabras === []) {
            return [];
        }

        $resultados = [];
        $candidatos = [];

        foreach ($this->datos()['iconos'] as $nombre => $icono) {
            $candidatos['i:'.$nombre] = ['nombre' => $icono['nombre'], 'palabras' => $icono['palabras'], 'auto' => $icono['auto'] ?? false];
        }

        foreach ($this->datos()['redes'] as $red => $datos) {
            $candidatos['red:'.$red] = ['nombre' => $datos['nombre'], 'palabras' => $datos['palabras'], 'auto' => false];
        }

        foreach ($candidatos as $valor => $icono) {
            // Con espacio inicial: se compara por inicio de palabra
            // («agua» no encuentra «paraguas»).
            $titulo = ' '.self::normalizar($icono['nombre']);
            $texto = $titulo.' '.$icono['palabras'];
            $puntos = 0;

            foreach ($palabras as $palabra) {
                $variantes = self::variantes($palabra);
                $encontrada = false;

                foreach ($variantes as $v) {
                    if (str_contains($texto, ' '.$v)) {
                        $encontrada = true;
                        $puntos += str_contains($titulo, ' '.$v) ? (str_starts_with($titulo, ' '.$v) ? 4 : 3) : 1;
                        break;
                    }
                }

                if (! $encontrada) {
                    continue 2;
                }
            }

            $resultados[$valor] = $puntos - ($icono['auto'] ? 2 : 0);
        }

        arsort($resultados);

        return array_slice(array_keys($resultados), 0, $limite);
    }

    /**
     * @return list<array{clave: string, nombre: string, iconos: list<string>}>
     */
    public function categorias(): array
    {
        return $this->datos()['categorias'];
    }

    public function total(): int
    {
        return count($this->datos()['iconos']);
    }

    /** Solo para pruebas: vuelve a leer el archivo. */
    public static function olvidar(): void
    {
        self::$datos = null;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);

        return (string) preg_replace('/\s+/', ' ', $texto);
    }

    /**
     * @return list<string>
     */
    private static function palabrasConsulta(string $consulta): array
    {
        $vacias = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'para', 'con', 'en', 'a', 'un', 'una', 'al', 'o'];
        $partes = preg_split('/[^a-z0-9]+/', self::normalizar(mb_substr($consulta, 0, 80))) ?: [];

        return array_values(array_filter($partes, fn (string $p) => $p !== '' && ! in_array($p, $vacias, true)));
    }

    /**
     * @return list<string>
     */
    private static function variantes(string $palabra): array
    {
        $variantes = [$palabra];

        if (strlen($palabra) > 4 && str_ends_with($palabra, 'es')) {
            $variantes[] = substr($palabra, 0, -2);
        }

        if (strlen($palabra) > 3 && str_ends_with($palabra, 's')) {
            $variantes[] = substr($palabra, 0, -1);
        }

        return $variantes;
    }

    /**
     * @return array{categorias: list<array{clave: string, nombre: string, iconos: list<string>}>, redes: array<string, array{nombre: string, palabras: string}>, iconos: array<string, array{nombre: string, palabras: string, auto?: bool, propio?: bool, nodos: list<array{0: string, 1: array<string, string|int|float>}>}>}
     */
    private function datos(): array
    {
        if (self::$datos !== null) {
            return self::$datos;
        }

        $ruta = base_path(self::RUTA);
        $contenido = is_file($ruta) ? file_get_contents($ruta) : false;
        $datos = $contenido !== false ? json_decode($contenido, true) : null;

        if (! is_array($datos) || ! isset($datos['iconos'], $datos['categorias'], $datos['redes'])) {
            throw new RuntimeException('No se pudo leer el catálogo de íconos ('.self::RUTA.'). Ejecuta scripts/generar-catalogo-iconos.mjs.');
        }

        /** @var array{categorias: list<array{clave: string, nombre: string, iconos: list<string>}>, redes: array<string, array{nombre: string, palabras: string}>, iconos: array<string, array{nombre: string, palabras: string, auto?: bool, propio?: bool, nodos: list<array{0: string, 1: array<string, string|int|float>}>}>} $datos */
        return self::$datos = $datos;
    }
}
