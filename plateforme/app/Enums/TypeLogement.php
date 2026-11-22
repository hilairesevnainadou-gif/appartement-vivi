<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Type d'espace réservable. Deux familles :
 *  - logements (chambre, studio, appartements, résidence) : voyageurs, chambres, lits ;
 *  - salles (fêtes, conférences) : capacité en personnes, sans chambres ni lits.
 * Les deux se réservent de la même façon (à l'heure, à la demi-journée, à la journée).
 */
enum TypeLogement: string implements HasLabel
{
    case Chambre = 'chambre';
    case Studio = 'studio';
    case F2 = 'f2';
    case F3 = 'f3';
    case F4 = 'f4';
    case Residence = 'residence';   // plusieurs chambres identiques louées séparément (logements.unites)
    case SalleFetes = 'salle_fetes';
    case SalleConference = 'salle_conference';
    case SalleReunion = 'salle_reunion';
    case EspacePleinAir = 'espace_plein_air';     // jardin, terrasse, cour pour un événement
    case EspaceComplet = 'espace_complet';        // maison ou villa entière louée pour un événement

    /** Capacité maximale saisie : un logement accueille quelques voyageurs, une salle des centaines d'invités. */
    public const CAPACITE_MAX_LOGEMENT = 20;

    public const CAPACITE_MAX_SALLE = 1000;

    public function getLabel(): string
    {
        return match ($this) {
            self::Residence => 'Résidence',
            self::Chambre => 'Chambre',
            self::Studio => 'Studio',
            self::F2 => 'F2 (1 chambre et salon)',
            self::F3 => 'F3 (2 chambres et salon)',
            self::F4 => 'F4 et plus',
            self::SalleFetes => 'Salle de fêtes',
            self::SalleConference => 'Salle de conférence',
            self::SalleReunion => 'Salle de réunion',
            self::EspacePleinAir => 'Espace en plein air (jardin, terrasse)',
            self::EspaceComplet => 'Espace complet (maison ou villa entière)',
        };
    }

    /** Libellé court pour les pastilles des cartes. */
    public function court(): string
    {
        return match ($this) {
            self::EspacePleinAir => 'Plein air',
            self::EspaceComplet => 'Espace complet',
            self::F2 => 'F2',
            self::F3 => 'F3',
            default => $this->getLabel(),
        };
    }

    /** Icône Bootstrap Icons du type. */
    public function icone(): string
    {
        return match ($this) {
            self::SalleFetes => 'bi-balloon',
            self::SalleConference => 'bi-easel',
            self::SalleReunion => 'bi-people',
            self::EspacePleinAir => 'bi-tree',
            self::EspaceComplet => 'bi-house-heart',
            self::Residence => 'bi-buildings',
            default => 'bi-house-door',
        };
    }

    public function estSalle(): bool
    {
        return in_array($this, self::salles(), true);
    }

    /** « logement » ou « salle » : filtre de la recherche et écrans de l'application. */
    public function categorie(): string
    {
        return $this->estSalle() ? 'salle' : 'logement';
    }

    public function capaciteMax(): int
    {
        return $this->estSalle() ? self::CAPACITE_MAX_SALLE : self::CAPACITE_MAX_LOGEMENT;
    }

    /** Mot de la capacité : « voyageur » pour un logement, « place » pour une salle. */
    public function personne(): string
    {
        return $this->estSalle() ? 'place' : 'voyageur';   // une salle a des places, pas de « voyageurs »
    }

    /** @return list<self> */
    public static function salles(): array
    {
        return [self::SalleFetes, self::SalleConference, self::SalleReunion, self::EspacePleinAir, self::EspaceComplet];
    }

    /** @return list<self> */
    public static function logements(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => ! $t->estSalle()));
    }

    /** Catégorie => libellé. */
    public const CATEGORIES = ['logement' => 'Logements', 'salle' => 'Salles et espaces événementiels'];

    /** @return list<self> types d'une catégorie (« logement » ou « salle ») */
    public static function deCategorie(string $categorie): array
    {
        return $categorie === 'salle' ? self::salles() : self::logements();
    }
}
