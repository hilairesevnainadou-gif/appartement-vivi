<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Support\Montant;
use App\Support\Telephone;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;

/** Colonnes communes aux listes de l'administration : même rendu partout. */
class Colonnes
{
    /** Référence (VIVI-XXXXXX, RET-000001…) : en gras, couleur de la charte, copiable. */
    public static function reference(string $champ = 'reference', string $libelle = 'Référence'): TextColumn
    {
        return TextColumn::make($champ)
            ->label($libelle)
            ->weight(FontWeight::Bold)
            ->color('primary')
            ->searchable()
            ->copyable()
            ->copyMessage('Référence copiée');
    }

    /** Montant en FCFA, aligné à droite. */
    public static function montant(string $champ, string $libelle): TextColumn
    {
        return TextColumn::make($champ)
            ->label($libelle)
            ->formatStateUsing(fn ($state) => Montant::fcfa($state))
            ->weight(FontWeight::SemiBold)
            ->alignEnd()
            // Un montant ne se coupe jamais (« 40 » d'un côté, « 000 FCFA » de l'autre)
            ->extraAttributes(['style' => 'white-space: nowrap'])
            ->sortable();
    }

    /** Personne (client, propriétaire) : nom, et numéro lisible ou email en dessous. */
    public static function personne(string $relation, string $libelle): TextColumn
    {
        return TextColumn::make($relation.'.name')
            ->label($libelle)
            ->description(fn ($record) => self::contact($record->{$relation}))
            ->searchable(['name', 'telephone', 'email']);
    }

    public static function contact(?User $u): ?string
    {
        if (! $u) {
            return null;
        }

        return $u->telephone ? Telephone::lisible($u->telephone) : $u->email;
    }

    /** Date et heure courtes : « 12/11 à 14:00 », l'année seulement si ce n'est pas l'année en cours. */
    public static function dateHeure(string $champ, string $libelle): TextColumn
    {
        return TextColumn::make($champ)
            ->label($libelle)
            ->formatStateUsing(fn ($state) => $state?->year === now()->year
                ? $state->translatedFormat('D j M').' · '.$state->format('H:i')
                : $state?->format('d/m/Y H:i'))
            ->sortable();
    }
}
