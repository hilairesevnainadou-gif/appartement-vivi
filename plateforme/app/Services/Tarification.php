<?php

namespace App\Services;

use App\Enums\Formule;
use App\Models\Creneau;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\Service;
use App\Support\Montant;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Calcul du prix d'un séjour. C'est la seule source de vérité : le navigateur affiche ce que le serveur calcule.
 *
 *  - Location : prix à l'heure × nombre d'heures, prix du créneau (demi-journée), ou prix par jour × nombre de jours.
 *  - Services à la carte : seulement ceux proposés dans ce logement, au prix fixé par VIVI.
 *  - Commission VIVI : payée par le client, au taux des paramètres, sur la location et les services.
 */
class Tarification
{
    public function __construct(private readonly Disponibilites $disponibilites) {}

    /**
     * Période et prix d'un séjour à partir des choix du client.
     *
     * @param  array{heure?: string, duree?: int, creneau_id?: int, jours?: int}  $choix
     * @param  array<int|string, int>  $services  identifiant du service => quantité
     *
     * @throws InvalidArgumentException si la formule n'est pas proposée ou si les choix sont incohérents
     */
    public function devis(Logement $logement, Formule $formule, CarbonInterface $date, array $choix, array $services = []): Devis
    {
        $tarif = $logement->tarif($formule) ?? throw new InvalidArgumentException('Cette formule n’est pas proposée pour '.$logement->mot('ce').'.');
        $jour = CarbonImmutable::instance($date)->startOfDay();
        $creneauId = null;

        switch ($formule) {
            case Formule::Heure:
                $minimum = max(1, (int) $tarif->duree_minimum_heures);
                $duree = (int) ($choix['duree'] ?? $minimum);
                [$ouverture, $fermeture] = $this->disponibilites->plageHoraire($tarif, $jour);
                [$h, $m] = array_map('intval', explode(':', (string) ($choix['heure'] ?? $ouverture->format('H:i'))) + [1 => 0]);
                $debut = $jour->setTime($h, $m);
                $fin = $debut->addHours($duree);
                if ($duree < $minimum || $debut->lt($ouverture) || $fin->gt($fermeture)) {
                    throw new InvalidArgumentException("Choisissez au moins {$minimum} h entre {$ouverture->format('H:i')} et {$fermeture->format('H:i')}.");
                }
                $location = $tarif->prix * $duree;
                $detail = Montant::fcfa($tarif->prix).' × '.$duree.' h';
                break;

            case Formule::DemiJournee:
                /** @var Creneau|null $creneau */
                $creneau = $logement->creneaux->firstWhere('id', (int) ($choix['creneau_id'] ?? 0))
                    ?? throw new InvalidArgumentException('Choisissez un créneau.');
                [$debut, $fin] = $this->disponibilites->periodeCreneau($creneau, $jour);
                $location = $tarif->prix;
                $detail = 'Demi-journée · '.mb_strtolower($creneau->nom);
                $creneauId = $creneau->getKey();
                break;

            case Formule::Journee:
                $jours = (int) ($choix['jours'] ?? 1);
                if ($jours < 1 || $jours > Disponibilites::JOURS_MAX) {
                    throw new InvalidArgumentException('Choisissez entre 1 et '.Disponibilites::JOURS_MAX.' jours.');
                }
                [$debut, $fin] = $this->disponibilites->periodeJournees($tarif, $jour, $jours, $logement->estSalle());
                $location = $tarif->prix * $jours;
                $detail = Montant::fcfa($tarif->prix).' × '.Reservation::pluriel($jours, 'jour');
                break;
        }

        // Services : uniquement ceux du logement, actifs, au prix du catalogue
        $proposes = $logement->services->where('actif', true)->keyBy('id');
        $parDefaut = Parametres::get('services_beneficiaire');
        $lignes = [];
        foreach ($services as $id => $quantite) {
            $quantite = min(20, max(0, (int) $quantite));
            /** @var Service|null $service */
            $service = $proposes->get((int) $id);
            if ($service && $quantite > 0) {
                $lignes[] = [
                    'service_id' => $service->getKey(),
                    'nom' => $service->nom,
                    'quantite' => $quantite,
                    'prix_unitaire' => (int) $service->prix,
                    'montant' => (int) $service->prix * $quantite,
                    // VIVI par défaut, le propriétaire si l'administrateur a noté qu'il assure ce service lui-même
                    'beneficiaire' => $service->pivot?->beneficiaire ?: $parDefaut,
                ];
            }
        }
        $totalServices = array_sum(array_column($lignes, 'montant'));

        // Logement : taux unique ; salle : taux de la tranche où tombe le montant (Administration › Paramètres)
        $taux = Parametres::commissionPourcentage($logement, $location + $totalServices);
        $commission = (int) round(($location + $totalServices) * $taux / 100);

        return new Devis(
            formule: $formule,
            debut: $debut,
            fin: $fin,
            location: $location,
            detailLocation: $detail,
            lignesServices: $lignes,
            services: $totalServices,
            tauxCommission: $taux,
            commission: $commission,
            total: $location + $totalServices + $commission,
            creneauId: $creneauId,
        );
    }
}
