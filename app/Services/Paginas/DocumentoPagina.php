<?php

namespace App\Services\Paginas;

use App\Models\ArchivoPublico;
use App\Models\Negocio;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Valida y sanea el documento de una Página de enlaces. Se ejecuta igual al
 * guardar el borrador, al publicar, al restaurar versiones y al aplicar
 * plantillas.
 *
 * Principios:
 * - Lista blanca: cada campo, tipo de elemento y opción se reconstruye
 *   explícitamente; las claves desconocidas se descartan.
 * - Nunca HTML, JS ni CSS libre: los textos se guardan como texto (sin
 *   etiquetas) y la interfaz los muestra escapados; el formato enriquecido
 *   se limita a **negritas**, _cursivas_ y saltos de línea.
 * - Colores solo #rrggbb; números acotados; longitudes limitadas.
 * - URLs validadas por tipo (ValidadorUrlDestino); WhatsApp, teléfono y
 *   correo se construyen en el servidor a partir de los datos.
 * - Insertados (video, música, mapas) solo de proveedores permitidos y
 *   guardados como identificadores; la URL del iframe la arma la interfaz
 *   con una plantilla fija.
 * - Imágenes y videos solo como referencias a archivos del propio negocio.
 *
 * Modos: «borrador» (rechaza valores inválidos, admite elementos
 * incompletos), «publicacion» (además exige los datos obligatorios) y
 * «tolerante» (descarta en silencio lo inválido; para versiones antiguas,
 * plantillas y la página pública).
 */
class DocumentoPagina
{
    public const MODO_BORRADOR = 'borrador';

    public const MODO_PUBLICACION = 'publicacion';

    public const MODO_TOLERANTE = 'tolerante';

    public const ESQUEMA = 1;

    private const PATRON_UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private string $modo = self::MODO_BORRADOR;

    /** @var array<string, string> */
    private array $errores = [];

    /** @var array<string, ArchivoPublico> */
    private array $medios = [];

    /** @var array<string, true> */
    private array $ids = [];

    private int $totalBloques = 0;

    public function __construct(private readonly ValidadorUrlDestino $validador) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException con errores por elemento (bloques.{id}.{campo}).
     */
    public function sanear(mixed $entrada, Negocio $negocio, string $modo = self::MODO_BORRADOR): array
    {
        $this->modo = $modo;
        $this->errores = [];
        $this->ids = [];
        $this->totalBloques = 0;
        $this->medios = $this->cargarMedios($entrada, $negocio);

        $entrada = is_array($entrada) ? $entrada : [];
        $limites = (array) config('dasti.paginas.limites');

        $documento = [
            'esquema' => self::ESQUEMA,
            'perfil' => $this->perfil($this->arreglo($entrada['perfil'] ?? null), $negocio),
            'tema' => $this->tema($this->arreglo($entrada['tema'] ?? null)),
            'paginas' => [],
            'ajustes' => $this->ajustes($this->arreglo($entrada['ajustes'] ?? null)),
        ];

        $paginas = array_values(array_filter($this->lista($entrada['paginas'] ?? null), 'is_array'));

        if (count($paginas) > (int) ($limites['subpaginas'] ?? 20)) {
            $this->error('documento', 'Puedes tener hasta '.($limites['subpaginas'] ?? 20).' páginas.');
            $paginas = array_slice($paginas, 0, (int) ($limites['subpaginas'] ?? 20));
        }

        if ($paginas === []) {
            $paginas = [['titulo' => 'Inicio', 'bloques' => []]];
        }

        $slugs = [];

        foreach ($paginas as $indice => $pagina) {
            $documento['paginas'][] = $this->pagina($pagina, $indice === 0, $slugs);
        }

        // Los enlaces a subpáginas deben apuntar a una página existente.
        $idsPaginas = array_column($documento['paginas'], 'id');
        $documento['paginas'] = array_map(fn (array $p) => [
            ...$p,
            'bloques' => $this->revisarSubpaginas($p['bloques'], $idsPaginas, $p['id']),
        ], $documento['paginas']);

        if ($this->totalBloques > (int) ($limites['bloques_totales'] ?? 600)) {
            $this->error('documento', 'La página tiene demasiados elementos (máximo '.($limites['bloques_totales'] ?? 600).').');
        }

        $bytes = strlen((string) json_encode($documento));

        if ($bytes > (int) ($limites['documento_kb'] ?? 400) * 1024) {
            $this->error('documento', 'El contenido es demasiado grande. Divide la información en menos elementos.');
        }

        if ($this->errores !== [] && $this->modo !== self::MODO_TOLERANTE) {
            throw ValidationException::withMessages($this->errores);
        }

        return $documento;
    }

    /**
     * Documento inicial de un negocio.
     *
     * @return array<string, mixed>
     */
    public function inicial(Negocio $negocio): array
    {
        return $this->sanear([
            'perfil' => ['nombre' => $negocio->nombre_comercial, 'descripcion' => ''],
            'paginas' => [['titulo' => 'Inicio', 'bloques' => [
                ['tipo' => 'titulo', 'texto' => 'Bienvenido', 'tamano' => 'mediano'],
                ['tipo' => 'texto', 'texto' => 'Agrega aquí tus redes sociales, tu WhatsApp y todos tus enlaces.'],
            ]]],
        ], $negocio, self::MODO_TOLERANTE);
    }

    /**
     * Tema por defecto (también para «restablecer diseño»).
     *
     * @return array<string, mixed>
     */
    public static function temaPorDefecto(): array
    {
        return [
            'colores' => [
                'fondo' => '#f6f4ef', 'texto' => '#2a2723', 'titulos' => '#171512', 'texto_suave' => '#6f685e',
                'enlaces' => '#2a2723', 'boton_fondo' => '#ffffff', 'boton_texto' => '#1f1c18', 'boton_borde' => '#e3ddd2',
                'boton_hover_fondo' => '#f1ece3', 'boton_hover_texto' => '#171512', 'tarjeta_fondo' => '#ffffff',
                'tarjeta_texto' => '#2a2723', 'borde' => '#e3ddd2', 'acento' => '#b4532a',
            ],
            'fondo' => [
                'tipo' => 'solido', 'angulo' => 160,
                'paradas' => [['color' => '#f6f4ef', 'posicion' => 0], ['color' => '#ece6da', 'posicion' => 100]],
                'imagen' => null, 'superposicion' => '#000000', 'opacidad' => 0, 'desenfoque' => 0,
                'textura' => 'puntos', 'color_textura' => '#d9d2c5', 'video' => null, 'poster' => null, 'ahorro_datos' => true,
            ],
            'tipografia' => [
                'fuente_texto' => 'inter', 'fuente_titulos' => 'inter', 'tamano' => 16, 'peso_texto' => 400,
                'peso_titulos' => 700, 'espaciado' => 0, 'alineacion' => 'centro', 'escala_titulos' => 125,
            ],
            'botones' => [
                'estilo' => 'solido', 'radio' => 14, 'sombra' => 'suave', 'borde' => 1, 'tamano' => 'normal',
                'animacion' => 'elevar', 'icono' => 'izquierda', 'alineacion' => 'centro',
            ],
            'disposicion' => ['ancho' => 'normal', 'espaciado' => 'normal', 'encabezado' => 'centrado', 'forma_foto' => 'circulo'],
        ];
    }

    // ------------------------------------------------------------------
    // Secciones
    // ------------------------------------------------------------------

    /**
     * @param  array<mixed>  $p
     * @return array<string, mixed>
     */
    private function perfil(array $p, Negocio $negocio): array
    {
        $nombre = $this->texto($p['nombre'] ?? '', 80);

        if ($nombre === '' && $this->modo === self::MODO_PUBLICACION) {
            $this->error('perfil.nombre', 'Escribe el nombre que verán tus visitantes.');
        }

        $contacto = $this->arreglo($p['contacto'] ?? null);

        return [
            'nombre' => $nombre,
            'descripcion' => $this->texto($p['descripcion'] ?? '', 300, true),
            'foto' => $this->medio($p['foto'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'perfil.foto'),
            'logo' => $this->medio($p['logo'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'perfil.logo'),
            'portada' => $this->medio($p['portada'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'perfil.portada'),
            'favicon' => $this->medio($p['favicon'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'perfil.favicon'),
            'mostrar_nombre' => $this->booleano($p['mostrar_nombre'] ?? true),
            'contacto' => [
                'telefono' => $this->telefono($contacto['telefono'] ?? '', 'perfil.contacto.telefono', false) ?? '',
                'email' => $this->email($contacto['email'] ?? '', 'perfil.contacto.email', false) ?? '',
                'direccion' => $this->texto($contacto['direccion'] ?? '', 200),
            ],
        ];
    }

    /**
     * @param  array<mixed>  $t
     * @return array<string, mixed>
     */
    private function tema(array $t): array
    {
        $base = self::temaPorDefecto();
        $c = $this->arreglo($t['colores'] ?? null);
        $colores = [];

        foreach (CatalogoPagina::COLORES as $clave) {
            $colores[$clave] = $this->color($c[$clave] ?? null, $base['colores'][$clave]);
        }

        $f = $this->arreglo($t['fondo'] ?? null);
        $paradas = [];

        foreach (array_slice(array_values(array_filter($this->lista($f['paradas'] ?? null), 'is_array')), 0, 3) as $i => $parada) {
            $paradas[] = [
                'color' => $this->color($parada['color'] ?? null, $base['fondo']['paradas'][min($i, 1)]['color']),
                'posicion' => $this->entero($parada['posicion'] ?? ($i * 50), 0, 100, $i * 50),
            ];
        }

        if (count($paradas) < 2) {
            $paradas = $base['fondo']['paradas'];
        }

        $tipoFondo = $this->opcion($f['tipo'] ?? null, CatalogoPagina::FONDOS, 'solido');

        $fondo = [
            'tipo' => $tipoFondo,
            'angulo' => $this->entero($f['angulo'] ?? null, 0, 360, 160),
            'paradas' => $paradas,
            'imagen' => $this->medio($f['imagen'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'tema.fondo.imagen'),
            'superposicion' => $this->color($f['superposicion'] ?? null, '#000000'),
            'opacidad' => $this->entero($f['opacidad'] ?? null, 0, 90, 0),
            'desenfoque' => $this->entero($f['desenfoque'] ?? null, 0, 20, 0),
            'textura' => $this->opcion($f['textura'] ?? null, CatalogoPagina::TEXTURAS, 'puntos'),
            'color_textura' => $this->color($f['color_textura'] ?? null, $base['fondo']['color_textura']),
            'video' => $this->medio($f['video'] ?? null, ArchivoPublico::TIPO_VIDEO, 'tema.fondo.video'),
            'poster' => $this->medio($f['poster'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'tema.fondo.poster'),
            'ahorro_datos' => $this->booleano($f['ahorro_datos'] ?? true),
        ];

        if ($this->modo === self::MODO_PUBLICACION && $tipoFondo === 'imagen' && $fondo['imagen'] === null) {
            $this->error('tema.fondo.imagen', 'Elige la imagen de fondo o cambia el tipo de fondo.');
        }

        if ($this->modo === self::MODO_PUBLICACION && $tipoFondo === 'video' && $fondo['video'] === null) {
            $this->error('tema.fondo.video', 'Sube el video de fondo o cambia el tipo de fondo.');
        }

        $ti = $this->arreglo($t['tipografia'] ?? null);
        $b = $this->arreglo($t['botones'] ?? null);
        $d = $this->arreglo($t['disposicion'] ?? null);

        return [
            'colores' => $colores,
            'fondo' => $fondo,
            'tipografia' => [
                'fuente_texto' => $this->opcion($ti['fuente_texto'] ?? null, CatalogoPagina::FUENTES, 'inter'),
                'fuente_titulos' => $this->opcion($ti['fuente_titulos'] ?? null, CatalogoPagina::FUENTES, 'inter'),
                'tamano' => $this->entero($ti['tamano'] ?? null, 14, 20, 16),
                'peso_texto' => $this->opcion((int) ($ti['peso_texto'] ?? 400), [300, 400, 500], 400),
                'peso_titulos' => $this->opcion((int) ($ti['peso_titulos'] ?? 700), [500, 600, 700, 800], 700),
                'espaciado' => $this->entero($ti['espaciado'] ?? null, -3, 10, 0),
                'alineacion' => $this->opcion($ti['alineacion'] ?? null, ['centro', 'izquierda'], 'centro'),
                'escala_titulos' => $this->entero($ti['escala_titulos'] ?? null, 100, 170, 125),
            ],
            'botones' => [
                'estilo' => $this->opcion($b['estilo'] ?? null, CatalogoPagina::ESTILOS_BOTON, 'solido'),
                'radio' => $this->entero($b['radio'] ?? null, 0, 40, 14),
                'sombra' => $this->opcion($b['sombra'] ?? null, CatalogoPagina::SOMBRAS, 'suave'),
                'borde' => $this->entero($b['borde'] ?? null, 0, 4, 1),
                'tamano' => $this->opcion($b['tamano'] ?? null, CatalogoPagina::TAMANOS_BOTON, 'normal'),
                'animacion' => $this->opcion($b['animacion'] ?? null, CatalogoPagina::ANIMACIONES, 'elevar'),
                'icono' => $this->opcion($b['icono'] ?? null, CatalogoPagina::POSICIONES_ICONO, 'izquierda'),
                'alineacion' => $this->opcion($b['alineacion'] ?? null, ['centro', 'izquierda'], 'centro'),
            ],
            'disposicion' => [
                'ancho' => $this->opcion($d['ancho'] ?? null, CatalogoPagina::ANCHOS, 'normal'),
                'espaciado' => $this->opcion($d['espaciado'] ?? null, CatalogoPagina::ESPACIADOS, 'normal'),
                'encabezado' => $this->opcion($d['encabezado'] ?? null, CatalogoPagina::ENCABEZADOS, 'centrado'),
                'forma_foto' => $this->opcion($d['forma_foto'] ?? null, CatalogoPagina::FORMAS_FOTO, 'circulo'),
            ],
        ];
    }

    /**
     * @param  array<mixed>  $a
     * @return array<string, mixed>
     */
    private function ajustes(array $a): array
    {
        return [
            'seo_titulo' => $this->texto($a['seo_titulo'] ?? '', 70),
            'seo_descripcion' => $this->texto($a['seo_descripcion'] ?? '', 160),
            'imagen_social' => $this->medio($a['imagen_social'] ?? null, ArchivoPublico::TIPO_IMAGEN, 'ajustes.imagen_social'),
            'indexar' => $this->booleano($a['indexar'] ?? true),
            'notificar_mensajes' => $this->booleano($a['notificar_mensajes'] ?? false),
        ];
    }

    /**
     * @param  array<mixed>  $p
     * @param  array<string, true>  $slugs
     * @return array<string, mixed>
     */
    private function pagina(array $p, bool $esInicio, array &$slugs): array
    {
        $id = $this->id($p['id'] ?? null);
        $titulo = $this->texto($p['titulo'] ?? '', 60);

        if ($esInicio) {
            $slug = 'inicio';
        } else {
            $slug = Str::slug($this->texto($p['slug'] ?? '', 40) ?: $titulo);
            $slug = trim(substr($slug, 0, 40), '-');

            if ($slug === '' || in_array($slug, CatalogoPagina::SUBPAGINAS_RESERVADAS, true)) {
                $slug = 'pagina';
            }

            $base = $slug;
            $n = 2;

            while (isset($slugs[$slug]) || $slug === 'inicio') {
                $slug = substr($base, 0, 36).'-'.$n++;
            }
        }

        $slugs[$slug] = true;

        if ($titulo === '' && $this->modo === self::MODO_PUBLICACION) {
            $this->error("paginas.{$id}.titulo", 'Escribe el nombre de la página.');
        }

        $limite = (int) config('dasti.paginas.limites.bloques_por_pagina', 150);
        $bloques = array_values(array_filter($this->lista($p['bloques'] ?? null), 'is_array'));

        if (count($bloques) > $limite) {
            $this->error("paginas.{$id}.bloques", "Cada página admite hasta {$limite} elementos.");
            $bloques = array_slice($bloques, 0, $limite);
        }

        $saneados = [];

        foreach ($bloques as $bloque) {
            $saneado = $this->bloque($bloque, CatalogoPagina::TIPOS);

            if ($saneado !== null) {
                $saneados[] = $saneado;
            }
        }

        return [
            'id' => $id,
            'slug' => $slug,
            'titulo' => $titulo === '' && $esInicio ? 'Inicio' : $titulo,
            'descripcion' => $this->texto($p['descripcion'] ?? '', 160),
            'bloques' => $saneados,
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  list<string>  $permitidos
     * @return array<string, mixed>|null
     */
    private function bloque(array $b, array $permitidos): ?array
    {
        $tipo = is_string($b['tipo'] ?? null) ? $b['tipo'] : '';

        if (! in_array($tipo, $permitidos, true)) {
            return null;
        }

        $this->totalBloques++;
        $id = $this->id($b['id'] ?? null);
        $campo = fn (string $c) => "bloques.{$id}.{$c}";

        $comun = [
            'id' => $id,
            'tipo' => $tipo,
            'oculto' => $this->booleano($b['oculto'] ?? false),
            'visible_desde' => $this->fecha($b['visible_desde'] ?? null, $campo('visible_desde')),
            'visible_hasta' => $this->fecha($b['visible_hasta'] ?? null, $campo('visible_hasta')),
        ];

        if ($comun['visible_desde'] !== null && $comun['visible_hasta'] !== null && $comun['visible_hasta'] <= $comun['visible_desde']) {
            $this->error($campo('visible_hasta'), 'La fecha de fin debe ser posterior a la de inicio.');
        }

        $datos = match ($tipo) {
            'enlace', 'whatsapp', 'telefono', 'correo', 'subpagina' => $this->boton($tipo, $b, $campo),
            'redes' => $this->redes($b, $campo),
            'mapa' => $this->mapa($b, $campo),
            'video' => $this->video($b, $campo),
            'musica' => $this->musica($b, $campo),
            'imagen' => $this->imagen($b, $campo),
            'carrusel' => $this->carrusel($b, $campo),
            'titulo' => $this->titulo($b, $campo),
            'texto' => [
                'texto' => $this->requerido($this->texto($b['texto'] ?? '', 2000, true), $campo('texto'), 'Escribe el texto.'),
                'alineacion' => $this->opcion($b['alineacion'] ?? null, ['tema', 'izquierda', 'centro'], 'tema'),
                'tamano' => $this->opcion($b['tamano'] ?? null, ['normal', 'pequeno', 'grande'], 'normal'),
            ],
            'separador' => [
                'estilo' => $this->opcion($b['estilo'] ?? null, ['linea', 'espacio', 'puntos'], 'linea'),
                'alto' => $this->entero($b['alto'] ?? null, 8, 120, 24),
            ],
            'grupo' => $this->grupo($b, $campo),
            'tarjeta' => $this->tarjeta($b, $campo),
            'contacto' => $this->contacto($b),
            'qr' => [
                'titulo' => $this->texto($b['titulo'] ?? '', 80),
                'descripcion' => $this->texto($b['descripcion'] ?? '', 200),
                'tamano' => $this->entero($b['tamano'] ?? null, 120, 280, 180),
            ],
            default => [],
        };

        return [...$comun, ...$datos];
    }

    /**
     * Botón de acción (enlace, WhatsApp, teléfono, correo o subpágina).
     *
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function boton(string $tipo, array $b, \Closure $campo): array
    {
        $titulo = $this->texto($b['titulo'] ?? '', 100);

        $comun = [
            'titulo' => $this->requerido($titulo, $campo('titulo'), 'Escribe el texto del botón.'),
            'subtitulo' => $this->texto($b['subtitulo'] ?? '', 140),
            'icono' => $this->icono($b['icono'] ?? null),
            'miniatura' => $this->medio($b['miniatura'] ?? null, ArchivoPublico::TIPO_IMAGEN, $campo('miniatura')),
            'destacado' => $this->booleano($b['destacado'] ?? false),
            'insignia' => $this->texto($b['insignia'] ?? '', 24),
            'animacion' => $this->opcion($b['animacion'] ?? null, ['tema', ...CatalogoPagina::ANIMACIONES], 'tema'),
            'tamano' => $this->opcion($b['tamano'] ?? null, ['normal', 'grande'], 'normal'),
            'color_fondo' => $this->colorOpcional($b['color_fondo'] ?? null),
            'color_texto' => $this->colorOpcional($b['color_texto'] ?? null),
        ];

        return [...$comun, ...match ($tipo) {
            'enlace' => ['url' => $this->url($b['url'] ?? '', $campo('url'), true)],
            'whatsapp' => [
                'telefono' => $this->telefono($b['telefono'] ?? '', $campo('telefono'), true) ?? '',
                'mensaje' => $this->texto($b['mensaje'] ?? '', 500, true),
            ],
            'telefono' => ['telefono' => $this->telefono($b['telefono'] ?? '', $campo('telefono'), true) ?? ''],
            'correo' => [
                'email' => $this->email($b['email'] ?? '', $campo('email'), true) ?? '',
                'asunto' => $this->texto($b['asunto'] ?? '', 120),
            ],
            'subpagina' => ['pagina_id' => $this->uuidValido($b['pagina_id'] ?? null) ?? ''],
            default => [],
        }];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function redes(array $b, \Closure $campo): array
    {
        $limite = (int) config('dasti.paginas.limites.redes', 20);
        $elementos = [];

        foreach (array_slice(array_values(array_filter($this->lista($b['elementos'] ?? null), 'is_array')), 0, $limite) as $e) {
            $red = $this->opcion($e['red'] ?? null, array_keys(CatalogoPagina::REDES), 'sitio');
            $id = $this->id($e['id'] ?? null);
            $clave = $campo("elementos.{$id}.valor");

            if ($red === 'whatsapp') {
                $valor = $this->telefono($e['valor'] ?? '', $clave, true) ?? '';
            } else {
                $valor = $this->url($e['valor'] ?? '', $clave, true);

                if ($valor !== '' && ! $this->dominioDeRed($valor, $red)) {
                    $this->error($clave, 'La dirección no corresponde a '.CatalogoPagina::NOMBRES_REDES[$red].'. Copia el enlace de tu perfil.');
                    $valor = '';
                }
            }

            $elementos[] = ['id' => $id, 'red' => $red, 'valor' => $valor];
        }

        if ($elementos === [] && $this->modo === self::MODO_PUBLICACION) {
            $this->error($campo('elementos'), 'Agrega al menos una red social.');
        }

        return [
            'estilo' => $this->opcion($b['estilo'] ?? null, ['iconos', 'botones'], 'iconos'),
            'tamano' => $this->opcion($b['tamano'] ?? null, ['normal', 'grande'], 'normal'),
            'elementos' => $elementos,
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function mapa(array $b, \Closure $campo): array
    {
        return [
            'titulo' => $this->texto($b['titulo'] ?? '', 100),
            'direccion' => $this->requerido($this->texto($b['direccion'] ?? '', 200), $campo('direccion'), 'Escribe la dirección o el nombre del lugar.'),
            'mostrar_mapa' => $this->booleano($b['mostrar_mapa'] ?? true),
            'boton' => $this->texto($b['boton'] ?? 'Cómo llegar', 40) ?: 'Cómo llegar',
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function video(array $b, \Closure $campo): array
    {
        $fuente = $this->opcion($b['fuente'] ?? null, ['externo', 'archivo'], 'externo');
        $datos = ['titulo' => $this->texto($b['titulo'] ?? '', 100), 'fuente' => $fuente, 'url' => '', 'proveedor' => '', 'ref' => '', 'archivo' => null, 'poster' => null];

        if ($fuente === 'archivo') {
            $datos['archivo'] = $this->medio($b['archivo'] ?? null, ArchivoPublico::TIPO_VIDEO, $campo('archivo'));
            $datos['poster'] = $this->medio($b['poster'] ?? null, ArchivoPublico::TIPO_IMAGEN, $campo('poster'));

            if ($datos['archivo'] === null && $this->modo === self::MODO_PUBLICACION) {
                $this->error($campo('archivo'), 'Sube el video.');
            }

            return $datos;
        }

        $url = $this->texto($b['url'] ?? '', 500);

        if ($url === '') {
            $this->requerido('', $campo('url'), 'Pega el enlace del video de YouTube o Vimeo.');

            return $datos;
        }

        $insertado = Insertados::video($url);

        if ($insertado === null) {
            $this->error($campo('url'), 'Solo se pueden insertar videos de YouTube o Vimeo. Pega el enlace del video.');

            return $datos;
        }

        return [...$datos, ...$insertado];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function musica(array $b, \Closure $campo): array
    {
        $datos = ['titulo' => $this->texto($b['titulo'] ?? '', 100), 'url' => '', 'proveedor' => '', 'ref' => ''];
        $url = $this->texto($b['url'] ?? '', 500);

        if ($url === '') {
            $this->requerido('', $campo('url'), 'Pega el enlace de Spotify, SoundCloud o Apple Music.');

            return $datos;
        }

        $insertado = Insertados::musica($url);

        if ($insertado === null) {
            $this->error($campo('url'), 'Solo se puede insertar música de Spotify, SoundCloud o Apple Music.');

            return $datos;
        }

        return [...$datos, ...$insertado];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function imagen(array $b, \Closure $campo): array
    {
        $archivo = $this->medio($b['archivo'] ?? null, ArchivoPublico::TIPO_IMAGEN, $campo('archivo'));

        if ($archivo === null && $this->modo === self::MODO_PUBLICACION && ! isset($this->errores[$campo('archivo')])) {
            $this->error($campo('archivo'), 'Elige una imagen.');
        }

        return [
            'archivo' => $archivo,
            'alt' => $this->texto($b['alt'] ?? '', 200),
            'url' => $this->url($b['url'] ?? '', $campo('url'), false),
            'proporcion' => $this->opcion($b['proporcion'] ?? null, CatalogoPagina::PROPORCIONES, 'original'),
            'redondeo' => $this->booleano($b['redondeo'] ?? true),
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function carrusel(array $b, \Closure $campo): array
    {
        $limite = (int) config('dasti.paginas.limites.imagenes_carrusel', 12);
        $elementos = [];

        foreach (array_slice(array_values(array_filter($this->lista($b['elementos'] ?? null), 'is_array')), 0, $limite) as $e) {
            $id = $this->id($e['id'] ?? null);
            $archivo = $this->medio($e['archivo'] ?? null, ArchivoPublico::TIPO_IMAGEN, $campo("elementos.{$id}.archivo"));

            if ($archivo === null) {
                continue;
            }

            $elementos[] = [
                'id' => $id,
                'archivo' => $archivo,
                'alt' => $this->texto($e['alt'] ?? '', 200),
                'url' => $this->url($e['url'] ?? '', $campo("elementos.{$id}.url"), false),
            ];
        }

        if ($elementos === [] && $this->modo === self::MODO_PUBLICACION) {
            $this->error($campo('elementos'), 'Agrega al menos una imagen al carrusel.');
        }

        return [
            'titulo' => $this->texto($b['titulo'] ?? '', 100),
            'proporcion' => $this->opcion($b['proporcion'] ?? null, ['cuadrada', 'horizontal', 'vertical'], 'cuadrada'),
            'elementos' => $elementos,
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function titulo(array $b, \Closure $campo): array
    {
        return [
            'texto' => $this->requerido($this->texto($b['texto'] ?? '', 120), $campo('texto'), 'Escribe el título.'),
            'tamano' => $this->opcion($b['tamano'] ?? null, ['grande', 'mediano', 'pequeno'], 'mediano'),
            'alineacion' => $this->opcion($b['alineacion'] ?? null, ['tema', 'izquierda', 'centro'], 'tema'),
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function grupo(array $b, \Closure $campo): array
    {
        $limite = (int) config('dasti.paginas.limites.elementos_grupo', 60);
        $hijos = [];

        foreach (array_slice(array_values(array_filter($this->lista($b['bloques'] ?? null), 'is_array')), 0, $limite) as $hijo) {
            // Un solo nivel: dentro de un grupo no se admiten grupos.
            $saneado = $this->bloque($hijo, CatalogoPagina::TIPOS_EN_GRUPO);

            if ($saneado !== null) {
                $hijos[] = $saneado;
            }
        }

        return [
            'titulo' => $this->texto($b['titulo'] ?? '', 100),
            'disposicion' => $this->opcion($b['disposicion'] ?? null, ['lista', 'cuadricula'], 'lista'),
            'columnas' => $this->opcion((int) ($b['columnas'] ?? 2), [2, 3], 2),
            'plegable' => $this->booleano($b['plegable'] ?? false),
            'abierto' => $this->booleano($b['abierto'] ?? true),
            'bloques' => $hijos,
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @param  \Closure(string): string  $campo
     * @return array<string, mixed>
     */
    private function tarjeta(array $b, \Closure $campo): array
    {
        return [
            'titulo' => $this->requerido($this->texto($b['titulo'] ?? '', 100), $campo('titulo'), 'Escribe el título de la tarjeta.'),
            'texto' => $this->texto($b['texto'] ?? '', 800, true),
            'imagen' => $this->medio($b['imagen'] ?? null, ArchivoPublico::TIPO_IMAGEN, $campo('imagen')),
            'boton' => $this->texto($b['boton'] ?? '', 40),
            'url' => $this->url($b['url'] ?? '', $campo('url'), false),
        ];
    }

    /**
     * @param  array<mixed>  $b
     * @return array<string, mixed>
     */
    private function contacto(array $b): array
    {
        return [
            'titulo' => $this->texto($b['titulo'] ?? 'Escríbenos', 100),
            'descripcion' => $this->texto($b['descripcion'] ?? '', 300),
            'pedir_telefono' => $this->booleano($b['pedir_telefono'] ?? false),
            'boton' => $this->texto($b['boton'] ?? 'Enviar mensaje', 40) ?: 'Enviar mensaje',
            'aviso' => $this->texto($b['aviso'] ?? '', 400) ?: 'Usaremos tus datos solo para responder a tu mensaje. No los compartimos con terceros.',
            'exito' => $this->texto($b['exito'] ?? '', 200) ?: '¡Gracias! Recibimos tu mensaje y te responderemos pronto.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  list<string>  $idsPaginas
     * @return list<array<string, mixed>>
     */
    private function revisarSubpaginas(array $bloques, array $idsPaginas, string $paginaActual): array
    {
        foreach ($bloques as $i => $bloque) {
            if ($bloque['tipo'] === 'grupo') {
                $bloques[$i]['bloques'] = $this->revisarSubpaginas($bloque['bloques'], $idsPaginas, $paginaActual);

                continue;
            }

            if ($bloque['tipo'] !== 'subpagina') {
                continue;
            }

            if (! in_array($bloque['pagina_id'], $idsPaginas, true) || $bloque['pagina_id'] === $paginaActual) {
                if ($bloque['pagina_id'] !== '' || $this->modo === self::MODO_PUBLICACION) {
                    $this->error("bloques.{$bloque['id']}.pagina_id", 'Elige otra de tus páginas como destino.');
                }

                $bloques[$i]['pagina_id'] = '';
            }
        }

        return $bloques;
    }

    // ------------------------------------------------------------------
    // Página pública
    // ------------------------------------------------------------------

    /**
     * Documento listo para el visitante: sin elementos ocultos o fuera de
     * su periodo de visibilidad, sin datos de destino (solo la ruta de
     * seguimiento) y con las imágenes que siguen existiendo.
     *
     * @param  array<string, mixed>  $documento
     * @param  string  $base  Ruta base relativa ('/l/slug' o '' en dominio propio).
     * @param  list<string>|null  $mediosExistentes  UUID que siguen existiendo (null = no comprobar).
     * @return array<string, mixed>
     */
    public function paraPublico(array $documento, string $base, ?CarbonInterface $ahora = null, ?array $mediosExistentes = null): array
    {
        $ahora ??= CarbonImmutable::now();
        $slugs = [];

        foreach ($documento['paginas'] ?? [] as $p) {
            $slugs[$p['id']] = $p['slug'];
        }

        $limpiar = function (mixed $valor) use (&$limpiar, $mediosExistentes): mixed {
            if (! is_array($valor)) {
                return $valor;
            }

            if (isset($valor['id'], $valor['url']) && is_string($valor['url']) && str_starts_with($valor['url'], '/medios/')) {
                return $mediosExistentes === null || in_array($valor['id'], $mediosExistentes, true) ? $valor : null;
            }

            return array_map($limpiar, $valor);
        };

        $documento = $limpiar($documento);

        $documento['paginas'] = array_map(fn (array $p) => [
            ...$p,
            'bloques' => $this->bloquesPublicos($p['bloques'], $base, $slugs, $ahora),
        ], $documento['paginas'] ?? []);

        return $documento;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  array<string, string>  $slugs
     * @return list<array<string, mixed>>
     */
    private function bloquesPublicos(array $bloques, string $base, array $slugs, CarbonInterface $ahora): array
    {
        $salida = [];

        foreach ($bloques as $bloque) {
            if (! self::esVisible($bloque, $ahora)) {
                continue;
            }

            $rastreo = $base.'/ir/'.$bloque['id'];
            $publico = $bloque;
            unset($publico['oculto'], $publico['visible_desde'], $publico['visible_hasta']);

            switch ($bloque['tipo']) {
                case 'grupo':
                    $publico['bloques'] = $this->bloquesPublicos($bloque['bloques'], $base, $slugs, $ahora);
                    break;
                case 'enlace':
                case 'whatsapp':
                    unset($publico['url'], $publico['telefono'], $publico['mensaje']);
                    $publico['href'] = $this->destino($bloque, $base, $slugs) !== null ? $rastreo : null;
                    break;
                case 'telefono':
                case 'correo':
                    // tel: y mailto: se abren directamente (una redirección a
                    // esos esquemas no es fiable en todos los navegadores); el
                    // clic se registra con un aviso a la ruta de seguimiento.
                    unset($publico['telefono'], $publico['email'], $publico['asunto']);
                    $publico['href'] = $this->destino($bloque, $base, $slugs);
                    $publico['rastreo'] = $rastreo;
                    break;
                case 'subpagina':
                    unset($publico['pagina_id']);
                    // También pasa por la ruta de seguimiento (cuenta como clic).
                    $publico['href'] = $this->destino($bloque, $base, $slugs) !== null ? $rastreo : null;
                    $publico['pagina_slug'] = $slugs[$bloque['pagina_id']] ?? null;
                    break;
                case 'mapa':
                    $publico['href'] = $rastreo;
                    break;
                case 'imagen':
                case 'tarjeta':
                    $publico['href'] = ($bloque['url'] ?? '') !== '' ? $rastreo : null;
                    unset($publico['url']);
                    break;
                case 'carrusel':
                    $publico['elementos'] = array_values(array_filter(array_map(function (array $e) use ($base) {
                        if (($e['archivo'] ?? null) === null) {
                            return null;
                        }
                        $e['href'] = ($e['url'] ?? '') !== '' ? $base.'/ir/'.$e['id'] : null;
                        unset($e['url']);

                        return $e;
                    }, $bloque['elementos'])));
                    break;
                case 'redes':
                    $publico['elementos'] = array_values(array_map(function (array $e) use ($base) {
                        $e['href'] = $e['valor'] !== '' ? $base.'/ir/'.$e['id'] : null;
                        unset($e['valor']);

                        return $e;
                    }, array_filter($bloque['elementos'], fn (array $e) => $e['valor'] !== '')));
                    break;
            }

            $salida[] = $publico;
        }

        return $salida;
    }

    /**
     * ¿Se muestra ahora? Ocultos y fuera del periodo programado, no.
     *
     * @param  array<string, mixed>  $bloque
     */
    public static function esVisible(array $bloque, CarbonInterface $ahora): bool
    {
        if (($bloque['oculto'] ?? false) === true) {
            return false;
        }

        $zona = (string) config('app.timezone');
        $desde = $bloque['visible_desde'] ?? null;
        $hasta = $bloque['visible_hasta'] ?? null;

        if (is_string($desde) && $ahora->lt(CarbonImmutable::createFromFormat('Y-m-d\TH:i', $desde, $zona))) {
            return false;
        }

        if (is_string($hasta) && $ahora->gte(CarbonImmutable::createFromFormat('Y-m-d\TH:i', $hasta, $zona))) {
            return false;
        }

        return true;
    }

    /**
     * Busca un elemento clicable (o un elemento de redes/carrusel) por id
     * entre los visibles del documento publicado.
     *
     * @param  array<string, mixed>  $documento
     * @return array{bloque: array<string, mixed>, pagina: string}|null
     */
    public function buscarClicable(array $documento, string $id, CarbonInterface $ahora): ?array
    {
        foreach ($documento['paginas'] ?? [] as $pagina) {
            foreach ($pagina['bloques'] as $bloque) {
                if (! self::esVisible($bloque, $ahora)) {
                    continue;
                }

                $candidatos = $bloque['tipo'] === 'grupo'
                    ? array_filter($bloque['bloques'], fn (array $b) => self::esVisible($b, $ahora))
                    : [$bloque];

                foreach ($candidatos as $c) {
                    if ($c['id'] === $id) {
                        return ['bloque' => $c, 'pagina' => $pagina['slug']];
                    }

                    if (in_array($c['tipo'], ['redes', 'carrusel'], true)) {
                        foreach ($c['elementos'] as $e) {
                            if ($e['id'] === $id) {
                                return ['bloque' => ['tipo' => $c['tipo'] === 'redes' ? 'red' : 'elemento_carrusel', ...$e], 'pagina' => $pagina['slug']];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Destino ACTUAL de un elemento, construido en el servidor.
     *
     * @param  array<string, mixed>  $bloque
     * @param  array<string, string>  $slugs  id de página => slug
     */
    public function destino(array $bloque, string $base, array $slugs = []): ?string
    {
        $url = match ($bloque['tipo'] ?? '') {
            'enlace', 'imagen', 'tarjeta', 'elemento_carrusel' => (string) ($bloque['url'] ?? ''),
            'whatsapp' => self::urlWhatsapp((string) ($bloque['telefono'] ?? ''), (string) ($bloque['mensaje'] ?? '')),
            'telefono' => ($bloque['telefono'] ?? '') !== '' ? 'tel:+'.$bloque['telefono'] : '',
            'correo' => ($bloque['email'] ?? '') !== ''
                ? 'mailto:'.$bloque['email'].(($bloque['asunto'] ?? '') !== '' ? '?subject='.rawurlencode((string) $bloque['asunto']) : '')
                : '',
            'subpagina' => match (true) {
                ! isset($slugs[$bloque['pagina_id'] ?? '']) => '',
                $slugs[$bloque['pagina_id']] === 'inicio' => $base === '' ? '/' : $base,
                default => $base.'/'.$slugs[$bloque['pagina_id']],
            },
            'mapa' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) ($bloque['direccion'] ?? '')),
            'red' => ($bloque['red'] ?? '') === 'whatsapp' ? self::urlWhatsapp((string) ($bloque['valor'] ?? ''), '') : (string) ($bloque['valor'] ?? ''),
            default => '',
        };

        return $url === '' ? null : $url;
    }

    public static function urlWhatsapp(string $telefono, string $mensaje): string
    {
        if ($telefono === '') {
            return '';
        }

        return 'https://wa.me/'.$telefono.($mensaje !== '' ? '?text='.rawurlencode($mensaje) : '');
    }

    /**
     * Recorre todos los elementos (incluidos los de grupos).
     *
     * @param  array<string, mixed>  $documento
     * @return \Generator<int, array{bloque: array<string, mixed>, pagina: array<string, mixed>}>
     */
    public static function recorrer(array $documento): \Generator
    {
        foreach ($documento['paginas'] ?? [] as $pagina) {
            foreach ($pagina['bloques'] ?? [] as $bloque) {
                yield ['bloque' => $bloque, 'pagina' => $pagina];

                if (($bloque['tipo'] ?? '') === 'grupo') {
                    foreach ($bloque['bloques'] ?? [] as $hijo) {
                        yield ['bloque' => $hijo, 'pagina' => $pagina];
                    }
                }
            }
        }
    }

    /**
     * Páginas (inicio y subpáginas) de un documento ya validado.
     *
     * @param  array<string, mixed>|null  $documento
     * @return list<array<string, mixed>>
     */
    public static function paginas(?array $documento): array
    {
        $paginas = $documento['paginas'] ?? [];

        return is_array($paginas) ? array_values(array_filter($paginas, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>|null  $documento
     * @return array<string, mixed>|null
     */
    public static function buscarPagina(?array $documento, string $slug): ?array
    {
        foreach (self::paginas($documento) as $pagina) {
            if (($pagina['slug'] ?? null) === $slug) {
                return $pagina;
            }
        }

        return null;
    }

    /**
     * id de página => slug.
     *
     * @param  array<string, mixed>|null  $documento
     * @return array<string, string>
     */
    public static function slugs(?array $documento): array
    {
        $slugs = [];

        foreach (self::paginas($documento) as $pagina) {
            $slugs[(string) ($pagina['id'] ?? '')] = (string) ($pagina['slug'] ?? '');
        }

        return $slugs;
    }

    /**
     * Total de elementos de primer nivel.
     *
     * @param  array<string, mixed>|null  $documento
     */
    public static function contarElementos(?array $documento): int
    {
        return array_sum(array_map(fn (array $p) => is_array($p['bloques'] ?? null) ? count($p['bloques']) : 0, self::paginas($documento)));
    }

    /**
     * Nombre legible de un elemento (estadísticas).
     *
     * @param  array<string, mixed>  $bloque
     */
    public static function nombre(array $bloque): string
    {
        $texto = (string) ($bloque['titulo'] ?? $bloque['texto'] ?? $bloque['alt'] ?? '');

        return $texto !== '' ? $texto : match ($bloque['tipo'] ?? '') {
            'redes' => 'Redes sociales',
            'mapa' => 'Mapa',
            'imagen' => 'Imagen',
            'carrusel' => 'Carrusel',
            default => 'Elemento',
        };
    }

    // ------------------------------------------------------------------
    // Valores primitivos
    // ------------------------------------------------------------------

    private function error(string $clave, string $mensaje): void
    {
        $this->errores[$clave] ??= $mensaje;
    }

    private function requerido(string $valor, string $clave, string $mensaje): string
    {
        if ($valor === '' && $this->modo === self::MODO_PUBLICACION) {
            $this->error($clave, $mensaje);
        }

        return $valor;
    }

    /**
     * @return array<mixed>
     */
    private function arreglo(mixed $valor): array
    {
        return is_array($valor) && ! array_is_list($valor) ? $valor : [];
    }

    /**
     * @return list<mixed>
     */
    private function lista(mixed $valor): array
    {
        return is_array($valor) ? array_values($valor) : [];
    }

    /**
     * Texto plano: sin etiquetas HTML ni caracteres de control. La interfaz
     * lo muestra siempre escapado.
     */
    public function texto(mixed $valor, int $maximo, bool $multilinea = false): string
    {
        if (! is_string($valor) && ! is_int($valor) && ! is_float($valor)) {
            return '';
        }

        $texto = str_replace(["\r\n", "\r"], "\n", (string) $valor);
        $texto = (string) preg_replace('/<\/?[a-z!?][^>]*>?/iu', '', $texto);
        $texto = (string) preg_replace($multilinea ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $multilinea ? '' : ' ', $texto);

        if ($multilinea) {
            $texto = (string) preg_replace("/\n{3,}/", "\n\n", $texto);
        }

        return trim(mb_substr(trim($texto), 0, $maximo));
    }

    private function color(mixed $valor, string $porDefecto): string
    {
        return is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) ? strtolower($valor) : $porDefecto;
    }

    private function colorOpcional(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) ? strtolower($valor) : null;
    }

    private function entero(mixed $valor, int $min, int $max, int $porDefecto): int
    {
        if (! is_numeric($valor)) {
            return $porDefecto;
        }

        return max($min, min($max, (int) round((float) $valor)));
    }

    /**
     * @template T of string|int
     *
     * @param  list<T>  $opciones
     * @param  T  $porDefecto
     * @return T
     */
    private function opcion(mixed $valor, array $opciones, string|int $porDefecto): string|int
    {
        return in_array($valor, $opciones, true) ? $valor : $porDefecto;
    }

    private function booleano(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 'true';
    }

    private function icono(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        if (in_array($valor, CatalogoPagina::ICONOS, true)) {
            return $valor;
        }

        return str_starts_with($valor, 'red:') && array_key_exists(substr($valor, 4), CatalogoPagina::REDES) ? $valor : null;
    }

    private function uuidValido(mixed $valor): ?string
    {
        return is_string($valor) && preg_match(self::PATRON_UUID, strtolower($valor)) ? strtolower($valor) : null;
    }

    /**
     * Identificador estable (UUID). Si falta, es inválido o está repetido,
     * se genera uno nuevo.
     */
    private function id(mixed $valor): string
    {
        $id = $this->uuidValido($valor);

        if ($id === null || isset($this->ids[$id])) {
            $id = (string) Str::uuid();
        }

        $this->ids[$id] = true;

        return $id;
    }

    private function fecha(mixed $valor, string $clave): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valor)) {
            $fecha = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $valor, (string) config('app.timezone'));

            if ($fecha !== null && $fecha->format('Y-m-d\TH:i') === $valor) {
                return $valor;
            }
        }

        $this->error($clave, 'La fecha no es válida.');

        return null;
    }

    private function url(mixed $valor, string $clave, bool $requerida): string
    {
        $url = is_string($valor) ? trim($valor) : '';

        if ($url === '') {
            if ($requerida) {
                $this->requerido('', $clave, 'Escribe la dirección del enlace.');
            }

            return '';
        }

        // Comodidad: «www.ejemplo.com» se entiende como https://.
        if (! preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) && preg_match('#^[a-z0-9-]+(\.[a-z0-9-]+)+(/|$)#i', $url)) {
            $url = 'https://'.$url;
        }

        $error = $this->validador->error($url);

        if ($error !== null) {
            $this->error($clave, $error);

            return '';
        }

        return $this->validador->normalizar($url);
    }

    /**
     * Número internacional solo con dígitos (8 a 15). Con 10 dígitos se
     * asume México (+52).
     */
    private function telefono(mixed $valor, string $clave, bool $requerido): ?string
    {
        $texto = is_string($valor) || is_int($valor) ? trim((string) $valor) : '';

        if ($texto === '') {
            if ($requerido) {
                $this->requerido('', $clave, 'Escribe el número de teléfono.');
            }

            return null;
        }

        if (preg_match('/[^\d\s+\-().]/', $texto)) {
            $this->error($clave, 'El teléfono solo puede contener números.');

            return null;
        }

        $digitos = (string) preg_replace('/\D/', '', $texto);

        if (strlen($digitos) === 10 && ! str_starts_with($texto, '+')) {
            $digitos = '52'.$digitos;
        }

        if (strlen($digitos) < 8 || strlen($digitos) > 15) {
            $this->error($clave, 'Escribe el número completo con lada (10 dígitos en México).');

            return null;
        }

        return $digitos;
    }

    private function email(mixed $valor, string $clave, bool $requerido): ?string
    {
        $email = is_string($valor) ? trim($valor) : '';

        if ($email === '') {
            if ($requerido) {
                $this->requerido('', $clave, 'Escribe el correo electrónico.');
            }

            return null;
        }

        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error($clave, 'El correo electrónico no es válido.');

            return null;
        }

        return strtolower($email);
    }

    /**
     * Referencia a una imagen o video del propio negocio.
     *
     * @return array{id: string, url: string, ancho: int|null, alto: int|null}|null
     */
    private function medio(mixed $valor, string $tipo, string $clave): ?array
    {
        $uuid = is_array($valor) ? ($valor['id'] ?? null) : $valor;

        if ($uuid === null || $uuid === '') {
            return null;
        }

        $uuid = $this->uuidValido($uuid);
        $archivo = $uuid !== null ? ($this->medios[$uuid] ?? null) : null;

        if ($archivo === null || $archivo->tipo !== $tipo) {
            $this->error($clave, $tipo === ArchivoPublico::TIPO_VIDEO
                ? 'El video ya no está disponible. Vuelve a subirlo.'
                : 'La imagen ya no está disponible. Vuelve a elegirla.');

            return null;
        }

        return ['id' => $archivo->uuid, 'url' => $archivo->url(), 'ancho' => $archivo->ancho, 'alto' => $archivo->alto];
    }

    private function dominioDeRed(string $url, string $red): bool
    {
        $dominios = CatalogoPagina::REDES[$red] ?? null;

        if ($dominios === null) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($dominios as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.'.$dominio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Precarga los archivos del negocio referenciados en el documento (una
     * sola consulta). Los UUID de otros negocios nunca se encuentran.
     *
     * @return array<string, ArchivoPublico>
     */
    private function cargarMedios(mixed $entrada, Negocio $negocio): array
    {
        if (! is_array($entrada)) {
            return [];
        }

        $uuids = [];

        array_walk_recursive($entrada, function (mixed $valor) use (&$uuids) {
            if (is_string($valor) && strlen($valor) === 36 && count($uuids) < 3000 && preg_match(self::PATRON_UUID, strtolower($valor))) {
                $uuids[strtolower($valor)] = true;
            }
        });

        if ($uuids === []) {
            return [];
        }

        return ArchivoPublico::query()
            ->where('negocio_id', $negocio->id)
            ->where('modulo', ArchivoPublico::MODULO_PAGINAS)
            ->whereIn('uuid', array_keys($uuids))
            ->get()
            ->keyBy('uuid')
            ->all();
    }
}
