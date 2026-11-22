<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR Code en SVG, généré sur le serveur (aucun service extérieur). */
class QrCode
{
    public static function svg(string $contenu, int $taille = 240): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($taille, 1), new SvgImageBackEnd)))->writeString($contenu);

        // Retire la déclaration XML pour l'insérer directement dans la page
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
