<?php

namespace App\Filament;

use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Pastille aux initiales, orange sur fond de la charte, générée sur place.
 * Remplace l'avatar par défaut de Filament, chargé depuis un service externe (ui-avatars.com).
 */
class AvatarVivi implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initiales = $record instanceof User ? $record->initiales() : 'V';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="#f4ad4c"/>'
            .'<text x="32" y="33" text-anchor="middle" dominant-baseline="central" '
            .'font-family="Outfit, DM Sans, Arial, sans-serif" font-size="25" font-weight="700" fill="#14282a">'
            .e($initiales).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
