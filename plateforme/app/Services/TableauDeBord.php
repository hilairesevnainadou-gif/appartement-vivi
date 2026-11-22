<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Chiffres du tableau de bord du propriétaire (ou du gestionnaire, sur les logements qui lui sont confiés) :
 * arrivées du jour, prochains séjours, revenus et occupation. Commun au site et à l'application mobile.
 */
class TableauDeBord
{
    /** Séjours qui rapportent au propriétaire : démarrés, terminés, ou consommés (client absent). */
    public const STATUTS_REVENUS = [StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];

    /** Séjours qui occupent le logement, pour le taux d'occupation. */
    public const STATUTS_OCCUPATION = [StatutReservation::Confirmee, ...self::STATUTS_REVENUS];

    public function donnees(User $utilisateur): array
    {
        $estProprietaire = $utilisateur->hasRole(Role::Proprietaire->value);
        // Gestionnaire : logements délégués, et ceux dont il est le gérant VIVI
        $logements = $estProprietaire ? $utilisateur->logements()->get()
            : Logement::whereIn('id', app(Arrivees::class)->logementsDe($utilisateur))->get();
        $ids = $logements->modelKeys();

        $aujourdhui = CarbonImmutable::today();
        $avec = ['client', 'logement', 'creneau', 'services'];

        $arrivees = Reservation::with($avec)->whereIn('logement_id', $ids)
            ->whereDate('debut', $aujourdhui)
            ->whereIn('statut', self::STATUTS_OCCUPATION)
            ->orderBy('debut')->get();

        $prochaines = Reservation::with($avec)->whereIn('logement_id', $ids)
            ->where('debut', '>=', $aujourdhui->addDay())
            ->where('debut', '<', $aujourdhui->addDays(8))
            ->where('statut', StatutReservation::Confirmee)
            ->orderBy('debut')->get();

        // Revenus et occupation sur le mois en cours et le mois précédent
        $debutMois = $aujourdhui->startOfMonth();
        $debutMoisPrecedent = $debutMois->subMonth();
        $sejours = Reservation::whereIn('logement_id', $ids)
            ->whereIn('statut', self::STATUTS_OCCUPATION)
            ->where('fin', '>', $debutMoisPrecedent)
            ->where('debut', '<', $aujourdhui->addDay())
            ->get(['logement_id', 'debut', 'fin', 'statut', 'montant_location']);

        $revenus = fn (CarbonImmutable $de, CarbonImmutable $a) => (int) $sejours
            ->filter(fn (Reservation $r) => in_array($r->statut, self::STATUTS_REVENUS, true) && $r->debut->gte($de) && $r->debut->lt($a))
            ->sum('montant_location');

        $publies = $logements->filter(fn (Logement $l) => $l->statut === StatutLogement::Publie)->modelKeys();

        // Revenus de location des 7 derniers jours (aujourd'hui compris)
        $jours = collect(range(6, 0))->map(fn (int $n) => $aujourdhui->subDays($n));
        $revenus7j = $jours->map(fn (CarbonImmutable $j) => ['jour' => $j, 'montant' => $revenus($j, $j->addDay())]);

        return [
            'estProprietaire' => $estProprietaire,
            'arrivees' => $arrivees,
            'prochaines' => $prochaines,
            'revenusMois' => $revenus($debutMois, $aujourdhui->addDay()),
            'revenusMoisPrecedent' => $revenus($debutMoisPrecedent, $debutMois),
            'occupation' => $this->occupation($sejours, $publies, $debutMois, $aujourdhui),
            'occupationPrecedente' => $this->occupation($sejours, $publies, $debutMoisPrecedent, $debutMois->subDay()),
            'revenus7j' => $revenus7j,
            'solde' => $estProprietaire ? $utilisateur->solde() : null,
            'retraits' => $estProprietaire ? $utilisateur->retraits()->latest()->limit(3)->get() : collect(),
            'abonnement' => $estProprietaire ? $utilisateur->abonnementActif()->with('formule')->first() : null,
            // Publiés, séparés : logements et salles (quotas distincts de l'abonnement)
            'nombrePublies' => $logements->filter(fn (Logement $l) => $l->statut === StatutLogement::Publie && ! $l->estSalle())->count(),
            'sallesPubliees' => $logements->filter(fn (Logement $l) => $l->statut === StatutLogement::Publie && $l->estSalle())->count(),
        ];
    }

    /**
     * Part des journées où chaque logement publié a reçu au moins un séjour, entre deux dates incluses.
     *
     * @param  Collection<int, Reservation>  $sejours
     * @param  list<int>  $logements
     */
    private function occupation(Collection $sejours, array $logements, CarbonImmutable $de, CarbonImmutable $a): int
    {
        if ($logements === []) {
            return 0;
        }

        $jours = CarbonPeriod::create($de, $a);
        $occupes = 0;
        foreach ($logements as $id) {
            $siens = $sejours->where('logement_id', $id);
            foreach ($jours as $jour) {
                $finJour = $jour->copy()->endOfDay();
                $occupes += $siens->contains(fn (Reservation $r) => $r->debut->lte($finJour) && $r->fin->gt($jour)) ? 1 : 0;
            }
        }

        return (int) round($occupes / (count($logements) * $jours->count()) * 100);
    }
}
