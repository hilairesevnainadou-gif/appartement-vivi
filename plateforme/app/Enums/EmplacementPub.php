<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Espaces publicitaires de l'application Android et du site web. Jamais sur le paiement, le billet (QR Code), le scanner,
 * la connexion ni dans les espaces propriétaire, gestionnaire et agent.
 */
enum EmplacementPub: string implements HasLabel
{
    case Demarrage = 'demarrage';
    case AccueilHaut = 'accueil_haut';
    case AccueilMilieu = 'accueil_milieu';
    case Recherche = 'recherche';
    case Fiche = 'fiche';
    case Reservations = 'reservations';

    public function getLabel(): string
    {
        return match ($this) {
            self::Demarrage => 'Ouverture de l’application',
            self::AccueilHaut => 'Accueil, en haut',
            self::AccueilMilieu => 'Accueil, entre les blocs',
            self::Recherche => 'Résultats de recherche',
            self::Fiche => 'Bas d’une fiche',
            self::Reservations => 'Mes réservations',
        };
    }

    /** L'espace existe aussi sur le site web (pas d'écran d'ouverture plein écran sur le site). */
    public function surSite(): bool
    {
        return $this !== self::Demarrage;
    }

    /** Nom de l'espace sur le site web. */
    public function libelleSite(): string
    {
        return match ($this) {
            self::AccueilHaut => 'Accueil, sous la recherche',
            self::AccueilMilieu => 'Accueil, avant les salles',
            self::Recherche => 'Dans les résultats de recherche',
            self::Fiche => 'Bas d’une fiche (après les avis)',
            self::Reservations => 'Mes réservations (espace client)',
            default => $this->getLabel(),
        };
    }

    /** @return array<string, string> espaces proposés sur le site web */
    public static function optionsSite(): array
    {
        return collect(self::cases())->filter->surSite()->mapWithKeys(fn (self $e) => [$e->value => $e->libelleSite()])->all();
    }

    /** Forme de l'espace dans l'application. */
    public function format(): string
    {
        return match ($this) {
            self::Demarrage => 'plein_ecran',
            self::AccueilMilieu, self::Recherche => 'carte',
            default => 'banniere',
        };
    }

    /** @return array{0: int, 1: int} largeur et hauteur de l'image, en pixels */
    public function taille(): array
    {
        return match ($this->format()) {
            'plein_ecran' => [1080, 1920],
            'carte' => [1200, 675],
            default => [1200, 400],
        };
    }

    public function tailleEnClair(): string
    {
        [$l, $h] = $this->taille();

        return $l.' × '.$h.' px';
    }

    /** Format du bloc d'annonces AdMob à créer pour cet espace. */
    public function formatAdmob(): string
    {
        return match ($this->format()) {
            'plein_ecran' => 'ouverture',          // App Open : Google interdit l'interstitiel au lancement
            'carte' => 'natif',
            default => 'banniere_adaptative',
        };
    }

    public function formatAdmobEnClair(): string
    {
        return match ($this->formatAdmob()) {
            'ouverture' => 'Annonce à l’ouverture',
            'natif' => 'Natif',
            default => 'Bannière',
        };
    }

    /** Bloc de test public de Google : à utiliser tant que l'application n'est pas en ligne. */
    public function blocTest(): string
    {
        return match ($this->formatAdmob()) {
            'ouverture' => 'ca-app-pub-3940256099942544/9257395921',
            'natif' => 'ca-app-pub-3940256099942544/2247696110',
            default => 'ca-app-pub-3940256099942544/9214589741',
        };
    }
}
