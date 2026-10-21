<?php

namespace App\Services\Estadisticas;

use App\Enums\CategoriaDispositivo;

/**
 * Clasificación aproximada del User-Agent en categorías de dispositivo,
 * navegador y sistema operativo. No identifica personas ni dispositivos
 * concretos (sin fingerprinting); el User-Agent completo no se almacena.
 *
 * La detección de bots es heurística: filtra rastreadores conocidos y
 * generadores de vistas previas, pero no es perfecta.
 */
class ClasificadorAgente
{
    private const PATRON_BOT = '/bot|crawl|spider|slurp|preview|facebookexternalhit|facebot|whatsapp|telegram|discord|skype|slack|embedly|curl|wget|python|httpclient|okhttp|java\/|go-http|libwww|headless|phantom|lighthouse|pingdom|uptime|monitor|scan|axios|node-fetch|postman|insomnia/i';

    /**
     * @return array{dispositivo: CategoriaDispositivo, navegador: string|null, sistema_operativo: string|null, es_bot: bool}
     */
    public function clasificar(?string $agente): array
    {
        $agente = trim((string) $agente);

        if ($agente === '') {
            return [
                'dispositivo' => CategoriaDispositivo::Bot,
                'navegador' => null,
                'sistema_operativo' => null,
                'es_bot' => true,
            ];
        }

        $esBot = (bool) preg_match(self::PATRON_BOT, $agente);

        return [
            'dispositivo' => $esBot ? CategoriaDispositivo::Bot : $this->dispositivo($agente),
            'navegador' => $this->navegador($agente),
            'sistema_operativo' => $this->sistemaOperativo($agente),
            'es_bot' => $esBot,
        ];
    }

    private function dispositivo(string $agente): CategoriaDispositivo
    {
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $agente)) {
            return CategoriaDispositivo::Tablet;
        }

        if (preg_match('/mobi|iphone|ipod|android|windows phone|blackberry|opera mini/i', $agente)) {
            return CategoriaDispositivo::Movil;
        }

        if (preg_match('/windows|macintosh|mac os x|x11|linux|cros/i', $agente)) {
            return CategoriaDispositivo::Escritorio;
        }

        return CategoriaDispositivo::Desconocido;
    }

    private function navegador(string $agente): string
    {
        // El orden importa: varios navegadores incluyen "Chrome" o "Safari".
        $patrones = [
            'Instagram' => '/instagram/i',
            'Facebook' => '/fban|fbav|fb_iab/i',
            'Samsung Internet' => '/samsungbrowser/i',
            'Edge' => '/edg(e|a|ios)?\//i',
            'Opera' => '/opr\/|opera/i',
            'Firefox' => '/firefox|fxios/i',
            'Chrome' => '/chrome|crios/i',
            'Safari' => '/safari/i',
        ];

        foreach ($patrones as $nombre => $patron) {
            if (preg_match($patron, $agente)) {
                return $nombre;
            }
        }

        return 'Otro';
    }

    private function sistemaOperativo(string $agente): string
    {
        $patrones = [
            'iOS' => '/iphone|ipad|ipod/i',
            'Android' => '/android/i',
            'Windows' => '/windows/i',
            'macOS' => '/macintosh|mac os x/i',
            'ChromeOS' => '/cros/i',
            'Linux' => '/linux|x11/i',
        ];

        foreach ($patrones as $nombre => $patron) {
            if (preg_match($patron, $agente)) {
                return $nombre;
            }
        }

        return 'Otro';
    }
}
