<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Documentation technique lue dans l'administration : API de l'application mobile (docs/API-mobile.md) et
 * fonctionnement de la plateforme (README.md). Toujours à jour : les fichiers du projet sont lus à chaque visite.
 * Réservée aux comptes qui ont la permission « documentation.voir » (administrateur, technicien).
 */
class Documentation extends Page
{
    /** Onglet => [titre, fichier relatif à la racine du projet]. */
    public const DOCUMENTS = [
        'api' => ['API de l’application mobile', 'docs/API-mobile.md'],
        'plateforme' => ['Plateforme (installation et règles)', 'README.md'],
    ];

    protected static ?string $title = 'Documentation technique';

    protected static ?string $navigationLabel = 'Documentation technique';

    protected static ?string $slug = 'documentation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.documentation';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('documentation.voir');
    }

    public function getSubheading(): ?string
    {
        return 'À transmettre au développeur de l’application Android. Le contenu suit les fichiers du projet.';
    }

    protected function getViewData(): array
    {
        $onglet = isset(self::DOCUMENTS[request()->query('doc')]) ? request()->query('doc') : 'api';
        [$titre, $fichier] = self::DOCUMENTS[$onglet];
        $chemin = base_path($fichier);
        $texte = is_file($chemin) ? (string) file_get_contents($chemin) : '# Document introuvable';

        return [
            'onglet' => $onglet,
            'documents' => self::DOCUMENTS,
            'titre' => $titre,
            'misAJour' => is_file($chemin) ? \Carbon\Carbon::createFromTimestamp(filemtime($chemin))->timezone(config('app.timezone')) : null,
            // HTML du fichier écrit par l'équipe : les balises HTML brutes sont neutralisées
            'html' => Str::markdown($texte, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
        ];
    }
}
