<?php

namespace App\Services;

use App\Enums\Formule;
use App\Models\Logement;
use App\Models\Reservation;
use App\Support\Montant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * État du bloc « Réserver » de la fiche logement, calculé par le serveur :
 * formules proposées et disponibles ce jour-là, heures d'arrivée libres, créneaux, nombre de jours possible,
 * et prix détaillé. Les choix incohérents (date passée, créneau complet, durée trop longue) sont corrigés.
 */
class SimulationSejour
{
    /** Nombre de jours pour lesquels le calendrier de réservation reçoit les dates complètes. */
    public const JOURS_CALENDRIER = 90;

    public function __construct(
        private readonly Disponibilites $disponibilites,
        private readonly Tarification $tarification,
    ) {}

    /** @param array<string, mixed> $demande paramètres reçus (date, formule, heure, duree, creneau_id, jours, voyageurs) */
    public function etat(Logement $logement, array $demande): array
    {
        $logement->loadMissing(['tarifs', 'creneaux', 'services']);
        $aujourdhui = CarbonImmutable::today();

        $date = $this->date($demande['date'] ?? null) ?? $aujourdhui;
        if ($date->lt($aujourdhui)) {
            $date = $aujourdhui;
        }
        // Une salle se réserve entière : pas de nombre de personnes
        $voyageurs = $logement->estSalle() ? 1 : min(max(1, (int) ($demande['voyageurs'] ?? 2)), (int) $logement->capacite);

        $proposees = $logement->formulesProposees();
        $formule = Formule::tryFrom((string) ($demande['formule'] ?? '')) ?? $logement->formuleParDefaut();
        if (! in_array($formule, $proposees, true)) {
            $formule = $proposees[0] ?? null;
        }

        $etat = [
            'date' => $date->toDateString(),
            'date_min' => $aujourdhui->toDateString(),
            'date_texte' => $date->translatedFormat('l j F'),
            'jours_complets' => [],
            'voyageurs' => $voyageurs,
            'capacite' => (int) $logement->capacite,
            'formule' => $formule?->value,
            'formules' => [],
            'heures' => [], 'heure' => null, 'duree' => null, 'duree_min' => null, 'duree_max' => null,
            'creneaux' => [], 'creneau_id' => null,
            'jours' => null, 'jours_max' => null,
            'aide' => null,
            'devis' => null,
            'message' => null,
        ];

        foreach ($proposees as $f) {
            $tarif = $logement->tarif($f);
            $etat['formules'][] = [
                'valeur' => $f->value, 'court' => $f->court(), 'libelle' => $f->getLabel(),
                'prix' => $tarif->prix, 'prix_texte' => Montant::fcfa($tarif->prix), 'par' => $f->par(), 'unite' => $f->unite(),
            ];
        }
        if (! $formule) {
            $etat['message'] = 'Ce logement n’a pas encore de tarif.';

            return $etat;
        }

        // Dates à griser dans le calendrier pour la formule choisie
        $etat['jours_complets'] = $this->disponibilites->joursComplets($logement, $formule, $aujourdhui, self::JOURS_CALENDRIER);

        $choix = [];
        switch ($formule) {
            case Formule::Heure:
                $tarif = $logement->tarif(Formule::Heure);
                $minimum = max(1, (int) $tarif->duree_minimum_heures);
                $heures = $this->disponibilites->heures($logement, $date);
                $etat['heures'] = array_map(fn ($h) => ['heure' => $h['heure'], 'duree_max' => $h['duree_max'], 'libre' => $h['libre']], $heures);
                $libres = array_values(array_filter($heures, fn ($h) => $h['libre']));
                if ($libres === []) {
                    $etat['message'] = 'Plus d’heure libre ce jour-là. Essayez une autre date ou une autre formule.';
                    break;
                }
                // Heure demandée si elle est libre, sinon la première libre à partir de 14:00, sinon la première libre
                $choisie = collect($libres)->firstWhere('heure', (string) ($demande['heure'] ?? ''))
                    ?? collect($libres)->first(fn ($h) => $h['heure'] >= '14:00')
                    ?? $libres[0];
                $duree = min(max($minimum, (int) ($demande['duree'] ?? max($minimum, 3))), $choisie['duree_max']);
                $etat = array_merge($etat, ['heure' => $choisie['heure'], 'duree' => $duree, 'duree_min' => $minimum, 'duree_max' => $choisie['duree_max']]);
                $choix = ['heure' => $choisie['heure'], 'duree' => $duree];
                $fin = $choisie['debut']->addHours($duree);
                $etat['aide'] = ($logement->estSalle() ? 'Fin à ' : 'Départ à ').$fin->format('H:i').($fin->subMinute()->isSameDay($choisie['debut']) ? '' : ' le lendemain').' · minimum '.$minimum.' h pour '.$logement->mot('ce').'.';
                break;

            case Formule::DemiJournee:
                $creneaux = $this->disponibilites->creneaux($logement, $date);
                $etat['creneaux'] = array_map(fn ($c) => [
                    'id' => $c['creneau']->getKey(), 'nom' => $c['creneau']->nom,
                    'de' => $c['debut']->format('H:i'), 'a' => $c['fin']->format('H:i'), 'libre' => $c['libre'],
                ], $creneaux);
                $libres = array_values(array_filter($creneaux, fn ($c) => $c['libre']));
                if ($libres === []) {
                    $etat['message'] = 'Tous les créneaux sont pris ce jour-là. Essayez une autre date.';
                    break;
                }
                $choisi = collect($libres)->first(fn ($c) => $c['creneau']->getKey() === (int) ($demande['creneau_id'] ?? 0)) ?? $libres[0];
                $etat['creneau_id'] = $choisi['creneau']->getKey();
                $choix = ['creneau_id' => $etat['creneau_id']];
                break;

            case Formule::Journee:
                $tarif = $logement->tarif(Formule::Journee);
                $max = $this->disponibilites->joursMax($logement, $date);
                if ($max === 0) {
                    $etat['message'] = ($logement->estSalle() ? 'Journée déjà prise' : 'Arrivée impossible').' ce jour-là. Essayez une autre date.';
                    break;
                }
                $jours = min(max(1, (int) ($demande['jours'] ?? 1)), $max);
                [$debut, $fin] = $this->disponibilites->periodeJournees($tarif, $date, $jours, $logement->estSalle());
                $etat = array_merge($etat, ['jours' => $jours, 'jours_max' => $max]);
                $choix = ['jours' => $jours];
                $etat['aide'] = ($logement->estSalle() ? Reservation::journeeEnClair($debut, $fin, true) : 'Arrivée à partir de '.$debut->format('H:i').' · départ avant '.$fin->format('H:i').' le '.$fin->translatedFormat('D j M'))
                    .($max < Disponibilites::JOURS_MAX ? ' · '.Reservation::pluriel($max, 'jour').' au plus à partir de cette date' : '');
                break;
        }

        if ($etat['message'] === null) {
            try {
                $etat['devis'] = $this->tarification->devis($logement, $formule, $date, $choix)->enTableau();
            } catch (InvalidArgumentException $e) {
                $etat['message'] = $e->getMessage();
            }
        }

        return $etat;
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valeur) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Texte « 3 voyageurs », réutilisé par les vues. */
    public static function voyageurs(int $n): string
    {
        return Reservation::pluriel($n, 'voyageur');
    }
}
