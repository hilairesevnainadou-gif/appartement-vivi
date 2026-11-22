<?php

namespace App\Services;

use App\Enums\Formule;
use App\Models\Creneau;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\Tarif;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Moteur de disponibilités d'un logement.
 *
 * Un séjour [début, fin] est libre si :
 *  - il commence dans le futur (avec une marge de 30 minutes) ;
 *  - aucun de ses jours n'est bloqué par le propriétaire ;
 *  - il ne chevauche aucune réservation qui occupe le logement (confirmée, en cours, ou en attente
 *    de paiement dont le créneau est encore retenu), en gardant entre deux séjours le temps de
 *    préparation fixé par le propriétaire.
 *
 * Les occupations sont chargées une fois par logement et par période, puis tout le calcul se fait en mémoire.
 */
class Disponibilites
{
    /** On ne réserve pas un séjour qui commence dans moins de 30 minutes. */
    public const MARGE_MINUTES = 30;

    /** Nombre maximal de jours pour la formule « journée ». */
    public const JOURS_MAX = 14;

    /** @var array<string, array{0: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>, 1: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>}> */
    private array $cache = [];

    /* ---------------------------------------------------------------------
     | Règle de base
     * --------------------------------------------------------------------- */

    /**
     * @param  bool  $avecMarge  false pour une réservation saisie par le propriétaire (client déjà sur place)
     */
    public function estLibre(Logement $logement, CarbonInterface $debut, CarbonInterface $fin, ?int $saufReservation = null, bool $avecMarge = true): bool
    {
        return $this->unitesLibres($logement, $debut, $fin, $saufReservation, $avecMarge) > 0;
    }

    /**
     * Nombre d'unités (chambres identiques d'une résidence) encore libres sur toute la période : 0 si aucune.
     * Un logement simple a une seule unité. Un jour bloqué bloque toutes les unités.
     */
    public function unitesLibres(Logement $logement, CarbonInterface $debut, CarbonInterface $fin, ?int $saufReservation = null, bool $avecMarge = true): int
    {
        $debut = CarbonImmutable::instance($debut);
        $fin = CarbonImmutable::instance($fin);
        $unites = max(1, (int) ($logement->unites ?? 1));

        if ($fin->lte($debut) || ($avecMarge ? $debut->lt(now()->addMinutes(self::MARGE_MINUTES)) : $fin->lte(now()))) {
            return 0;
        }

        [$occupations, $blocages] = $this->charger($logement, $debut->subDay(), $fin->addDay(), $saufReservation);
        $preparation = (int) $logement->temps_preparation_minutes;

        // Jours bloqués : un séjour ne peut toucher aucun jour bloqué
        foreach ($blocages as [$de, $a]) {
            if ($debut->lt($a) && $fin->gt($de)) {
                return 0;
            }
        }

        // Réservations existantes, temps de préparation compris de part et d'autre : chacune occupe une unité
        // sur [début − préparation, fin + préparation]. On compte le maximum d'unités occupées en même temps.
        $evenements = [];
        foreach ($occupations as [$de, $a]) {
            $occupeDe = $de->subMinutes($preparation);
            $occupeA = $a->addMinutes($preparation);
            if ($debut->lt($occupeA) && $fin->gt($occupeDe)) {
                $evenements[] = [max($occupeDe, $debut)->getTimestamp(), 1];
                $evenements[] = [min($occupeA, $fin)->getTimestamp(), -1];
            }
        }
        // À la même minute, une unité qui se libère compte avant celle qui se prend
        usort($evenements, fn ($x, $y) => $x[0] <=> $y[0] ?: $x[1] <=> $y[1]);
        $enCours = $maximum = 0;
        foreach ($evenements as [, $delta]) {
            $enCours += $delta;
            $maximum = max($maximum, $enCours);
        }

        return max(0, $unites - $maximum);
    }

    /* ---------------------------------------------------------------------
     | Formule « heure »
     * --------------------------------------------------------------------- */

    /**
     * Heures d'arrivée possibles un jour donné, avec la durée maximale réservable à partir de chacune.
     *
     * @return list<array{heure: string, debut: CarbonImmutable, duree_max: int, libre: bool}>
     */
    public function heures(Logement $logement, CarbonInterface $date): array
    {
        $tarif = $logement->tarif(Formule::Heure);
        if (! $tarif) {
            return [];
        }
        [$ouverture, $fermeture] = $this->plageHoraire($tarif, $date);
        $minimum = max(1, (int) $tarif->duree_minimum_heures);
        $this->charger($logement, $ouverture->subDay(), $fermeture->addDay());

        $heures = [];
        for ($debut = $ouverture; $debut->addHours($minimum)->lte($fermeture); $debut = $debut->addHour()) {
            $dureeMax = 0;
            for ($d = $minimum; $debut->addHours($d)->lte($fermeture); $d++) {
                if (! $this->estLibre($logement, $debut, $debut->addHours($d))) {
                    break;
                }
                $dureeMax = $d;
            }
            $heures[] = ['heure' => $debut->format('H:i'), 'debut' => $debut, 'duree_max' => $dureeMax, 'libre' => $dureeMax >= $minimum];
        }

        return $heures;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} ouverture et fermeture du jour (fermeture à 00:00 = minuit) */
    public function plageHoraire(Tarif $tarif, CarbonInterface $date): array
    {
        $jour = CarbonImmutable::instance($date)->startOfDay();
        $ouverture = $this->aHeure($jour, $tarif->heure_ouverture ?? '08:00');
        $fermeture = $this->aHeure($jour, $tarif->heure_fermeture ?? '22:00');
        if ($fermeture->lte($ouverture)) {
            $fermeture = $fermeture->addDay();
        }

        return [$ouverture, $fermeture];
    }

    /* ---------------------------------------------------------------------
     | Formule « demi-journée »
     * --------------------------------------------------------------------- */

    /** @return list<array{creneau: Creneau, debut: CarbonImmutable, fin: CarbonImmutable, libre: bool}> */
    public function creneaux(Logement $logement, CarbonInterface $date): array
    {
        if (! $logement->tarif(Formule::DemiJournee)) {
            return [];
        }

        return $logement->creneaux->sortBy('ordre')->values()->map(function (Creneau $c) use ($logement, $date) {
            [$debut, $fin] = $this->periodeCreneau($c, $date);

            return ['creneau' => $c, 'debut' => $debut, 'fin' => $fin, 'libre' => $this->estLibre($logement, $debut, $fin)];
        })->all();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function periodeCreneau(Creneau $creneau, CarbonInterface $date): array
    {
        $jour = CarbonImmutable::instance($date)->startOfDay();
        $debut = $this->aHeure($jour, $creneau->heure_debut);
        $fin = $this->aHeure($jour, $creneau->heure_fin);

        return [$debut, $fin->lte($debut) ? $fin->addDay() : $fin];
    }

    /* ---------------------------------------------------------------------
     | Formule « journée »
     * --------------------------------------------------------------------- */

    /**
     * Logement : arrivée le jour J, départ le jour J + n. Salle : elle se libère le soir même (jour J pour une journée) ;
     * une heure de fin avant l'heure de début signifie après minuit (08:00 → 02:00).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodeJournees(Tarif $tarif, CarbonInterface $date, int $jours, bool $salle = false): array
    {
        $jour = CarbonImmutable::instance($date)->startOfDay();
        if ($salle) {
            $debut = $this->aHeure($jour, $tarif->heure_arrivee ?? '08:00');
            $finDuJour = $this->aHeure($jour, $tarif->heure_depart ?? '22:00');

            return [$debut, ($finDuJour->lte($debut) ? $finDuJour->addDay() : $finDuJour)->addDays($jours - 1)];
        }

        return [
            $this->aHeure($jour, $tarif->heure_arrivee ?? '12:00'),
            $this->aHeure($jour->addDays($jours), $tarif->heure_depart ?? '11:00'),
        ];
    }

    /** Nombre de jours consécutifs réservables à partir de cette date (0 si l'arrivée est impossible). */
    public function joursMax(Logement $logement, CarbonInterface $date): int
    {
        $tarif = $logement->tarif(Formule::Journee);
        if (! $tarif) {
            return 0;
        }
        $max = 0;
        for ($n = 1; $n <= self::JOURS_MAX; $n++) {
            [$debut, $fin] = $this->periodeJournees($tarif, $date, $n, $logement->estSalle());
            if (! $this->estLibre($logement, $debut, $fin)) {
                break;
            }
            $max = $n;
        }

        return $max;
    }

    /* ---------------------------------------------------------------------
     | Résumé d'une journée (recherche)
     * --------------------------------------------------------------------- */

    /**
     * Formules encore réservables ce jour-là, pour la page de résultats.
     *
     * @return array<string, bool> clé = valeur de la formule
     */
    public function resume(Logement $logement, CarbonInterface $date): array
    {
        $resume = [];
        foreach (Formule::cases() as $formule) {
            if (! $logement->tarif($formule)) {
                continue;
            }
            $resume[$formule->value] = match ($formule) {
                Formule::Heure => collect($this->heures($logement, $date))->contains('libre', true),
                Formule::DemiJournee => collect($this->creneaux($logement, $date))->contains('libre', true),
                Formule::Journee => $this->joursMax($logement, $date) > 0,
            };
        }

        return $resume;
    }

    /* ---------------------------------------------------------------------
     | Outils
     * --------------------------------------------------------------------- */

    /** « 14:00 » ou « 14:00:00 » appliqué à un jour. */
    private function aHeure(CarbonImmutable $jour, string $heure): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $heure) + [1 => 0]);

        return $jour->setTime($h, $m);
    }

    /**
     * Occupations et blocages du logement autour de la période, mis en cache pour la requête.
     *
     * @return array{0: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>, 1: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>}
     */
    private function charger(Logement $logement, CarbonImmutable $de, CarbonImmutable $a, ?int $saufReservation = null): array
    {
        // Une fenêtre large (± 15 jours) évite de recharger à chaque heure testée
        $cle = $logement->getKey().'|'.$de->startOfMonth()->format('Y-m').'|'.$saufReservation;
        if (! isset($this->cache[$cle]) || $this->cache[$cle]['a']->lt($a)) {
            $debutFenetre = $de->startOfMonth()->subDays(15);
            $finFenetre = $a->endOfMonth()->addDays(15);

            $occupations = Reservation::query()
                ->where('logement_id', $logement->getKey())
                ->occupantes()
                ->when($saufReservation, fn ($q) => $q->whereKeyNot($saufReservation))
                ->where('debut', '<', $finFenetre)
                ->where('fin', '>', $debutFenetre)
                ->get(['debut', 'fin'])
                ->map(fn (Reservation $r) => [CarbonImmutable::instance($r->debut), CarbonImmutable::instance($r->fin)]);

            $blocages = $logement->blocages()
                ->whereDate('date_fin', '>=', $debutFenetre)
                ->whereDate('date_debut', '<=', $finFenetre)
                ->get(['date_debut', 'date_fin'])
                ->map(fn ($b) => [CarbonImmutable::instance($b->date_debut)->startOfDay(), CarbonImmutable::instance($b->date_fin)->addDay()->startOfDay()]);

            $this->cache[$cle] = ['a' => $finFenetre, 'donnees' => [$occupations, $blocages]];
        }

        return $this->cache[$cle]['donnees'];
    }

    /** À appeler après la création d'une réservation dans la même requête. */
    public function oublier(): void
    {
        $this->cache = [];
    }
}
