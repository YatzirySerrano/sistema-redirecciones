<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portada pública de DASTI (/). Los invitados ven la presentación de la
 * plataforma; las sesiones iniciadas siguen yendo a su panel.
 *
 * Las preguntas frecuentes viven aquí para usarse tanto en la página como en
 * los datos estructurados FAQPage (resources/views/partials/seo.blade.php).
 * No se publican precios ni testimonios.
 */
class BienvenidaController extends Controller
{
    /**
     * @var list<array{pregunta: string, respuesta: string}>
     */
    public const PREGUNTAS = [
        [
            'pregunta' => '¿Qué es una redirección dinámica?',
            'respuesta' => 'Es un enlace corto y permanente que apunta a un destino que puedes cambiar cuando quieras. El código QR o la tarjeta NFC siempre llevan al mismo enlace; tú decides a dónde redirige en cada momento.',
        ],
        [
            'pregunta' => '¿Tengo que reimprimir mis códigos QR si cambio el destino?',
            'respuesta' => 'No. Los códigos QR dinámicos y las tarjetas NFC de DASTI conservan su enlace para siempre. Al cambiar el destino desde el panel, el cambio se refleja de inmediato en todos los productos ya impresos o grabados.',
        ],
        [
            'pregunta' => '¿Qué puedo ver en las estadísticas de escaneos?',
            'respuesta' => 'Los accesos por día, por producto y por tipo de dispositivo, para saber qué códigos QR y tarjetas NFC se usan más. Los registros son anónimos: no se guarda la dirección IP de quien escanea.',
        ],
        [
            'pregunta' => '¿Cómo funciona el programa de fidelidad digital?',
            'respuesta' => 'Tus clientes reciben una tarjeta de recompensas digital, sin descargar aplicaciones. El negocio registra las visitas y los canjes desde su panel y el cliente consulta su progreso desde el teléfono.',
        ],
        [
            'pregunta' => '¿Qué es la página de enlaces?',
            'respuesta' => 'Una página tipo «link in bio» con tus redes sociales, contacto, ubicación y enlaces importantes, personalizada con la identidad de tu negocio. Puedes vincularla a tus códigos QR y tarjetas NFC.',
        ],
        [
            'pregunta' => '¿Cómo obtengo una cuenta?',
            'respuesta' => 'Las cuentas las crea el equipo de DASTI al contratar el servicio; no hay registro público. Si ya tienes cuenta, entra con «Iniciar sesión».',
        ],
    ];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('inicio');
        }

        $correo = config('dasti.sitio.correo_contacto');
        $whatsapp = preg_replace('/\D+/', '', (string) config('dasti.sitio.whatsapp_contacto'));

        return Inertia::render('Bienvenida', [
            'preguntas' => self::PREGUNTAS,
            'contacto' => [
                'correo' => is_string($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : null,
                'whatsapp' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp : null,
            ],
            'prefijoEnlace' => rtrim((string) config('dasti.url_publica'), '/').'/'.config('dasti.prefijo_redireccion').'/',
        ]);
    }
}
