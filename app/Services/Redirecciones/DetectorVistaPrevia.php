<?php

namespace App\Services\Redirecciones;

/**
 * Reconoce los robots que generan la vista previa de un enlace cuando
 * alguien lo pega en una app de mensajería o red social.
 *
 * Esos robots siguen el 302 y mostrarían el título e imagen del destino;
 * para ellos el enlace corto responde una tarjeta genérica de DASTI sin
 * revelar el destino (ver Publico\RedireccionController).
 *
 * Deliberadamente NO incluye buscadores (Googlebot, Bingbot, Applebot): deben
 * seguir el 302 normal, que ya lleva `X-Robots-Tag: noindex`. Tampoco
 * incluye navegadores integrados de apps (Instagram, FBAN/FBAV, LINE…),
 * que usan personas reales y deben llegar al destino. iMessage se presenta
 * como «facebookexternalhit … Twitterbot», por lo que queda cubierto.
 */
class DetectorVistaPrevia
{
    private const PATRON = '/whatsapp\/|facebookexternalhit|facebot|meta-externalfetcher|twitterbot|telegrambot|linkedinbot|slackbot|slack-imgproxy|discordbot|skypeuripreview|pinterestbot|redditbot|embedly|vkshare|mastodon\/|bluesky cardyb|iframely|microsoftpreview/i';

    public function esVistaPrevia(?string $agente): bool
    {
        if ($agente === null || $agente === '') {
            return false;
        }

        return preg_match(self::PATRON, $agente) === 1;
    }
}
