<?php

namespace App\Services\Paginas;

use App\Models\Negocio;

/**
 * Plantillas originales de DASTI: punto de partida (diseño + estructura de
 * ejemplo) que el negocio edita. Los destinos se dejan vacíos para que el
 * editor marque lo que falta antes de publicar. Se validan con
 * DocumentoPagina como cualquier documento.
 */
class PlantillasPagina
{
    public function __construct(private readonly DocumentoPagina $documento) {}

    /**
     * @return list<array{clave: string, nombre: string, descripcion: string, documento: array<string, mixed>}>
     */
    public function todas(Negocio $negocio): array
    {
        $plantillas = [];

        foreach ($this->definiciones() as $clave => $p) {
            $plantillas[] = [
                'clave' => $clave,
                'nombre' => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'documento' => $this->documento->sanear([
                    'perfil' => ['nombre' => $negocio->nombre_comercial, 'descripcion' => $p['bio']],
                    'tema' => array_replace_recursive(DocumentoPagina::temaPorDefecto(), $p['tema']),
                    'paginas' => [['titulo' => 'Inicio', 'bloques' => $p['bloques']]],
                ], $negocio, DocumentoPagina::MODO_TOLERANTE),
            ];
        }

        return $plantillas;
    }

    /**
     * @return array<string, array{nombre: string, descripcion: string, bio: string, tema: array<string, mixed>, bloques: list<array<string, mixed>>}>
     */
    private function definiciones(): array
    {
        $boton = fn (string $titulo, string $icono = 'enlace', array $extra = []) => ['tipo' => 'enlace', 'titulo' => $titulo, 'icono' => $icono, 'url' => '', ...$extra];
        $redes = fn (array $redes, string $estilo = 'iconos') => ['tipo' => 'redes', 'estilo' => $estilo, 'elementos' => array_map(fn ($r) => ['red' => $r, 'valor' => ''], $redes)];
        $whatsapp = fn (string $titulo = 'Escríbenos por WhatsApp') => ['tipo' => 'whatsapp', 'titulo' => $titulo, 'icono' => 'red:whatsapp', 'telefono' => '', 'mensaje' => 'Hola, vi su página y quiero más información.'];

        return [
            'minimalista' => [
                'nombre' => 'Minimalista',
                'descripcion' => 'Blanco cálido, tipografía limpia y mucho espacio.',
                'bio' => 'Todo lo que necesitas saber de nosotros, en un solo lugar.',
                'tema' => [
                    'colores' => ['fondo' => '#fafaf9', 'texto' => '#292524', 'titulos' => '#0c0a09', 'texto_suave' => '#78716c', 'enlaces' => '#0c0a09',
                        'boton_fondo' => '#ffffff', 'boton_texto' => '#1c1917', 'boton_borde' => '#e7e5e4', 'boton_hover_fondo' => '#f5f5f4', 'boton_hover_texto' => '#0c0a09',
                        'tarjeta_fondo' => '#ffffff', 'tarjeta_texto' => '#292524', 'borde' => '#e7e5e4', 'acento' => '#0c0a09'],
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'inter', 'fuente_titulos' => 'inter', 'peso_titulos' => 600, 'espaciado' => -1],
                    'botones' => ['estilo' => 'solido', 'radio' => 10, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'elevar'],
                ],
                'bloques' => [
                    $boton('Nuestro sitio web', 'globo'),
                    $boton('Ubicación y horarios', 'mapa'),
                    $whatsapp(),
                    $redes(['instagram', 'facebook', 'tiktok']),
                ],
            ],
            'corporativa' => [
                'nombre' => 'Corporativa',
                'descripcion' => 'Sobria y confiable, con acentos azul marino.',
                'bio' => 'Soluciones para empresas. Conoce nuestros servicios y agenda una llamada con nuestro equipo.',
                'tema' => [
                    'colores' => ['fondo' => '#f3f6fa', 'texto' => '#1e293b', 'titulos' => '#0f172a', 'texto_suave' => '#64748b', 'enlaces' => '#1d4ed8',
                        'boton_fondo' => '#1e3a8a', 'boton_texto' => '#ffffff', 'boton_borde' => '#1e3a8a', 'boton_hover_fondo' => '#1e40af', 'boton_hover_texto' => '#ffffff',
                        'tarjeta_fondo' => '#ffffff', 'tarjeta_texto' => '#1e293b', 'borde' => '#dbe3ee', 'acento' => '#2563eb'],
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'plus-jakarta-sans', 'fuente_titulos' => 'plus-jakarta-sans', 'alineacion' => 'izquierda', 'peso_titulos' => 700],
                    'botones' => ['estilo' => 'solido', 'radio' => 8, 'sombra' => 'suave', 'borde' => 0, 'animacion' => 'elevar', 'alineacion' => 'izquierda', 'icono' => 'derecha'],
                    'disposicion' => ['ancho' => 'normal', 'encabezado' => 'izquierda', 'forma_foto' => 'redondeada'],
                ],
                'bloques' => [
                    ['tipo' => 'titulo', 'texto' => 'Servicios', 'tamano' => 'pequeno'],
                    $boton('Consultoría', 'maletin', ['subtitulo' => 'Diagnóstico y plan de trabajo']),
                    $boton('Capacitación', 'escuela', ['subtitulo' => 'Cursos para equipos']),
                    $boton('Casos de éxito', 'trofeo'),
                    ['tipo' => 'separador', 'estilo' => 'linea'],
                    ['tipo' => 'correo', 'titulo' => 'Escríbenos', 'icono' => 'correo', 'email' => ''],
                    $redes(['linkedin', 'youtube', 'sitio']),
                ],
            ],
            'restaurante' => [
                'nombre' => 'Restaurantes',
                'descripcion' => 'Cálida y apetitosa: menú, reservas por mensaje y ubicación.',
                'bio' => 'Cocina de temporada, hecha en casa. Te esperamos.',
                'tema' => [
                    'colores' => ['fondo' => '#2b1a12', 'texto' => '#f6e9dc', 'titulos' => '#ffd9a8', 'texto_suave' => '#d4b89c', 'enlaces' => '#ffd9a8',
                        'boton_fondo' => '#f4a259', 'boton_texto' => '#2b1a12', 'boton_borde' => '#f4a259', 'boton_hover_fondo' => '#f7b77a', 'boton_hover_texto' => '#2b1a12',
                        'tarjeta_fondo' => '#3a2418', 'tarjeta_texto' => '#f6e9dc', 'borde' => '#5a3a28', 'acento' => '#e76f51'],
                    'fondo' => ['tipo' => 'degradado', 'angulo' => 170, 'paradas' => [['color' => '#2b1a12', 'posicion' => 0], ['color' => '#4a2c1d', 'posicion' => 100]]],
                    'tipografia' => ['fuente_texto' => 'dm-sans', 'fuente_titulos' => 'playfair-display', 'peso_titulos' => 700, 'escala_titulos' => 140],
                    'botones' => ['estilo' => 'solido', 'radio' => 40, 'sombra' => 'media', 'borde' => 0, 'animacion' => 'elevar'],
                ],
                'bloques' => [
                    $boton('Ver el menú', 'menu', ['destacado' => true, 'insignia' => 'Nuevo']),
                    $whatsapp('Pide para llevar'),
                    ['tipo' => 'mapa', 'titulo' => 'Visítanos', 'direccion' => '', 'mostrar_mapa' => true],
                    ['tipo' => 'tarjeta', 'titulo' => 'Horario', 'texto' => "Martes a domingo\n**13:00 a 22:00**"],
                    $redes(['instagram', 'facebook', 'tiktok']),
                ],
            ],
            'belleza' => [
                'nombre' => 'Belleza y spa',
                'descripcion' => 'Tonos arena y rosa suave, letras elegantes.',
                'bio' => 'Rituales de cuidado para ti. Escríbenos para conocer disponibilidad.',
                'tema' => [
                    'colores' => ['fondo' => '#f7efe9', 'texto' => '#4a3b36', 'titulos' => '#3b2c27', 'texto_suave' => '#8a746c', 'enlaces' => '#3b2c27',
                        'boton_fondo' => '#ead3c7', 'boton_texto' => '#3b2c27', 'boton_borde' => '#dcbcad', 'boton_hover_fondo' => '#e2c4b5', 'boton_hover_texto' => '#2a1d19',
                        'tarjeta_fondo' => '#fffaf7', 'tarjeta_texto' => '#4a3b36', 'borde' => '#ead9cf', 'acento' => '#b5838d'],
                    'fondo' => ['tipo' => 'degradado', 'angulo' => 180, 'paradas' => [['color' => '#f9f1ec', 'posicion' => 0], ['color' => '#efe0d6', 'posicion' => 100]]],
                    'tipografia' => ['fuente_texto' => 'nunito', 'fuente_titulos' => 'cormorant-garamond', 'peso_titulos' => 600, 'escala_titulos' => 150, 'espaciado' => 1],
                    'botones' => ['estilo' => 'suave', 'radio' => 40, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'brillo'],
                ],
                'bloques' => [
                    $boton('Tratamientos y precios', 'brillo'),
                    $whatsapp('Agenda por WhatsApp'),
                    $boton('Tarjeta de regalo', 'regalo'),
                    ['tipo' => 'texto', 'texto' => '_Tu bienestar es nuestra prioridad._'],
                    $redes(['instagram', 'tiktok', 'pinterest']),
                ],
            ],
            'redes' => [
                'nombre' => 'Redes sociales',
                'descripcion' => 'Degradado vibrante y botones de cristal para creadores.',
                'bio' => 'Contenido nuevo cada semana ✨',
                'tema' => [
                    'colores' => ['fondo' => '#6d28d9', 'texto' => '#ffffff', 'titulos' => '#ffffff', 'texto_suave' => '#fde7f3', 'enlaces' => '#ffffff',
                        'boton_fondo' => '#ffffff', 'boton_texto' => '#ffffff', 'boton_borde' => '#ffffff', 'boton_hover_fondo' => '#ffffff', 'boton_hover_texto' => '#ffffff',
                        'tarjeta_fondo' => '#ffffff', 'tarjeta_texto' => '#3b0764', 'borde' => '#f5d0fe', 'acento' => '#fde047'],
                    'fondo' => ['tipo' => 'degradado', 'angulo' => 135, 'paradas' => [['color' => '#6d28d9', 'posicion' => 0], ['color' => '#db2777', 'posicion' => 55], ['color' => '#f59e0b', 'posicion' => 100]]],
                    'tipografia' => ['fuente_texto' => 'outfit', 'fuente_titulos' => 'outfit', 'peso_titulos' => 700],
                    'botones' => ['estilo' => 'vidrio', 'radio' => 18, 'sombra' => 'suave', 'borde' => 1, 'animacion' => 'pulso'],
                ],
                'bloques' => [
                    $redes(['instagram', 'tiktok', 'youtube', 'x', 'threads']),
                    $boton('Mi último video', 'video', ['destacado' => true]),
                    $boton('Colaboraciones', 'mensaje'),
                    ['tipo' => 'video', 'fuente' => 'externo', 'url' => ''],
                ],
            ],
            'profesional' => [
                'nombre' => 'Servicios profesionales',
                'descripcion' => 'Portada amplia, verde petróleo y tarjetas claras.',
                'bio' => 'Atención personalizada y profesional. Consulta nuestros servicios.',
                'tema' => [
                    'colores' => ['fondo' => '#ffffff', 'texto' => '#334155', 'titulos' => '#0f172a', 'texto_suave' => '#64748b', 'enlaces' => '#0f766e',
                        'boton_fondo' => '#0f766e', 'boton_texto' => '#ffffff', 'boton_borde' => '#0f766e', 'boton_hover_fondo' => '#115e59', 'boton_hover_texto' => '#ffffff',
                        'tarjeta_fondo' => '#f8fafc', 'tarjeta_texto' => '#334155', 'borde' => '#e2e8f0', 'acento' => '#0f766e'],
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'manrope', 'fuente_titulos' => 'manrope', 'peso_titulos' => 800, 'espaciado' => -1],
                    'botones' => ['estilo' => 'solido', 'radio' => 12, 'sombra' => 'suave', 'borde' => 0, 'animacion' => 'elevar'],
                    'disposicion' => ['encabezado' => 'portada', 'forma_foto' => 'circulo'],
                ],
                'bloques' => [
                    ['tipo' => 'tarjeta', 'titulo' => 'Sobre mí', 'texto' => 'Más de 10 años de experiencia. **Primera consulta sin costo.**'],
                    $boton('Servicios y tarifas', 'documento'),
                    ['tipo' => 'telefono', 'titulo' => 'Llamar al consultorio', 'icono' => 'telefono', 'telefono' => ''],
                    $whatsapp('Enviar un mensaje'),
                    ['tipo' => 'contacto', 'titulo' => 'Déjanos tus datos', 'descripcion' => 'Te contactamos en menos de 24 horas.'],
                ],
            ],
            'oscura' => [
                'nombre' => 'Oscura premium',
                'descripcion' => 'Negro profundo, cuadrícula sutil y detalles dorados.',
                'bio' => 'Experiencias exclusivas. Bienvenido.',
                'tema' => [
                    'colores' => ['fondo' => '#0b0b0f', 'texto' => '#d4d4d8', 'titulos' => '#fafafa', 'texto_suave' => '#a1a1aa', 'enlaces' => '#e8c766',
                        'boton_fondo' => '#16161c', 'boton_texto' => '#fafafa', 'boton_borde' => '#34343c', 'boton_hover_fondo' => '#212129', 'boton_hover_texto' => '#ffffff',
                        'tarjeta_fondo' => '#131318', 'tarjeta_texto' => '#d4d4d8', 'borde' => '#27272a', 'acento' => '#d4af37'],
                    'fondo' => ['tipo' => 'textura', 'textura' => 'cuadricula', 'color_textura' => '#1b1b22'],
                    'tipografia' => ['fuente_texto' => 'sora', 'fuente_titulos' => 'fraunces', 'peso_titulos' => 600, 'peso_texto' => 300, 'espaciado' => 2],
                    'botones' => ['estilo' => 'solido', 'radio' => 14, 'sombra' => 'marcada', 'borde' => 1, 'animacion' => 'brillo'],
                ],
                'bloques' => [
                    $boton('Reservaciones', 'estrella', ['destacado' => true]),
                    $boton('Experiencias', 'brillo'),
                    $boton('Galería', 'camara'),
                    $redes(['instagram', 'spotify', 'sitio'], 'botones'),
                ],
            ],
            'creativa' => [
                'nombre' => 'Creativa',
                'descripcion' => 'Amarillo con ondas, bordes marcados y sombras sólidas.',
                'bio' => 'Diseño, ilustración y proyectos con personalidad.',
                'tema' => [
                    'colores' => ['fondo' => '#fff4c9', 'texto' => '#1f1300', 'titulos' => '#1f1300', 'texto_suave' => '#5c4a1f', 'enlaces' => '#c2410c',
                        'boton_fondo' => '#ff7a59', 'boton_texto' => '#1f1300', 'boton_borde' => '#1f1300', 'boton_hover_fondo' => '#ff9478', 'boton_hover_texto' => '#1f1300',
                        'tarjeta_fondo' => '#ffffff', 'tarjeta_texto' => '#1f1300', 'borde' => '#1f1300', 'acento' => '#2563eb'],
                    'fondo' => ['tipo' => 'textura', 'textura' => 'ondas', 'color_textura' => '#ffe38a'],
                    'tipografia' => ['fuente_texto' => 'bricolage-grotesque', 'fuente_titulos' => 'bricolage-grotesque', 'peso_titulos' => 800, 'escala_titulos' => 150],
                    'botones' => ['estilo' => 'solido', 'radio' => 6, 'sombra' => 'marcada', 'borde' => 2, 'animacion' => 'elevar'],
                ],
                'bloques' => [
                    ['tipo' => 'titulo', 'texto' => 'Hola, ¡qué gusto verte!', 'tamano' => 'grande'],
                    ['tipo' => 'grupo', 'titulo' => 'Proyectos', 'disposicion' => 'cuadricula', 'columnas' => 2, 'bloques' => [
                        $boton('Portafolio', 'paleta'), $boton('Ilustración', 'brillo'), $boton('Tienda externa', 'etiqueta'), $boton('Blog', 'noticias'),
                    ]],
                    $redes(['instagram', 'pinterest', 'tiktok']),
                ],
            ],
            'catalogo' => [
                'nombre' => 'Catálogo visual',
                'descripcion' => 'Imágenes protagonistas en carrusel y cuadrícula.',
                'bio' => 'Mira nuestras piezas más recientes.',
                'tema' => [
                    'colores' => ['fondo' => '#f4f1ec', 'texto' => '#2d2a26', 'titulos' => '#1a1815', 'texto_suave' => '#7a7268', 'enlaces' => '#1a1815',
                        'boton_fondo' => '#1a1815', 'boton_texto' => '#f4f1ec', 'boton_borde' => '#1a1815', 'boton_hover_fondo' => '#34302a', 'boton_hover_texto' => '#ffffff',
                        'tarjeta_fondo' => '#ffffff', 'tarjeta_texto' => '#2d2a26', 'borde' => '#e2dcd2', 'acento' => '#8c6d46'],
                    'fondo' => ['tipo' => 'solido'],
                    'tipografia' => ['fuente_texto' => 'work-sans', 'fuente_titulos' => 'josefin-sans', 'peso_titulos' => 600, 'espaciado' => 3],
                    'botones' => ['estilo' => 'contorno', 'radio' => 4, 'sombra' => 'ninguna', 'borde' => 1, 'animacion' => 'ninguna'],
                    'disposicion' => ['ancho' => 'amplio'],
                ],
                'bloques' => [
                    ['tipo' => 'carrusel', 'titulo' => 'Lo más nuevo', 'proporcion' => 'vertical', 'elementos' => []],
                    ['tipo' => 'grupo', 'titulo' => 'Colecciones', 'disposicion' => 'cuadricula', 'columnas' => 3, 'bloques' => [
                        $boton('Primavera', 'hoja'), $boton('Clásicos', 'estrella'), $boton('Ediciones', 'etiqueta'),
                    ]],
                    $whatsapp('Pregunta por una pieza'),
                    $redes(['instagram', 'pinterest']),
                ],
            ],
        ];
    }
}
