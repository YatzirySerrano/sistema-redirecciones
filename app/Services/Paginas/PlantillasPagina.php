<?php

namespace App\Services\Paginas;

use App\Models\Negocio;

/**
 * Catálogo de plantillas originales de DASTI: diseño completo (tema) y
 * estructura de ejemplo que el negocio edita. Cada plantilla tiene giros
 * (tipos de negocio a los que sirve) y un estilo visual.
 *
 * Reglas del contenido de ejemplo:
 * - Textos claramente demostrativos («Escribe aquí…», «Tu especialidad…»).
 * - Nunca empresas, teléfonos, direcciones, correos ni reseñas reales.
 * - Destinos vacíos: el editor marca lo pendiente antes de publicar.
 * - Sin imágenes externas: las fotos quedan como espacios por llenar.
 *
 * Se validan con DocumentoPagina como cualquier documento.
 */
class PlantillasPagina
{
    /** Giros (tipos de negocio) para filtrar el catálogo. */
    public const GIROS = [
        'cafeteria' => 'Cafeterías',
        'restaurante' => 'Restaurantes',
        'menu' => 'Menús digitales',
        'panaderia' => 'Panaderías',
        'estetica' => 'Estéticas',
        'spa' => 'Spas',
        'barberia' => 'Barberías',
        'gimnasio' => 'Gimnasios',
        'ropa' => 'Tiendas de ropa',
        'floreria' => 'Florerías',
        'agua' => 'Agua y hielo',
        'veterinaria' => 'Veterinarias',
        'consultorio' => 'Consultorios',
        'profesional' => 'Servicios profesionales',
        'despacho' => 'Despachos',
        'agencia' => 'Agencias',
        'inmobiliaria' => 'Inmobiliarias',
        'taller' => 'Talleres mecánicos',
        'fotografia' => 'Fotografía',
        'eventos' => 'Eventos',
        'creador' => 'Creadores de contenido',
        'tecnologia' => 'Tecnología',
        'comercio' => 'Comercio general',
    ];

    /** Estilos visuales. */
    public const ESTILOS = [
        'minimalista' => 'Minimalista',
        'elegante' => 'Elegante',
        'moderno' => 'Moderno',
        'calido' => 'Cálido',
        'natural' => 'Natural',
        'vibrante' => 'Vibrante',
        'oscuro' => 'Oscuro',
        'audaz' => 'Audaz',
        'corporativo' => 'Corporativo',
        'divertido' => 'Divertido',
    ];

    public function __construct(private readonly DocumentoPagina $documento) {}

    /**
     * @return list<array{clave: string, nombre: string, descripcion: string, giros: list<string>, estilo: string, rasgos: list<string>, documento: array<string, mixed>}>
     */
    public function todas(Negocio $negocio, string $modo = DocumentoPagina::MODO_TOLERANTE): array
    {
        $plantillas = [];

        foreach ($this->definiciones() as $clave => $p) {
            $plantillas[] = [
                'clave' => $clave,
                'nombre' => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'giros' => $p['giros'],
                'estilo' => $p['estilo'],
                'rasgos' => $p['rasgos'],
                'documento' => $this->documento->sanear($this->entrada($p, $negocio), $negocio, $modo),
            ];
        }

        return $plantillas;
    }

    /**
     * Documento de entrada (antes de sanear) de cada plantilla; lo usan las
     * pruebas para comprobar que el saneamiento no pierde campos.
     *
     * @return array<string, array<string, mixed>>
     */
    public function entradas(Negocio $negocio): array
    {
        return array_map(fn (array $p) => $this->entrada($p, $negocio), $this->definiciones());
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function entrada(array $p, Negocio $negocio): array
    {
        return [
            'perfil' => ['nombre' => $negocio->nombre_comercial, 'descripcion' => $p['bio']],
            'tema' => array_replace_recursive(DocumentoPagina::temaPorDefecto(), $p['tema']),
            'paginas' => [['titulo' => 'Inicio', 'bloques' => $p['bloques']]],
        ];
    }

    /**
     * @return array<string, array{nombre: string, descripcion: string, giros: list<string>, estilo: string, rasgos: list<string>, bio: string, tema: array<string, mixed>, bloques: list<array<string, mixed>>}>
     */
    private function definiciones(): array
    {
        // ---- Piezas de contenido de ejemplo ----
        $boton = fn (string $titulo, string $icono = 'enlace', array $extra = []) => ['tipo' => 'enlace', 'titulo' => $titulo, 'icono' => $icono, 'url' => '', ...$extra];
        $whatsapp = fn (string $titulo = 'Escríbenos por WhatsApp', array $extra = []) => ['tipo' => 'whatsapp', 'titulo' => $titulo, 'icono' => 'red:whatsapp', 'telefono' => '', 'mensaje' => 'Hola, vi tu página y quiero más información.', ...$extra];
        $telefono = fn (string $titulo = 'Llámanos', array $extra = []) => ['tipo' => 'telefono', 'titulo' => $titulo, 'icono' => 'telefono', 'telefono' => '', ...$extra];
        $correo = fn (string $titulo = 'Escríbenos un correo', array $extra = []) => ['tipo' => 'correo', 'titulo' => $titulo, 'icono' => 'correo', 'email' => '', ...$extra];
        $redes = fn (array $redes, string $estilo = 'iconos', string $tamano = 'normal') => ['tipo' => 'redes', 'estilo' => $estilo, 'tamano' => $tamano, 'elementos' => array_map(fn ($r) => ['red' => $r, 'valor' => ''], $redes)];
        $titulo = fn (string $texto, string $tamano = 'mediano') => ['tipo' => 'titulo', 'texto' => $texto, 'tamano' => $tamano];
        $texto = fn (string $texto, string $tamano = 'normal') => ['tipo' => 'texto', 'texto' => $texto, 'tamano' => $tamano];
        $tarjeta = fn (string $titulo, string $texto, array $extra = []) => ['tipo' => 'tarjeta', 'titulo' => $titulo, 'texto' => $texto, ...$extra];
        $grupo = fn (string $titulo, array $bloques, string $disposicion = 'cuadricula', int $columnas = 2, array $extra = []) => ['tipo' => 'grupo', 'titulo' => $titulo, 'disposicion' => $disposicion, 'columnas' => $columnas, 'bloques' => $bloques, ...$extra];
        $foto = fn (string $alt, string $proporcion = 'cuadrada') => ['tipo' => 'imagen', 'alt' => $alt, 'proporcion' => $proporcion];
        $separador = fn (string $estilo = 'linea', int $alto = 24) => ['tipo' => 'separador', 'estilo' => $estilo, 'alto' => $alto];
        $mapa = fn (string $titulo = 'Visítanos') => ['tipo' => 'mapa', 'titulo' => $titulo, 'direccion' => '', 'mostrar_mapa' => true];
        $contacto = fn (string $titulo, string $descripcion) => ['tipo' => 'contacto', 'titulo' => $titulo, 'descripcion' => $descripcion];
        $horario = "Lunes a viernes: escribe aquí tu horario\nSábado y domingo: escribe aquí tu horario";

        // ---- Paleta completa (14 colores) ----
        $paleta = fn (string $fondo, string $texto, string $titulos, string $suave, string $botonFondo, string $botonTexto, string $botonBorde, string $hoverFondo, string $hoverTexto, string $tarjetaFondo, string $tarjetaTexto, string $borde, string $acento, ?string $enlaces = null) => [
            'fondo' => $fondo, 'texto' => $texto, 'titulos' => $titulos, 'texto_suave' => $suave, 'enlaces' => $enlaces ?? $titulos,
            'boton_fondo' => $botonFondo, 'boton_texto' => $botonTexto, 'boton_borde' => $botonBorde,
            'boton_hover_fondo' => $hoverFondo, 'boton_hover_texto' => $hoverTexto,
            'tarjeta_fondo' => $tarjetaFondo, 'tarjeta_texto' => $tarjetaTexto, 'borde' => $borde, 'acento' => $acento,
        ];
        $degradado = function (int $angulo, string ...$colores): array {
            $paradas = [];
            $ultimo = max(1, count($colores) - 1);

            foreach (array_values($colores) as $i => $color) {
                $paradas[] = ['color' => $color, 'posicion' => (int) round($i * 100 / $ultimo)];
            }

            return ['tipo' => 'degradado', 'angulo' => $angulo, 'paradas' => $paradas];
        };
        $textura = fn (string $textura, string $color) => ['tipo' => 'textura', 'textura' => $textura, 'color_textura' => $color];

        return [
            'barra-de-cafe' => [
                'nombre' => 'Barra de café',
                'giros' => ['cafeteria', 'panaderia', 'comercio'],
                'estilo' => 'calido',
                'descripcion' => 'Banda color espresso, monograma y accesos en mosaico.',
                'rasgos' => ['Encabezado con banda de color y monograma', 'Mosaico de dos columnas', 'Botones píldora con ícono en círculo', 'Títulos tipo etiqueta'],
                'bio' => 'Escribe aquí lo que hace especial a tu café: origen, tostado o tu bebida de la casa.',
                'tema' => [
                    'colores' => $paleta('#f6efe6', '#3b2a20', '#2a1c14', '#7a6455', '#ffffff', '#2a1c14', '#e6d8c8', '#efe2d2', '#2a1c14', '#ffffff', '#3b2a20', '#e6d8c8', '#6f4428'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'dm-sans', 'fuente_titulos' => 'fraunces', 'peso_titulos' => 600, 'escala_titulos' => 135],
                    'botones' => ['estilo' => 'solido', 'radio' => 40, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'elevar', 'icono_fondo' => 'suave'],
                    'disposicion' => ['encabezado' => 'banda', 'monograma' => true, 'titulos' => 'etiqueta', 'tarjetas' => 'sombra'],
                ],
                'bloques' => [
                    $titulo('Para empezar el día', 'pequeno'),
                    $grupo('', [$boton('Bebidas', 'cafe'), $boton('Pan del día', 'hoja'), $boton('Para llevar', 'etiqueta'), $boton('Promociones', 'descuento')]),
                    $whatsapp('Pide para recoger'),
                    $tarjeta('Horario', $horario),
                    $mapa('Encuéntranos'),
                    $redes(['instagram', 'facebook', 'tiktok']),
                ],
            ],
            'carta-digital' => [
                'nombre' => 'Carta digital',
                'giros' => ['menu', 'restaurante', 'cafeteria'],
                'estilo' => 'elegante',
                'descripcion' => 'Menú por secciones con ornamentos, letra editorial y lista de platillos.',
                'rasgos' => ['Encabezado editorial alineado a la izquierda', 'Secciones del menú con línea', 'Separadores con ornamento', 'Botones subrayados tipo lista'],
                'bio' => 'Nuestra carta. Escribe aquí una frase sobre tu cocina.',
                'tema' => [
                    'colores' => $paleta('#fbf8f1', '#2e2a24', '#1d1a16', '#6e675c', '#fbf8f1', '#1d1a16', '#bfb39d', '#e3d6bd', '#1d1a16', '#ffffff', '#2e2a24', '#d9cfbd', '#8a5a2b'),
                    'fondo' => $textura('lineas', '#f1ebdd'),
                    'tipografia' => ['fuente_texto' => 'work-sans', 'fuente_titulos' => 'dm-serif-display', 'peso_titulos' => 500, 'peso_texto' => 400, 'escala_titulos' => 150, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'subrayado', 'radio' => 0, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'alineacion' => 'izquierda', 'icono' => 'derecha'],
                    'disposicion' => ['encabezado' => 'editorial', 'titulos' => 'linea', 'tarjetas' => 'plana', 'espaciado' => 'amplio'],
                ],
                'bloques' => [
                    $titulo('Entradas'),
                    $texto("**Tu especialidad de la casa** · $000\nEscribe aquí una descripción breve del platillo.\n\n**Otro platillo** · $000\nIngredientes principales o forma de preparación."),
                    $separador('ornamento', 40),
                    $titulo('Platos fuertes'),
                    $texto("**Platillo principal** · $000\nDescribe porciones, guarniciones o picor.\n\n**Opción vegetariana** · $000\nEscribe aquí la descripción."),
                    $separador('ornamento', 40),
                    $titulo('Postres y bebidas'),
                    $texto("**Postre de la casa** · $000\n**Bebida de temporada** · $000"),
                    $texto('_Precios en pesos mexicanos. Escribe aquí avisos sobre alérgenos o disponibilidad._', 'pequeno'),
                    $boton('Menú en PDF', 'documento'),
                    $whatsapp('Pedidos por WhatsApp'),
                ],
            ],
            'bistro-nocturno' => [
                'nombre' => 'Bistró nocturno',
                'giros' => ['restaurante', 'eventos', 'menu'],
                'estilo' => 'oscuro',
                'descripcion' => 'Fondo oscuro con resplandor cálido, tarjeta flotante y botones de cristal.',
                'rasgos' => ['Encabezado en tarjeta flotante', 'Fondo con resplandor (aurora)', 'Botones de cristal con ícono a la derecha', 'Redes como botones'],
                'bio' => 'Cocina de autor y buena música. Escribe aquí tu propuesta.',
                'tema' => [
                    'colores' => $paleta('#120d0b', '#eadfd6', '#fff4e8', '#b9a89a', '#ffffff', '#fff4e8', '#ffffff', '#3a2c25', '#ffffff', '#1d1512', '#eadfd6', '#3a2c25', '#e0a458'),
                    'fondo' => $textura('aurora', '#5a2a14'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'playfair-display', 'peso_titulos' => 700, 'escala_titulos' => 140, 'peso_texto' => 300],
                    'botones' => ['estilo' => 'vidrio', 'radio' => 16, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'brillo', 'icono' => 'derecha'],
                    'disposicion' => ['encabezado' => 'tarjeta', 'monograma' => true, 'forma_foto' => 'redondeada', 'tarjetas' => 'sombra'],
                ],
                'bloques' => [
                    $whatsapp('Aparta tu mesa por mensaje', ['destacado' => true]),
                    $boton('Menú de temporada', 'menu'),
                    $boton('Carta de vinos y cocteles', 'estrella'),
                    $tarjeta('Esta semana', 'Escribe aquí tu noche especial, música en vivo o el platillo invitado.'),
                    $mapa('Dónde estamos'),
                    $redes(['instagram', 'facebook', 'spotify'], 'botones'),
                ],
            ],
            'estudio-de-belleza' => [
                'nombre' => 'Estudio de belleza',
                'giros' => ['estetica', 'spa', 'barberia'],
                'estilo' => 'elegante',
                'descripcion' => 'Rosa empolvado, letras finas y botones de contorno en mayúsculas.',
                'rasgos' => ['Encabezado centrado con monograma', 'Degradado rosa suave', 'Botones de contorno en mayúsculas', 'Títulos con barra de acento'],
                'bio' => 'Escribe aquí tu especialidad: color, peinados, uñas o cuidado de la piel.',
                'tema' => [
                    'colores' => $paleta('#f8eeeb', '#4a3633', '#36231f', '#74605a', '#7a4b45', '#5a3430', '#b58a82', '#e9c9c1', '#36231f', '#fffaf8', '#4a3633', '#ead6d0', '#b06f66'),
                    'fondo' => $degradado(180, '#fbf3f0', '#f1dfd9'),
                    'tipografia' => ['fuente_texto' => 'nunito', 'fuente_titulos' => 'cormorant-garamond', 'peso_titulos' => 600, 'escala_titulos' => 160, 'espaciado' => 1],
                    'botones' => ['estilo' => 'contorno', 'radio' => 40, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'elevar', 'mayusculas' => true, 'icono' => 'oculto'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'titulos' => 'barra', 'ancho' => 'estrecho'],
                ],
                'bloques' => [
                    $titulo('Servicios'),
                    $grupo('', [$boton('Corte y peinado', 'tijeras'), $boton('Color', 'paleta'), $boton('Uñas', 'brillo'), $boton('Maquillaje', 'estrella')], 'lista'),
                    $whatsapp('Pide tu cita por mensaje'),
                    $separador('ornamento', 36),
                    $texto('_Escribe aquí una frase que describa la experiencia en tu estudio._'),
                    $redes(['instagram', 'tiktok', 'pinterest']),
                ],
            ],
            'spa-sereno' => [
                'nombre' => 'Spa sereno',
                'giros' => ['spa', 'estetica', 'consultorio'],
                'estilo' => 'natural',
                'descripcion' => 'Verde salvia, encabezado dividido y mucho aire entre elementos.',
                'rasgos' => ['Encabezado dividido (texto y foto)', 'Textura de ondas tenue', 'Botones suaves con ícono en recuadro sólido', 'Espaciado amplio y tarjetas planas'],
                'bio' => 'Un espacio para descansar. Escribe aquí qué tratamientos ofreces.',
                'tema' => [
                    'colores' => $paleta('#eef1ea', '#33402f', '#243021', '#5a6853', '#8aa37b', '#243021', '#8aa37b', '#9fb592', '#243021', '#f8faf5', '#33402f', '#d6dfcf', '#56704a'),
                    'fondo' => $textura('ondas', '#e1e8da'),
                    'tipografia' => ['fuente_texto' => 'manrope', 'fuente_titulos' => 'lora', 'peso_titulos' => 500, 'escala_titulos' => 140],
                    'botones' => ['estilo' => 'suave', 'radio' => 12, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'icono_fondo' => 'solido', 'alineacion' => 'izquierda'],
                    'disposicion' => ['encabezado' => 'dividido', 'monograma' => true, 'forma_foto' => 'redondeada', 'espaciado' => 'amplio', 'ancho' => 'estrecho', 'tarjetas' => 'plana'],
                ],
                'bloques' => [
                    $tarjeta('Rituales de la casa', 'Escribe aquí tu tratamiento estrella y cuánto dura.'),
                    $grupo('Tratamientos', [$boton('Masajes', 'hoja'), $boton('Faciales', 'brillo'), $boton('Paquetes', 'regalo'), $boton('Tarjetas de regalo', 'regalo')]),
                    $texto('Escribe aquí recomendaciones para tu visita.', 'pequeno'),
                    $whatsapp('Consulta disponibilidad'),
                    $mapa('Cómo llegar'),
                ],
            ],
            'barberia-clasica' => [
                'nombre' => 'Barbería clásica',
                'giros' => ['barberia', 'estetica'],
                'estilo' => 'audaz',
                'descripcion' => 'Carbón y rayas diagonales, botones rectos con sombra sólida.',
                'rasgos' => ['Encabezado con foto al lado', 'Patrón de rayas diagonales', 'Botones rectos con sombra dura en mayúsculas', 'Títulos resaltados'],
                'bio' => 'Cortes clásicos y modernos. Escribe aquí tu estilo.',
                'tema' => [
                    'colores' => $paleta('#1c1c1e', '#e7e2da', '#ffffff', '#a8a29a', '#f2ede4', '#1c1c1e', '#c8102e', '#ffffff', '#1c1c1e', '#26262a', '#e7e2da', '#3a3a40', '#c8102e'),
                    'fondo' => $textura('diagonales', '#26262a'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'josefin-sans', 'peso_titulos' => 700, 'escala_titulos' => 145, 'espaciado' => 2, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'solido', 'radio' => 0, 'sombra' => 'marcada', 'borde' => 2, 'animacion' => 'elevar', 'mayusculas' => true, 'alineacion' => 'izquierda'],
                    'disposicion' => ['encabezado' => 'izquierda', 'monograma' => true, 'forma_foto' => 'cuadrada', 'titulos' => 'resaltado'],
                ],
                'bloques' => [
                    $titulo('Servicios'),
                    $grupo('', [
                        $boton('Corte clásico', 'tijeras', ['subtitulo' => 'Escribe aquí el precio']),
                        $boton('Barba y toalla caliente', 'brillo', ['subtitulo' => 'Escribe aquí el precio']),
                        $boton('Corte y barba', 'estrella', ['subtitulo' => 'Escribe aquí el precio']),
                    ], 'lista'),
                    $whatsapp('Aparta tu turno'),
                    $tarjeta('Horario', $horario),
                    $redes(['instagram', 'tiktok', 'facebook'], 'iconos', 'grande'),
                    $mapa('La barbería'),
                ],
            ],
            'energia-total' => [
                'nombre' => 'Energía total',
                'giros' => ['gimnasio', 'eventos'],
                'estilo' => 'vibrante',
                'descripcion' => 'Negro con verde lima, títulos gigantes y botones en degradado.',
                'rasgos' => ['Encabezado editorial con título grande', 'Patrón de cruces', 'Botones con degradado', 'Mosaico de tres columnas'],
                'bio' => 'Entrena a tu ritmo. Escribe aquí tus disciplinas.',
                'tema' => [
                    'colores' => $paleta('#0d0f0c', '#dfe6d8', '#ffffff', '#9aa592', '#c6f432', '#0d0f0c', '#c6f432', '#d8ff5c', '#0d0f0c', '#171a15', '#dfe6d8', '#2a3025', '#3ddc84'),
                    'fondo' => $textura('cruces', '#1f241b'),
                    'tipografia' => ['fuente_texto' => 'sora', 'fuente_titulos' => 'space-grotesk', 'peso_titulos' => 700, 'escala_titulos' => 165, 'espaciado' => -2, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'degradado', 'radio' => 8, 'sombra' => 'media', 'borde' => 0, 'animacion' => 'pulso', 'alineacion' => 'izquierda', 'icono' => 'derecha'],
                    'disposicion' => ['encabezado' => 'editorial', 'titulos' => 'etiqueta', 'tarjetas' => 'borde'],
                ],
                'bloques' => [
                    $titulo('Sin excusas', 'grande'),
                    $texto('Escribe aquí qué te hace diferente: clases, equipo o entrenadores.'),
                    $grupo('', [$boton('Horarios', 'reloj'), $boton('Clases', 'calendario'), $boton('Planes', 'etiqueta')], 'cuadricula', 3),
                    $whatsapp('Pide tu clase de prueba', ['destacado' => true]),
                    $tarjeta('Promoción del mes', 'Escribe aquí tu promoción vigente y sus condiciones.'),
                    $redes(['instagram', 'tiktok', 'youtube']),
                ],
            ],
            'boutique' => [
                'nombre' => 'Boutique',
                'giros' => ['ropa', 'comercio', 'floreria'],
                'estilo' => 'minimalista',
                'descripcion' => 'Galería de fotos rectas, mayúsculas espaciadas y botones de línea fina.',
                'rasgos' => ['Galería de fotos en cuadrícula', 'Imágenes con esquinas rectas', 'Botones de contorno sin redondeo en mayúsculas', 'Ancho amplio'],
                'bio' => 'Nueva colección disponible. Escribe aquí tu estilo.',
                'tema' => [
                    'colores' => $paleta('#f7f6f3', '#232220', '#111110', '#6a675f', '#111110', '#111110', '#111110', '#cfccc4', '#111110', '#ffffff', '#232220', '#e3e0d9', '#111110'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'work-sans', 'fuente_titulos' => 'libre-baskerville', 'peso_titulos' => 500, 'escala_titulos' => 130, 'espaciado' => 3],
                    'botones' => ['estilo' => 'contorno', 'radio' => 0, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'mayusculas' => true, 'icono' => 'oculto', 'tamano' => 'compacto'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'forma_foto' => 'cuadrada', 'forma_imagen' => 'recta', 'ancho' => 'amplio', 'titulos' => 'linea'],
                ],
                'bloques' => [
                    $grupo('Nueva colección', [$foto('Prenda destacada 1', 'vertical'), $foto('Prenda destacada 2', 'vertical'), $foto('Prenda destacada 3', 'vertical'), $foto('Prenda destacada 4', 'vertical')]),
                    $boton('Ver catálogo completo', 'etiqueta', ['destacado' => true]),
                    $whatsapp('Pregunta por tallas'),
                    $boton('Cambios y envíos', 'documento'),
                    $redes(['instagram', 'tiktok', 'pinterest']),
                ],
            ],
            'jardin-de-flores' => [
                'nombre' => 'Jardín de flores',
                'giros' => ['floreria', 'eventos', 'comercio'],
                'estilo' => 'natural',
                'descripcion' => 'Foto en arco, puntos rosados y tarjeta flotante.',
                'rasgos' => ['Encabezado en tarjeta flotante', 'Imagen principal en forma de arco', 'Fondo de puntos', 'Botones redondeados con ícono en círculo suave'],
                'bio' => 'Arreglos hechos a mano. Escribe aquí para qué ocasiones.',
                'tema' => [
                    'colores' => $paleta('#fbf1f2', '#4b3439', '#3a2328', '#86686e', '#ffffff', '#3a2328', '#f0d5da', '#fbe5e9', '#3a2328', '#ffffff', '#4b3439', '#f0d5da', '#c2566b'),
                    'fondo' => $textura('puntos', '#f3d9de'),
                    'tipografia' => ['fuente_texto' => 'nunito', 'fuente_titulos' => 'fraunces', 'peso_titulos' => 600, 'escala_titulos' => 140],
                    'botones' => ['estilo' => 'solido', 'radio' => 18, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'elevar', 'icono_fondo' => 'suave'],
                    'disposicion' => ['encabezado' => 'tarjeta', 'monograma' => true, 'forma_imagen' => 'arco', 'tarjetas' => 'sombra'],
                ],
                'bloques' => [
                    $foto('Tu arreglo más bonito', 'vertical'),
                    $titulo('Arreglos para cada ocasión'),
                    $grupo('', [$boton('Ramos', 'hoja'), $boton('Para eventos', 'corazon'), $boton('Entrega semanal', 'calendario')], 'lista'),
                    $whatsapp('Encarga tu arreglo'),
                    $tarjeta('Entregas', 'Escribe aquí las zonas y horarios de entrega.'),
                    $redes(['instagram', 'facebook', 'pinterest']),
                ],
            ],
            'horno-artesanal' => [
                'nombre' => 'Horno artesanal',
                'giros' => ['panaderia', 'cafeteria', 'comercio'],
                'estilo' => 'calido',
                'descripcion' => 'Mantel de cuadros, letra redonda y botones con sombra sólida.',
                'rasgos' => ['Encabezado con monograma al lado', 'Fondo de cuadros tipo mantel', 'Botones con sombra dura y borde grueso', 'Separador en zigzag'],
                'bio' => 'Pan recién horneado todos los días. Escribe aquí tu especialidad.',
                'tema' => [
                    'colores' => $paleta('#fff8e7', '#3d2b12', '#2b1d08', '#7a6440', '#ffd27a', '#2b1d08', '#2b1d08', '#ffdc94', '#2b1d08', '#ffffff', '#3d2b12', '#2b1d08', '#c8551f'),
                    'fondo' => $textura('cuadros', '#f9e3b4'),
                    'tipografia' => ['fuente_texto' => 'dm-sans', 'fuente_titulos' => 'bricolage-grotesque', 'peso_titulos' => 800, 'escala_titulos' => 140],
                    'botones' => ['estilo' => 'solido', 'radio' => 14, 'sombra' => 'marcada', 'borde' => 2, 'animacion' => 'elevar'],
                    'disposicion' => ['encabezado' => 'izquierda', 'monograma' => true, 'forma_foto' => 'redondeada', 'titulos' => 'barra'],
                ],
                'bloques' => [
                    $tarjeta('Recién salido del horno', 'Escribe aquí el pan del día o tu especialidad de temporada.'),
                    $titulo('Pedidos'),
                    $whatsapp('Encarga tu pan'),
                    $boton('Pasteles por encargo', 'regalo'),
                    $boton('Lista de precios', 'documento'),
                    $separador('zigzag', 36),
                    $mapa('Nuestra panadería'),
                    $redes(['facebook', 'instagram']),
                ],
            ],
            'agua-fresca' => [
                'nombre' => 'Agua fresca',
                'giros' => ['agua', 'comercio'],
                'estilo' => 'moderno',
                'descripcion' => 'Azul limpio, botones grandes para pedir y zonas de reparto.',
                'rasgos' => ['Banda superior en azul', 'Degradado cielo', 'Botones píldora grandes con ícono en círculo sólido', 'Pedido como acción principal'],
                'bio' => 'Agua purificada y hielo a domicilio. Escribe aquí tu cobertura.',
                'tema' => [
                    'colores' => $paleta('#eef7fc', '#17384d', '#0b2a3d', '#4f6f83', '#0b78b8', '#ffffff', '#0b78b8', '#0a6aa3', '#ffffff', '#ffffff', '#17384d', '#cfe5f2', '#0b78b8'),
                    'fondo' => $degradado(180, '#e6f4fc', '#f7fbfe'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'poppins', 'peso_titulos' => 700, 'escala_titulos' => 130],
                    'botones' => ['estilo' => 'solido', 'radio' => 40, 'sombra' => 'media', 'borde' => 0, 'animacion' => 'elevar', 'tamano' => 'grande', 'icono_fondo' => 'suave'],
                    'disposicion' => ['encabezado' => 'banda', 'monograma' => true, 'tarjetas' => 'sombra', 'titulos' => 'etiqueta'],
                ],
                'bloques' => [
                    $whatsapp('Pide tu garrafón', ['destacado' => true, 'tamano' => 'grande']),
                    $telefono('Pedidos por teléfono'),
                    $titulo('Productos', 'pequeno'),
                    $grupo('', [$boton('Garrafón', 'etiqueta'), $boton('Hielo', 'brillo')]),
                    $tarjeta('Zonas de reparto', 'Escribe aquí las colonias o zonas a las que llegas y tu horario de reparto.'),
                    $redes(['facebook', 'whatsapp']),
                ],
            ],
            'patitas' => [
                'nombre' => 'Patitas',
                'giros' => ['veterinaria', 'comercio'],
                'estilo' => 'divertido',
                'descripcion' => 'Menta y puntos, botones redondos con íconos y tarjetas con sombra.',
                'rasgos' => ['Encabezado centrado con monograma', 'Mosaico de servicios con íconos en círculo sólido', 'Botones suaves muy redondeados', 'Tarjetas con sombra'],
                'bio' => 'Cuidamos a tu mascota como de la familia. Escribe aquí tus servicios.',
                'tema' => [
                    'colores' => $paleta('#ecfaf5', '#21433a', '#12332a', '#4a6c62', '#2fae8a', '#12332a', '#2fae8a', '#5cc7a7', '#12332a', '#ffffff', '#21433a', '#c9eee1', '#1f8f70'),
                    'fondo' => $textura('puntos', '#cdeee2'),
                    'tipografia' => ['fuente_texto' => 'nunito', 'fuente_titulos' => 'nunito', 'peso_titulos' => 800, 'escala_titulos' => 135],
                    'botones' => ['estilo' => 'suave', 'radio' => 22, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'pulso', 'icono_fondo' => 'solido'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'tarjetas' => 'sombra', 'titulos' => 'simple'],
                ],
                'bloques' => [
                    $titulo('¿Cómo podemos ayudar?'),
                    $grupo('', [$boton('Consulta', 'pata'), $boton('Vacunas', 'salud'), $boton('Estética', 'tijeras'), $telefono('Urgencias')]),
                    $whatsapp('Escríbenos'),
                    $tarjeta('Horario', $horario),
                    $mapa('La clínica'),
                    $redes(['facebook', 'instagram']),
                ],
            ],
            'consultorio' => [
                'nombre' => 'Consultorio',
                'giros' => ['consultorio', 'profesional', 'veterinaria'],
                'estilo' => 'corporativo',
                'descripcion' => 'Blanco y turquesa, encabezado dividido y formulario de contacto.',
                'rasgos' => ['Encabezado dividido', 'Botones alineados a la izquierda con ícono en recuadro', 'Títulos con línea', 'Formulario de contacto al final'],
                'bio' => 'Escribe aquí tu especialidad y tu cédula profesional.',
                'tema' => [
                    'colores' => $paleta('#ffffff', '#334155', '#0f172a', '#64748b', '#f1f8f8', '#0f3d3e', '#cfe6e6', '#e2f1f1', '#0f3d3e', '#f8fafc', '#334155', '#e2e8f0', '#0f766e'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'plus-jakarta-sans', 'fuente_titulos' => 'plus-jakarta-sans', 'peso_titulos' => 700, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'solido', 'radio' => 10, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'alineacion' => 'izquierda', 'icono_fondo' => 'solido'],
                    'disposicion' => ['encabezado' => 'dividido', 'monograma' => true, 'forma_foto' => 'circulo', 'titulos' => 'linea'],
                ],
                'bloques' => [
                    $tarjeta('Atención', 'Escribe aquí qué tratas, a quién atiendes y si das consulta en línea.'),
                    $titulo('Contacto', 'pequeno'),
                    $telefono('Llamar al consultorio'),
                    $whatsapp('Enviar un mensaje'),
                    $correo('Escribir un correo'),
                    $mapa('Ubicación del consultorio'),
                    $contacto('Déjanos tus datos', 'Te responderemos lo antes posible.'),
                ],
            ],
            'despacho' => [
                'nombre' => 'Despacho',
                'giros' => ['despacho', 'profesional', 'inmobiliaria'],
                'estilo' => 'elegante',
                'descripcion' => 'Marfil y azul marino, letra clásica y áreas desplegables.',
                'rasgos' => ['Encabezado editorial', 'Áreas en sección desplegable', 'Botones de contorno finos con flecha', 'Títulos tipo etiqueta'],
                'bio' => 'Asesoría clara y confiable. Escribe aquí tus áreas de práctica.',
                'tema' => [
                    'colores' => $paleta('#f8f6f0', '#2b3140', '#141b2d', '#646b7a', '#f8f6f0', '#141b2d', '#141b2d', '#d6cfbd', '#141b2d', '#ffffff', '#2b3140', '#ddd8cc', '#9c7a3c'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'libre-baskerville', 'peso_titulos' => 600, 'escala_titulos' => 135, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'contorno', 'radio' => 2, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'alineacion' => 'izquierda', 'icono' => 'derecha'],
                    'disposicion' => ['encabezado' => 'editorial', 'titulos' => 'etiqueta', 'tarjetas' => 'borde'],
                ],
                'bloques' => [
                    $texto('Escribe aquí una presentación breve del despacho y tu forma de trabajar.', 'grande'),
                    $titulo('Áreas de práctica', 'pequeno'),
                    $grupo('Conoce nuestras áreas', [$boton('Área de práctica 1', 'documento'), $boton('Área de práctica 2', 'documento'), $boton('Área de práctica 3', 'documento')], 'lista', 2, ['plegable' => true, 'abierto' => true]),
                    $correo('Solicitar asesoría'),
                    $telefono('Llamar al despacho'),
                    $contacto('Cuéntanos tu caso', 'La información que nos compartas es confidencial.'),
                    $redes(['linkedin', 'sitio']),
                ],
            ],
            'estudio-creativo' => [
                'nombre' => 'Estudio creativo',
                'giros' => ['agencia', 'fotografia', 'creador'],
                'estilo' => 'audaz',
                'descripcion' => 'Amarillo intenso, bordes gruesos, sombras sólidas y galería.',
                'rasgos' => ['Encabezado dividido', 'Patrón de cruces', 'Botones rectos con borde grueso y sombra dura', 'Títulos resaltados en bloque de color'],
                'bio' => 'Diseño, contenido y estrategia. Escribe aquí lo que hace tu equipo.',
                'tema' => [
                    'colores' => $paleta('#ffe14d', '#16130a', '#16130a', '#4a4223', '#ffffff', '#16130a', '#16130a', '#16130a', '#ffe14d', '#ffffff', '#16130a', '#16130a', '#ff5a36'),
                    'fondo' => $textura('cruces', '#f2cf2a'),
                    'tipografia' => ['fuente_texto' => 'space-grotesk', 'fuente_titulos' => 'bricolage-grotesque', 'peso_titulos' => 800, 'escala_titulos' => 160, 'espaciado' => -2, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'solido', 'radio' => 0, 'sombra' => 'marcada', 'borde' => 3, 'animacion' => 'elevar', 'alineacion' => 'izquierda', 'icono' => 'derecha', 'mayusculas' => true],
                    'disposicion' => ['encabezado' => 'dividido', 'monograma' => true, 'forma_foto' => 'cuadrada', 'titulos' => 'resaltado', 'forma_imagen' => 'marco'],
                ],
                'bloques' => [
                    $titulo('Hacemos que tu marca se note', 'grande'),
                    $grupo('Proyectos', [$foto('Proyecto 1'), $foto('Proyecto 2'), $foto('Proyecto 3'), $foto('Proyecto 4')]),
                    $boton('Portafolio completo', 'paleta', ['destacado' => true]),
                    $boton('Servicios y tarifas', 'maletin'),
                    $correo('Cuéntanos tu proyecto'),
                    $redes(['instagram', 'linkedin', 'youtube']),
                ],
            ],
            'hogar-ideal' => [
                'nombre' => 'Hogar ideal',
                'giros' => ['inmobiliaria', 'despacho', 'comercio'],
                'estilo' => 'corporativo',
                'descripcion' => 'Azul profundo con dorado, banda superior y propiedad destacada.',
                'rasgos' => ['Banda superior en azul profundo', 'Propiedad destacada en tarjeta con sombra', 'Botones con ícono en recuadro suave', 'Formulario para propietarios'],
                'bio' => 'Te ayudamos a comprar, vender o rentar. Escribe aquí tu zona.',
                'tema' => [
                    'colores' => $paleta('#f4f5f8', '#2a3142', '#121a2e', '#5f6778', '#ffffff', '#121a2e', '#dde1ea', '#eef0f5', '#121a2e', '#ffffff', '#2a3142', '#dde1ea', '#1b2a4e'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'manrope', 'fuente_titulos' => 'dm-serif-display', 'peso_titulos' => 500, 'escala_titulos' => 145],
                    'botones' => ['estilo' => 'solido', 'radio' => 6, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'elevar', 'icono_fondo' => 'suave', 'alineacion' => 'izquierda', 'icono' => 'izquierda'],
                    'disposicion' => ['encabezado' => 'banda', 'forma_foto' => 'redondeada', 'monograma' => true, 'tarjetas' => 'sombra', 'titulos' => 'barra'],
                ],
                'bloques' => [
                    $tarjeta('Propiedad destacada', "**Recámaras · Baños · m²**\nEscribe aquí la descripción de la propiedad y su precio.", ['boton' => 'Ver detalles']),
                    $titulo('Encuentra tu espacio'),
                    $boton('Propiedades en venta', 'casa'),
                    $boton('Propiedades en renta', 'edificio'),
                    $whatsapp('Pregunta por una propiedad'),
                    $contacto('¿Quieres vender o rentar?', 'Déjanos tus datos y te contactamos.'),
                    $redes(['facebook', 'instagram', 'youtube']),
                ],
            ],
            'taller' => [
                'nombre' => 'Taller',
                'giros' => ['taller', 'comercio', 'tecnologia'],
                'estilo' => 'audaz',
                'descripcion' => 'Gris grafito con naranja, botones industriales y garantía.',
                'rasgos' => ['Encabezado con foto al lado', 'Fondo de cuadrícula técnica', 'Botones rectos con ícono en recuadro naranja', 'Títulos con barra'],
                'bio' => 'Mecánica general y diagnóstico. Escribe aquí tus especialidades.',
                'tema' => [
                    'colores' => $paleta('#1f2226', '#d9dde2', '#ffffff', '#9aa1a9', '#2a2e33', '#f2f4f6', '#3b4047', '#353a40', '#ffffff', '#2a2e33', '#d9dde2', '#3b4047', '#f97316'),
                    'fondo' => $textura('cuadricula', '#262a2f'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'space-grotesk', 'peso_titulos' => 700, 'escala_titulos' => 140, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'solido', 'radio' => 4, 'sombra' => 'ninguna', 'borde' => 2, 'animacion' => 'ninguna', 'alineacion' => 'izquierda', 'icono_fondo' => 'solido'],
                    'disposicion' => ['encabezado' => 'izquierda', 'monograma' => true, 'forma_foto' => 'cuadrada', 'titulos' => 'barra', 'espaciado' => 'compacto'],
                ],
                'bloques' => [
                    $whatsapp('Cotiza por WhatsApp', ['destacado' => true]),
                    $telefono('Llamar al taller'),
                    $titulo('Servicios'),
                    $grupo('', [$boton('Afinación', 'auto'), $boton('Frenos', 'escudo'), $boton('Diagnóstico', 'info'), $boton('Hojalatería y pintura', 'paleta')], 'lista'),
                    $tarjeta('Garantía', 'Escribe aquí la garantía que ofreces en tus servicios.'),
                    $mapa('El taller'),
                ],
            ],
            'portafolio' => [
                'nombre' => 'Portafolio',
                'giros' => ['fotografia', 'creador', 'eventos'],
                'estilo' => 'oscuro',
                'descripcion' => 'Negro absoluto, galería de tres columnas y botones subrayados.',
                'rasgos' => ['Galería de seis fotos en tres columnas', 'Foto de perfil cuadrada', 'Botones subrayados en mayúsculas', 'Ancho amplio y letra ligera'],
                'bio' => 'Fotografía de retrato, producto y eventos. Escribe aquí tu estilo.',
                'tema' => [
                    'colores' => $paleta('#0a0a0a', '#d4d4d4', '#fafafa', '#8f8f8f', '#0a0a0a', '#fafafa', '#525252', '#171717', '#ffffff', '#141414', '#d4d4d4', '#262626', '#e5e5e5'),
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'cormorant-garamond', 'peso_titulos' => 500, 'peso_texto' => 300, 'escala_titulos' => 165, 'espaciado' => 4],
                    'botones' => ['estilo' => 'subrayado', 'radio' => 0, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna', 'mayusculas' => true, 'icono' => 'oculto'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'forma_foto' => 'cuadrada', 'forma_imagen' => 'recta', 'ancho' => 'amplio', 'espaciado' => 'compacto'],
                ],
                'bloques' => [
                    $grupo('', [$foto('Fotografía 1'), $foto('Fotografía 2'), $foto('Fotografía 3'), $foto('Fotografía 4'), $foto('Fotografía 5'), $foto('Fotografía 6')], 'cuadricula', 3),
                    $titulo('Sesiones', 'pequeno'),
                    $boton('Retrato', 'camara', ['subtitulo' => 'Escribe aquí qué incluye']),
                    $boton('Producto', 'imagen', ['subtitulo' => 'Escribe aquí qué incluye']),
                    $boton('Eventos', 'calendario', ['subtitulo' => 'Escribe aquí qué incluye']),
                    $correo('Cotiza tu sesión'),
                    $redes(['instagram', 'pinterest', 'sitio']),
                ],
            ],
            'celebraciones' => [
                'nombre' => 'Celebraciones',
                'giros' => ['eventos', 'floreria', 'restaurante'],
                'estilo' => 'vibrante',
                'descripcion' => 'Degradado coral a violeta, tarjeta flotante y botones de cristal.',
                'rasgos' => ['Encabezado en tarjeta flotante', 'Degradado de tres colores', 'Botones de cristal tipo píldora', 'Separador de puntos'],
                'bio' => 'Organizamos tu evento de principio a fin. Escribe aquí qué tipo de eventos haces.',
                'tema' => [
                    'colores' => $paleta('#6d28d9', '#ffffff', '#ffffff', '#f6e8ff', '#ffffff', '#ffffff', '#ffffff', '#2a0f55', '#ffffff', '#ffffff', '#3b1466', '#f1d9ff', '#ffd166'),
                    'fondo' => $degradado(150, '#d63a4f', '#9a2aa6', '#4a22c4'),
                    'tipografia' => ['fuente_texto' => 'outfit', 'fuente_titulos' => 'dm-serif-display', 'peso_titulos' => 500, 'escala_titulos' => 150],
                    'botones' => ['estilo' => 'vidrio', 'radio' => 40, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'brillo'],
                    'disposicion' => ['encabezado' => 'tarjeta', 'monograma' => true, 'titulos' => 'etiqueta'],
                ],
                'bloques' => [
                    $titulo('Hacemos tu evento inolvidable'),
                    $grupo('', [$boton('Bodas', 'corazon'), $boton('XV años', 'estrella'), $boton('Cumpleaños', 'regalo'), $boton('Empresariales', 'maletin')]),
                    $separador('puntos', 32),
                    $tarjeta('Paquetes', 'Escribe aquí qué incluye cada paquete y desde cuánto cuesta.'),
                    $whatsapp('Cotiza tu evento', ['destacado' => true]),
                    $redes(['instagram', 'facebook', 'tiktok']),
                ],
            ],
            'creador' => [
                'nombre' => 'Creador',
                'giros' => ['creador', 'tecnologia', 'comercio'],
                'estilo' => 'vibrante',
                'descripcion' => 'Violeta nocturno con resplandor, redes al frente y botones en degradado.',
                'rasgos' => ['Redes grandes al inicio', 'Fondo con resplandor (aurora)', 'Botones en degradado con pulso', 'Insignia de novedad'],
                'bio' => 'Contenido nuevo cada semana. Escribe aquí de qué hablas.',
                'tema' => [
                    'colores' => $paleta('#140b2e', '#e9e4ff', '#ffffff', '#b3a8e0', '#6d28d9', '#ffffff', '#6d28d9', '#7c3aed', '#ffffff', '#1f1442', '#e9e4ff', '#352866', '#be185d'),
                    'fondo' => $textura('aurora', '#4c1d95'),
                    'tipografia' => ['fuente_texto' => 'outfit', 'fuente_titulos' => 'outfit', 'peso_titulos' => 800, 'escala_titulos' => 140],
                    'botones' => ['estilo' => 'degradado', 'radio' => 18, 'sombra' => 'media', 'borde' => 0, 'animacion' => 'pulso'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'forma_foto' => 'circulo', 'tarjetas' => 'plana'],
                ],
                'bloques' => [
                    $redes(['instagram', 'tiktok', 'youtube', 'x', 'threads'], 'iconos', 'grande'),
                    $boton('Mi último video', 'video', ['destacado' => true, 'insignia' => 'Nuevo']),
                    $boton('Colaboraciones con marcas', 'mensaje'),
                    $boton('Mis recomendaciones', 'etiqueta'),
                    $tarjeta('Kit de medios', 'Escribe aquí tu audiencia y tus formatos de colaboración.', ['boton' => 'Descargar']),
                    $correo('Contacto para marcas'),
                ],
            ],
            'tecnologia' => [
                'nombre' => 'Tecnología',
                'giros' => ['tecnologia', 'agencia', 'profesional'],
                'estilo' => 'moderno',
                'descripcion' => 'Azul noche, panel flotante, cuadrícula técnica y acento cian.',
                'rasgos' => ['Contenido en panel flotante', 'Encabezado con foto al lado', 'Botones de contorno con flecha a la derecha', 'Títulos tipo etiqueta'],
                'bio' => 'Software y soluciones digitales. Escribe aquí qué problema resuelves.',
                'tema' => [
                    'colores' => $paleta('#0b1220', '#cbd5e1', '#f8fafc', '#94a3b8', '#0b1220', '#e2e8f0', '#334155', '#162033', '#ffffff', '#111a2c', '#cbd5e1', '#1e293b', '#22d3ee'),
                    'fondo' => $textura('cuadricula', '#16213a'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'space-grotesk', 'peso_titulos' => 700, 'escala_titulos' => 135, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'contorno', 'radio' => 10, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'elevar', 'alineacion' => 'izquierda', 'icono' => 'derecha'],
                    'disposicion' => ['encabezado' => 'izquierda', 'monograma' => true, 'forma_foto' => 'redondeada', 'lienzo' => 'panel', 'titulos' => 'etiqueta'],
                ],
                'bloques' => [
                    $texto('Escribe aquí en una frase qué hace tu producto y para quién.', 'grande'),
                    $boton('Prueba la demostración', 'flecha', ['destacado' => true]),
                    $titulo('Recursos', 'pequeno'),
                    $grupo('', [$boton('Documentación', 'libro'), $boton('Soporte', 'pregunta'), $boton('Precios', 'etiqueta')], 'lista'),
                    $correo('Hablar con ventas'),
                    $redes(['linkedin', 'x', 'youtube']),
                ],
            ],
            'tienda-de-barrio' => [
                'nombre' => 'Tienda de barrio',
                'giros' => ['comercio', 'agua', 'panaderia'],
                'estilo' => 'divertido',
                'descripcion' => 'Verde y amarillo con rombos, botones grandes y QR para el mostrador.',
                'rasgos' => ['Banda superior verde', 'Patrón de rombos', 'Mosaico de tres columnas', 'Código QR para imprimir'],
                'bio' => 'Todo lo que necesitas, cerca de ti. Escribe aquí qué vendes.',
                'tema' => [
                    'colores' => $paleta('#fffbea', '#2a3315', '#1a220b', '#626b4a', '#ffffff', '#1a220b', '#e3e7cf', '#f4f7e4', '#1a220b', '#ffffff', '#2a3315', '#e3e7cf', '#2f7d32'),
                    'fondo' => $textura('rombos', '#f4ecc4'),
                    'tipografia' => ['fuente_texto' => 'poppins', 'fuente_titulos' => 'poppins', 'peso_titulos' => 800, 'escala_titulos' => 125],
                    'botones' => ['estilo' => 'solido', 'radio' => 14, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'elevar', 'icono_fondo' => 'solido', 'tamano' => 'grande'],
                    'disposicion' => ['encabezado' => 'banda', 'monograma' => true, 'forma_foto' => 'redondeada', 'tarjetas' => 'sombra', 'titulos' => 'resaltado'],
                ],
                'bloques' => [
                    $tarjeta('Abierto hoy', $horario),
                    $whatsapp('Haz tu pedido'),
                    $grupo('Lo que encuentras', [$boton('Abarrotes', 'etiqueta'), $boton('Bebidas', 'cafe'), $boton('Ofertas', 'descuento')], 'cuadricula', 3),
                    $boton('Servicio a domicilio', 'auto'),
                    $mapa('La tienda'),
                    ['tipo' => 'qr', 'titulo' => 'Comparte esta página', 'descripcion' => 'Imprímelo y pégalo en tu mostrador.', 'tamano' => 160],
                ],
            ],
            'perfil-profesional' => [
                'nombre' => 'Perfil profesional',
                'giros' => ['profesional', 'despacho', 'consultorio', 'agencia'],
                'estilo' => 'minimalista',
                'descripcion' => 'Gris claro y panel blanco: sobrio, ordenado y directo.',
                'rasgos' => ['Contenido en panel flotante', 'Encabezado editorial', 'Botones sólidos oscuros con ícono a la izquierda', 'Tarjetas planas'],
                'bio' => 'Escribe aquí tu profesión y en qué puedes ayudar.',
                'tema' => [
                    'colores' => $paleta('#ffffff', '#3f3f46', '#18181b', '#5f5f68', '#18181b', '#fafafa', '#18181b', '#27272a', '#ffffff', '#f4f4f5', '#3f3f46', '#e4e4e7', '#18181b'),
                    'fondo' => $degradado(180, '#e4e4e7', '#f4f4f5'),
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'inter', 'peso_titulos' => 700, 'espaciado' => -1, 'alineacion' => 'izquierda'],
                    'botones' => ['estilo' => 'solido', 'radio' => 12, 'sombra' => 'ninguna', 'borde' => 0, 'animacion' => 'elevar', 'alineacion' => 'izquierda'],
                    'disposicion' => ['encabezado' => 'editorial', 'monograma' => true, 'lienzo' => 'panel', 'tarjetas' => 'plana', 'ancho' => 'estrecho'],
                ],
                'bloques' => [
                    $tarjeta('Sobre mí', 'Escribe aquí tu experiencia, tu formación y cómo trabajas.'),
                    $boton('Servicios', 'maletin'),
                    $boton('Proyectos', 'trofeo'),
                    $boton('Currículum', 'documento'),
                    $correo('Escríbeme'),
                    $contacto('¿Trabajamos juntos?', 'Cuéntame brevemente qué necesitas.'),
                    $redes(['linkedin', 'sitio']),
                ],
            ],
            'antojitos' => [
                'nombre' => 'Antojitos',
                'giros' => ['restaurante', 'menu', 'comercio'],
                'estilo' => 'divertido',
                'descripcion' => 'Rojo jitomate, píldoras con sombra sólida y lo más pedido al frente.',
                'rasgos' => ['Encabezado centrado con monograma', 'Fondo de puntos sobre rojo', 'Botones píldora con sombra dura', 'Títulos resaltados'],
                'bio' => 'Hecho al momento. Escribe aquí tu especialidad.',
                'tema' => [
                    'colores' => $paleta('#c0271d', '#fff6ee', '#ffffff', '#fff0e8', '#fff6ee', '#2b0d08', '#2b0d08', '#ffffff', '#2b0d08', '#fff6ee', '#2b0d08', '#2b0d08', '#ffd23f'),
                    'fondo' => $textura('puntos', '#ad2118'),
                    'tipografia' => ['fuente_texto' => 'dm-sans', 'fuente_titulos' => 'bricolage-grotesque', 'peso_titulos' => 800, 'escala_titulos' => 150],
                    'botones' => ['estilo' => 'solido', 'radio' => 40, 'sombra' => 'marcada', 'borde' => 2, 'animacion' => 'elevar'],
                    'disposicion' => ['encabezado' => 'centrado', 'monograma' => true, 'titulos' => 'resaltado', 'tarjetas' => 'plana'],
                ],
                'bloques' => [
                    $whatsapp('Pide a domicilio', ['destacado' => true, 'insignia' => 'Rápido']),
                    $titulo('Lo más pedido'),
                    $grupo('', [$boton('Combos', 'estrella'), $boton('Especialidades', 'menu'), $boton('Bebidas', 'cafe'), $boton('Postres', 'corazon')]),
                    $boton('Menú completo', 'menu'),
                    $tarjeta('Horario', $horario),
                    $redes(['facebook', 'instagram', 'tiktok']),
                ],
            ],
        ];
    }
}
