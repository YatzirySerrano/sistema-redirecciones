<?php

namespace App\Services\Fidelidad\Diseno;

/**
 * Puntos de partida originales por tipo de negocio. Cada plantilla solo
 * cambia la apariencia; pasa por el mismo saneamiento que cualquier
 * documento. Nunca incluye imágenes (cada negocio sube las suyas).
 */
final class PlantillasDiseno
{
    public function __construct(private readonly DocumentoDiseno $documentos) {}

    /**
     * @return list<array{clave: string, nombre: string, descripcion: string, documento: array<string, mixed>}>
     */
    public function todas(): array
    {
        $lista = [];

        foreach (self::definiciones() as $clave => [$nombre, $descripcion, $cambios]) {
            $documento = array_replace_recursive(DocumentoDiseno::predeterminado(), $cambios, ['plantilla' => $clave]);
            $lista[] = [
                'clave' => $clave,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'documento' => $this->documentos->sanear($documento, null)['documento'],
            ];
        }

        return $lista;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private static function definiciones(): array
    {
        return [
            'cafeteria' => ['Cafetería', 'Tonos tostados, tazas como sellos y textura de granos.', [
                'programa' => ['nombre' => 'Club del café', 'lema' => 'Tu taza favorita, cada vez más cerca de ser gratis.'],
                'tipografia' => ['titulos' => 'fraunces', 'texto' => 'dm-sans'],
                'colores' => ['primario' => '#6b3e26', 'secundario' => '#d9a066', 'texto' => '#2b1a12', 'texto_suave' => '#6f5646', 'superficie' => '#fffaf3', 'borde' => '#ead9c6'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#f6ede1', 'color2' => '#ead8c2', 'angulo' => 170, 'decoracion' => 'granos'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#4a2a1a', 'color2' => '#7a4a2e', 'angulo' => 145, 'texto' => '#fff7ec', 'texto_suave' => '#e9cfb4', 'radio' => 22, 'sombra' => 'intensa', 'decoracion' => 'granos', 'decoracion_opacidad' => 16],
                'progreso' => ['presentacion' => 'sellos', 'icono' => 'cafe', 'completo_fondo' => '#d9a066', 'completo_icono' => '#3b2113', 'pendiente_fondo' => '#fff7ec', 'pendiente_icono' => '#e9cfb4', 'pendiente_estilo' => 'punteado', 'barra_color' => '#d9a066', 'barra_fondo' => '#fff7ec'],
                'contenido' => ['mensaje_meta' => '¡Tu café va por nuestra cuenta! Pídelo en barra.', 'mensaje_canje' => '¡Salud! Ya empezaste a juntar para el próximo.'],
            ]],
            'spa' => ['Spa y bienestar', 'Calma en verde salvia, hojas y círculos que se llenan.', [
                'programa' => ['nombre' => 'Ritual de bienestar', 'lema' => 'Cuidarte también tiene recompensa.'],
                'tipografia' => ['titulos' => 'cormorant-garamond', 'texto' => 'quicksand'],
                'colores' => ['primario' => '#4f6b5a', 'secundario' => '#c9b79c', 'texto' => '#22302a', 'texto_suave' => '#5d6e64', 'superficie' => '#fbfaf6', 'borde' => '#dfe5dc'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#eef2ec', 'color2' => '#dfe8df', 'angulo' => 180, 'decoracion' => 'hojas'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#dfe8df', 'color2' => '#f7f3ea', 'angulo' => 160, 'texto' => '#22302a', 'texto_suave' => '#53645a', 'radio' => 32, 'sombra' => 'suave', 'borde_ancho' => 1, 'borde_color' => '#c9d4c8', 'decoracion' => 'hojas', 'decoracion_opacidad' => 18],
                'progreso' => ['presentacion' => 'circulos', 'icono' => 'hoja', 'completo_fondo' => '#4f6b5a', 'completo_icono' => '#f7f3ea', 'pendiente_fondo' => '#4f6b5a', 'pendiente_icono' => '#4f6b5a', 'pendiente_estilo' => 'tenue', 'barra_color' => '#4f6b5a', 'barra_fondo' => '#c9d4c8'],
                'contenido' => ['mensaje_meta' => 'Tu momento de descanso está listo. Reserva y menciónalo en recepción.', 'mensaje_canje' => 'Esperamos que lo hayas disfrutado. Tu siguiente ritual ya comenzó.'],
            ]],
            'salon' => ['Salón de belleza', 'Rosa empolvado con dorado y presentación premium.', [
                'programa' => ['nombre' => 'Beauty Club', 'lema' => 'Cada cita te acerca a un mimo extra.'],
                'tipografia' => ['titulos' => 'playfair-display', 'texto' => 'montserrat'],
                'colores' => ['primario' => '#9d4b63', 'secundario' => '#d4af7a', 'texto' => '#2e1a21', 'texto_suave' => '#7a5862', 'superficie' => '#fffafb', 'borde' => '#f0dbe1'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#fbeef1', 'color2' => '#f3dde3', 'angulo' => 160, 'decoracion' => 'burbujas'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#2e1a21', 'color2' => '#5a2d3c', 'angulo' => 135, 'texto' => '#fff4f6', 'texto_suave' => '#e9c8d1', 'radio' => 26, 'sombra' => 'flotante', 'borde_ancho' => 1, 'borde_color' => '#d4af7a', 'decoracion' => 'diamantes', 'decoracion_opacidad' => 12],
                'progreso' => ['presentacion' => 'premium', 'icono' => 'destello', 'completo_fondo' => '#d4af7a', 'completo_icono' => '#2e1a21', 'pendiente_fondo' => '#fff4f6', 'pendiente_icono' => '#e9c8d1', 'pendiente_estilo' => 'contorno', 'barra_color' => '#d4af7a', 'barra_fondo' => '#fff4f6'],
                'contenido' => ['mensaje_meta' => '¡Tu consentido te espera! Agéndalo en tu próxima cita.', 'mensaje_canje' => 'Te veías increíble. Ya vas por el siguiente.'],
            ]],
            'barberia' => ['Barbería', 'Carbón, crema y rojo clásico, en formato de boleto perforado.', [
                'programa' => ['nombre' => 'Cliente frecuente', 'lema' => 'Corte tras corte, la casa invita.'],
                'tipografia' => ['titulos' => 'bebas-neue', 'texto' => 'space-grotesk'],
                'colores' => ['primario' => '#b3261e', 'secundario' => '#e8dcc4', 'texto' => '#161616', 'texto_suave' => '#55524c', 'superficie' => '#fbf8f1', 'borde' => '#dcd3c1'],
                'pagina' => ['fondo_tipo' => 'solido', 'color1' => '#efe8da', 'color2' => '#efe8da', 'decoracion' => 'rayas'],
                'tarjeta' => ['orientacion' => 'horizontal', 'fondo_tipo' => 'solido', 'color1' => '#1b1b1b', 'color2' => '#1b1b1b', 'texto' => '#f4ecdc', 'texto_suave' => '#bfb6a5', 'radio' => 10, 'sombra' => 'intensa', 'borde_ancho' => 2, 'borde_color' => '#b3261e', 'decoracion' => 'rayas', 'decoracion_opacidad' => 10],
                'progreso' => ['presentacion' => 'boleto', 'icono' => 'tijeras', 'completo_fondo' => '#b3261e', 'completo_icono' => '#f4ecdc', 'pendiente_fondo' => '#f4ecdc', 'pendiente_icono' => '#7a7468', 'pendiente_estilo' => 'punteado', 'barra_color' => '#b3261e', 'barra_fondo' => '#f4ecdc'],
                'contenido' => ['mensaje_meta' => 'Tu próximo corte va por la casa. Avísale a tu barbero.', 'mensaje_canje' => '¡Quedaste fino! Ya llevas ventaja para el siguiente.'],
            ]],
            'restaurante' => ['Restaurante', 'Terracota cálida, cubiertos y cuadrícula de visitas.', [
                'programa' => ['nombre' => 'Mesa frecuente', 'lema' => 'Volver a comer rico tiene premio.'],
                'tipografia' => ['titulos' => 'dm-serif-display', 'texto' => 'nunito'],
                'colores' => ['primario' => '#b4532a', 'secundario' => '#2f5d50', 'texto' => '#2a1a12', 'texto_suave' => '#6b5446', 'superficie' => '#fffaf5', 'borde' => '#efdccd'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#fbf1e7', 'color2' => '#f3e1cf', 'angulo' => 190, 'decoracion' => 'puntos'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#b4532a', 'color2' => '#8a3a1c', 'angulo' => 150, 'texto' => '#fff8f1', 'texto_suave' => '#f6d4bf', 'radio' => 20, 'sombra' => 'media', 'decoracion' => 'puntos', 'decoracion_opacidad' => 14],
                'progreso' => ['presentacion' => 'cuadricula', 'icono' => 'cubiertos', 'completo_fondo' => '#fff8f1', 'completo_icono' => '#b4532a', 'pendiente_fondo' => '#fff8f1', 'pendiente_icono' => '#f6d4bf', 'pendiente_estilo' => 'tenue', 'barra_color' => '#fff8f1', 'barra_fondo' => '#fff8f1'],
                'contenido' => ['mensaje_meta' => '¡Buen provecho! Tu recompensa está lista para tu próxima visita.', 'mensaje_canje' => 'Gracias por compartir la mesa. La cuenta vuelve a empezar.'],
            ]],
            'panaderia' => ['Panadería y postres', 'Crema y frambuesa, con un camino de visitas hasta el premio.', [
                'programa' => ['nombre' => 'Dulce recompensa', 'lema' => 'Cada visita es una mordida más cerca.'],
                'tipografia' => ['titulos' => 'pacifico', 'texto' => 'nunito'],
                'colores' => ['primario' => '#c2416b', 'secundario' => '#f2b84b', 'texto' => '#3a1f26', 'texto_suave' => '#7b5a62', 'superficie' => '#fffaf6', 'borde' => '#f3dfe2'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#fff3ea', 'color2' => '#fde3e3', 'angulo' => 160, 'decoracion' => 'confeti'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#fff7ef', 'color2' => '#ffe6ea', 'angulo' => 135, 'texto' => '#3a1f26', 'texto_suave' => '#7b5a62', 'radio' => 30, 'sombra' => 'suave', 'borde_ancho' => 2, 'borde_color' => '#f4c7d2', 'decoracion' => 'confeti', 'decoracion_opacidad' => 22],
                'progreso' => ['presentacion' => 'ruta', 'icono' => 'croissant', 'completo_fondo' => '#c2416b', 'completo_icono' => '#fff7ef', 'pendiente_fondo' => '#c2416b', 'pendiente_icono' => '#c2416b', 'pendiente_estilo' => 'contorno', 'barra_color' => '#c2416b', 'barra_fondo' => '#f4c7d2'],
                'contenido' => ['mensaje_meta' => '¡Llegaste! Tu antojo va por nuestra cuenta.', 'mensaje_canje' => '¡Que lo disfrutes! El camino vuelve a empezar.'],
            ]],
            'mascotas' => ['Mascotas', 'Azul alegre y huellitas para estéticas y veterinarias.', [
                'programa' => ['nombre' => 'Club peludo', 'lema' => 'Baño tras baño, un consentido para tu mejor amigo.'],
                'tipografia' => ['titulos' => 'quicksand', 'texto' => 'nunito'],
                'colores' => ['primario' => '#2563a8', 'secundario' => '#f59e0b', 'texto' => '#132235', 'texto_suave' => '#4b5d73', 'superficie' => '#ffffff', 'borde' => '#d8e4f2'],
                'pagina' => ['fondo_tipo' => 'degradado', 'color1' => '#eaf3fd', 'color2' => '#fdf4e3', 'angulo' => 180, 'decoracion' => 'burbujas'],
                'tarjeta' => ['fondo_tipo' => 'degradado', 'color1' => '#2563a8', 'color2' => '#1b4a80', 'angulo' => 140, 'texto' => '#ffffff', 'texto_suave' => '#d3e3f6', 'radio' => 28, 'sombra' => 'media', 'decoracion' => 'burbujas', 'decoracion_opacidad' => 14],
                'progreso' => ['presentacion' => 'sellos', 'icono' => 'huella', 'completo_fondo' => '#f59e0b', 'completo_icono' => '#132235', 'pendiente_fondo' => '#ffffff', 'pendiente_icono' => '#ffffff', 'pendiente_estilo' => 'contorno', 'barra_color' => '#f59e0b', 'barra_fondo' => '#ffffff'],
                'contenido' => ['mensaje_meta' => '¡Guau! El consentido de tu mascota está listo.', 'mensaje_canje' => 'Gracias por confiarnos a tu compañero. ¡A juntar huellitas otra vez!'],
            ]],
            'minimalista' => ['Minimalista', 'Blanco, negro y una barra de progreso limpia.', [
                'programa' => ['nombre' => 'Recompensas', 'lema' => 'Simple: vienes, sumas, ganas.'],
                'tipografia' => ['titulos' => 'inter', 'texto' => 'inter'],
                'colores' => ['primario' => '#111111', 'secundario' => '#6b7280', 'texto' => '#111111', 'texto_suave' => '#52525b', 'superficie' => '#ffffff', 'borde' => '#e4e4e7'],
                'pagina' => ['fondo_tipo' => 'solido', 'color1' => '#f4f4f5', 'color2' => '#f4f4f5', 'decoracion' => 'ninguna'],
                'tarjeta' => ['fondo_tipo' => 'solido', 'color1' => '#ffffff', 'color2' => '#ffffff', 'texto' => '#111111', 'texto_suave' => '#52525b', 'radio' => 16, 'sombra' => 'suave', 'borde_ancho' => 1, 'borde_color' => '#e4e4e7', 'decoracion' => 'ninguna', 'decoracion_opacidad' => 0],
                'progreso' => ['presentacion' => 'barra', 'icono' => 'check', 'completo_fondo' => '#111111', 'completo_icono' => '#ffffff', 'pendiente_fondo' => '#e4e4e7', 'pendiente_icono' => '#a1a1aa', 'pendiente_estilo' => 'tenue', 'mostrar_barra' => true, 'barra_color' => '#111111', 'barra_fondo' => '#e4e4e7'],
            ]],
        ];
    }
}
