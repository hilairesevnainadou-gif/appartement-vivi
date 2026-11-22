<?php

namespace App\Support;

use App\Http\Resources\Api\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifications de l'espace « Notifications » vues par l'application mobile : même contenu que sur le site, plus
 * la catégorie et l'écran à ouvrir. Les notifications enregistrées avant l'ajout de ces champs sont classées
 * d'après leur type.
 */
class NotificationsApp
{
    public const CATEGORIES = ['reservation', 'paiement', 'offre', 'logement', 'compte'];

    /** Catégorie => types de notification (pour les anciennes notifications sans champ « categorie »). */
    private const TYPES = [
        'reservation' => ['ReservationConfirmee', 'NouvelleReservation', 'ReservationAnnulee', 'ReservationReportee', 'ReservationDeplacee', 'RappelArrivee', 'SejourCommence', 'DemandeAvis'],
        'paiement' => ['RemboursementVerse', 'RetraitTraite', 'CommissionAgent'],
        'offre' => ['BonusRecu', 'OffreVivi'],
        'logement' => ['DecisionLogement', 'GerantViviDesigne'],
    ];

    public static function categorie(DatabaseNotification $n): string
    {
        if (isset($n->data['categorie'])) {
            return $n->data['categorie'];
        }
        foreach (self::TYPES as $categorie => $types) {
            if (in_array(class_basename($n->type), $types, true)) {
                return $categorie;
            }
        }

        return 'compte';
    }

    public static function filtrerCategorie(Builder|Relation $q, string $categorie): Builder|Relation
    {
        $types = array_map(fn ($t) => 'App\\Notifications\\'.$t, self::TYPES[$categorie] ?? []);

        return $q->where(function ($q) use ($categorie, $types) {
            $q->where('data->categorie', $categorie)
                ->orWhere(fn ($q) => $q->whereNull('data->categorie')->when(
                    $categorie === 'compte',
                    fn ($q) => $q->whereNotIn('type', array_map(fn ($t) => 'App\\Notifications\\'.$t, array_merge(...array_values(self::TYPES)))),
                    fn ($q) => $q->whereIn('type', $types),
                ));
        });
    }

    /** @return array<string, mixed> */
    public static function versApi(DatabaseNotification $n): array
    {
        $d = $n->data;

        return [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'categorie' => self::categorie($n),
            'titre' => $d['titre'] ?? '',
            'texte' => $d['texte'] ?? '',
            'icone' => $d['icone'] ?? 'bi-bell',
            // Écran de l'application à ouvrir au toucher ; « lien » reste la page web équivalente
            'cible' => $d['cible'] ?? ['ecran' => 'notifications'],
            'lien' => $d['lien'] ?? null,
            'action' => $d['action'] ?? null,
            'lue' => $n->read_at !== null,
            'le' => Format::date($n->created_at),
        ];
    }
}
