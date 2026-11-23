<?php

namespace App\Services;

use App\Enums\Formule;
use App\Enums\StatutAvis;
use App\Enums\StatutReservation;
use App\Enums\TypeMouvement;
use App\Models\Avis;
use App\Models\Logement;
use App\Models\Reservation;
use App\Notifications\DemandeAvis;
use App\Notifications\RappelArrivee;
use App\Notifications\ReservationAnnulee;
use App\Notifications\ReservationReportee;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Après-vente côté client (lot 6) : report, annulation et remboursement, avis,
 * et tâches automatiques (fin de séjour, client absent, rappel la veille).
 *
 * Règles (cahier des charges, section 6.1, et décisions du 11 et 12/11/2026) :
 *  - Report gratuit jusqu'à 3 h avant l'arrivée, vers une date libre dans les 10 jours suivant la date prévue
 *    au départ ; même formule, même durée ; nouveau QR Code. Une réservation reportée n'est plus remboursable.
 *  - Annulation : la commission VIVI n'est jamais remboursée (décision du 12/11/2026). Heure et demi-journée :
 *    location et services remboursés jusqu'à 24 h avant, ensuite la location reste due ; journée : location et
 *    services remboursés jusqu'à 5 jours avant, ensuite la première journée et la moitié des suivantes restent dues.
 *    La location retenue revient au propriétaire.
 *  - Client absent à la fin du créneau : réservation consommée, la location revient au propriétaire.
 */
class ApresVente
{
    public function __construct(
        private readonly Disponibilites $disponibilites,
        private readonly Caisse $caisse,
    ) {}

    /* ---------------------------------------------------------------------
     | Report
     * --------------------------------------------------------------------- */

    /** Dernier jour possible pour la nouvelle date. */
    public function limiteReport(Reservation $r): CarbonImmutable
    {
        return CarbonImmutable::instance($r->debut_initial ?? $r->debut)->startOfDay()->addDays((int) Parametres::get('report_fenetre_jours'));
    }

    /**
     * Possibilités de report jour par jour (même formule, même durée). Les jours bloqués par le propriétaire ne sont
     * pas proposés. Pour chaque date : « creneaux », toutes les arrivées du jour encore à venir (« libre » à false si
     * elle est prise, pour l'afficher barrée), et « options », seulement les libres.
     *
     * @return list<array{date: CarbonImmutable, options: list<array{valeur: string, libelle: string, debut: CarbonImmutable, libre: bool}>, creneaux: list<array{valeur: string, libelle: string, debut: CarbonImmutable, libre: bool}>}>
     */
    public function joursDeReport(Reservation $r): array
    {
        $logement = Logement::with(['tarifs', 'creneaux'])->findOrFail($r->logement_id);
        $tarif = $logement->tarif($r->formule);
        if (! $tarif || ! $r->reportPossible()) {
            return [];
        }
        $this->disponibilites->oublier();
        $duree = (int) round($r->debut->diffInMinutes($r->fin));
        $limite = $this->limiteReport($r);
        $bloques = $logement->blocages()
            ->whereDate('date_fin', '>=', today())->whereDate('date_debut', '<=', $limite)
            ->get(['date_debut', 'date_fin']);
        $jours = [];
        for ($jour = CarbonImmutable::today(); $jour->lte($limite); $jour = $jour->addDay()) {
            // Jour bloqué par le propriétaire (travaux, fermeture) : il n'est pas proposé
            if ($bloques->contains(fn ($b) => $jour->betweenIncluded(CarbonImmutable::instance($b->date_debut)->startOfDay(), CarbonImmutable::instance($b->date_fin)->startOfDay()))) {
                continue;
            }
            $creneaux = $this->options($r, $logement, $jour, $duree);
            $jours[] = ['date' => $jour, 'options' => array_values(array_filter($creneaux, fn ($o) => $o['libre'])), 'creneaux' => $creneaux];
        }

        return $jours;
    }

    /**
     * Arrivées du jour pour la même durée, libres ou non. Les heures déjà passées et le créneau actuel ne sont pas listés.
     *
     * @return list<array{valeur: string, libelle: string, debut: CarbonImmutable, libre: bool}>
     */
    private function options(Reservation $r, Logement $logement, CarbonImmutable $jour, int $duree): array
    {
        $candidats = [];
        switch ($r->formule) {
            case Formule::Heure:
                foreach ($this->disponibilites->arrivees($logement->tarif(Formule::Heure), $jour, $duree) as [$d]) {
                    $f = $d->addMinutes($duree);
                    $candidats[] = [$d->format('H:i'), $d->format('H:i').' → '.$f->format('H:i').($f->subMinute()->isSameDay($d) ? '' : ' (lendemain)'), $d, $f];
                }
                break;
            case Formule::DemiJournee:
                foreach ($logement->creneaux->sortBy('ordre') as $c) {
                    [$d, $f] = $this->disponibilites->periodeCreneau($c, $jour);
                    $candidats[] = [(string) $c->id, $c->nom.' · '.$d->format('H:i').' – '.$f->format('H:i'), $d, $f];
                }
                break;
            case Formule::Journee:
                [$d, $f] = $this->disponibilites->periodeJournees($logement->tarif(Formule::Journee), $jour, $r->nombreJours(), $logement->estSalle());
                $candidats[] = ['journee', Reservation::journeeEnClair($d, $f, $logement->estSalle()), $d, $f];
                break;
        }

        $options = [];
        foreach ($candidats as [$valeur, $libelle, $d, $f]) {
            if ($d->eq($r->debut) || $d->lte(now())) {
                continue;
            }
            $options[] = ['valeur' => $valeur, 'libelle' => $libelle, 'debut' => $d,
                'libre' => $this->disponibilites->estLibre($logement, $d, $f, $r->getKey())];
        }

        return $options;
    }

    /** @throws ReservationImpossible */
    public function reporter(Reservation $reservation, string $date, string $option): Reservation
    {
        $r = DB::transaction(function () use ($reservation, $date, $option) {
            Logement::whereKey($reservation->logement_id)->lockForUpdate()->first();
            $r = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! $r->reportPossible()) {
                throw new ReservationImpossible('Le report n’est plus possible : il doit être demandé au plus tard '.Parametres::get('report_delai_minimum_heures').' h avant l’arrivée.');
            }
            $choisi = collect($this->joursDeReport($r))->firstWhere(fn ($j) => $j['date']->toDateString() === $date);
            $retenue = $choisi ? collect($choisi['options'])->first(fn ($o) => $o['valeur'] === $option && $o['libre']) : null;
            if (! $retenue) {
                throw new ReservationImpossible('Ce créneau n’est plus libre. Choisissez-en un autre.');
            }

            $duree = (int) round($r->debut->diffInMinutes($r->fin));
            $r->update([
                'debut_initial' => $r->debut_initial ?? $r->debut,
                'debut' => $retenue['debut'],
                'fin' => $retenue['debut']->addMinutes($duree),
                'creneau_id' => $r->formule === Formule::DemiJournee ? (int) $option : $r->creneau_id,
                'nombre_reports' => (int) $r->nombre_reports + 1,
                'code_secours' => (string) random_int(100000, 999999),   // nouveau QR Code : l'ancien n'est plus valable
                'rappel_envoye_le' => null,
            ]);

            return $r;
        });

        $this->prevenir(fn () => [
            $r->client->notify(new ReservationReportee($r->fresh(['logement', 'creneau']), pourProprietaire: false)),
            Notification::send($this->equipeDuLogement($r), new ReservationReportee($r->fresh(['logement', 'creneau', 'client']), pourProprietaire: true)),
        ]);

        return $r;
    }

    /* ---------------------------------------------------------------------
     | Annulation
     * --------------------------------------------------------------------- */

    public function annulable(Reservation $r): bool
    {
        return $r->statut === StatutReservation::Confirmee && ! $r->estManuelle() && (int) $r->nombre_reports === 0 && $r->debut->isFuture();
    }

    /**
     * Montant remboursé si le client annule maintenant.
     *
     * @return array{politique: string, regle: string, paye: int, retenue: int, rembourse: int}
     */
    /**
     * La commission VIVI n'est jamais remboursée (décision du 12/11/2026) ; la location et les services
     * le sont selon le barème. La part de location retenue revient au propriétaire.
     *
     * @return array{politique: string, regle: string, paye: int, commission: int, retenue_location: int, retenue: int, rembourse: int}
     */
    public function remboursement(Reservation $r): array
    {
        $paye = (int) $r->montant_total;
        $commission = (int) $r->montant_commission;
        // Location réellement payée : moins la remise d'un agent qui a réservé un logement qu'il promeut
        $location = (int) $r->montant_location - (int) $r->remise_agent;
        $heures = now()->diffInMinutes($r->debut, false) / 60;
        $flexible = $r->formule !== Formule::Journee;
        $rien = false;

        if ((int) $r->nombre_reports > 0) {
            [$rien, $retenueLocation, $regle] = [true, $location, 'Cette réservation a été reportée : elle n’est plus remboursable.'];
        } elseif ($heures <= 0) {
            [$rien, $retenueLocation, $regle] = [true, $location, 'L’heure d’arrivée est passée : la réservation n’est plus remboursable.'];
        } elseif ($flexible && $heures >= 24) {
            [$retenueLocation, $regle] = [0, 'Vous annulez plus de 24 h avant l’arrivée : la location et les services vous sont remboursés.'];
        } elseif ($flexible) {
            [$retenueLocation, $regle] = [$location, 'Vous annulez moins de 24 h avant l’arrivée : la location reste due, les services vous sont remboursés.'];
        } elseif ($heures >= 120) {
            [$retenueLocation, $regle] = [0, 'Vous annulez plus de 5 jours avant l’arrivée : la location et les services vous sont remboursés.'];
        } else {
            $jours = $r->nombreJours();
            $parJour = intdiv($location, max(1, $jours));
            $retenueLocation = min($location, $parJour + (int) round($parJour * 0.5 * ($jours - 1)));
            $regle = 'Vous annulez moins de 5 jours avant l’arrivée : la première journée et la moitié des suivantes restent dues, les services vous sont remboursés.';
        }

        $du = $rien ? 0 : max(0, $paye - $commission - $retenueLocation);
        // La part réglée avec un bonus revient d'abord sur le bonus du client, le reste lui est versé
        $bonus = (int) $r->montant_bonus;
        $bonusRendu = min($bonus, $du);

        return ['politique' => $flexible ? 'Flexible' : 'Modérée', 'regle' => $regle, 'paye' => $paye,
            'commission' => $commission, 'retenue_location' => $retenueLocation,
            'retenue' => $paye - $du, 'bonus' => $bonus, 'bonus_rendu' => $bonusRendu, 'rembourse' => $du - $bonusRendu];
    }

    /** @throws ReservationImpossible */
    public function annuler(Reservation $reservation, ?string $motif): Reservation
    {
        $r = DB::transaction(function () use ($reservation, $motif) {
            $r = Reservation::with('logement.proprietaire')->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! $this->annulable($r)) {
                throw new ReservationImpossible('Cette réservation ne peut plus être annulée.');
            }
            $calcul = $this->remboursement($r);
            $r->update([
                'statut' => StatutReservation::Annulee, 'annulee_le' => now(),
                'motif_annulation' => $motif ?: 'Annulée par le client',
                'montant_rembourse' => $calcul['rembourse'],
                'bonus_rendu' => app(Parrainage::class)->rendre($r, $calcul['bonus_rendu']),
            ]);
            if ($calcul['rembourse'] > 0) {
                DB::afterCommit(fn () => \App\Support\Equipe::prevenir('remboursements.gerer',
                    'Remboursement à verser : '.\App\Support\Montant::fcfa($calcul['rembourse']),
                    'Réservation '.$r->reference.' annulée par le client. À verser puis indiquer le mode de versement.',
                    \App\Filament\Resources\Remboursements\RemboursementResource::getUrl('index', panel: 'admin'),
                    'heroicon-o-arrow-uturn-left', 'warning', 'Voir les remboursements'));
            }
            // Le montant retenu (location) revient au propriétaire ; la commission reste à VIVI
            $partProprietaire = $calcul['retenue_location'];
            if ($partProprietaire > 0) {
                $r->logement->proprietaire->mouvementsSolde()->create([
                    'type' => TypeMouvement::CreditAnnulation, 'montant' => $partProprietaire, 'reservation_id' => $r->getKey(),
                    'libelle' => 'Annulation tardive '.$r->reference,
                ]);
            }

            return $r;
        });

        $this->prevenir(fn () => [
            $r->client->notify(new ReservationAnnulee($r->fresh('logement'), pourProprietaire: false)),
            Notification::send($this->equipeDuLogement($r), new ReservationAnnulee($r->fresh(['logement', 'client']), pourProprietaire: true)),
        ]);

        return $r;
    }

    /* ---------------------------------------------------------------------
     | Avis
     * --------------------------------------------------------------------- */

    /** @throws ReservationImpossible */
    public function laisserAvis(Reservation $r, int $note, ?string $commentaire): Avis
    {
        if ($r->statut !== StatutReservation::Terminee) {
            throw new ReservationImpossible('Vous pourrez donner votre avis après votre séjour.');
        }
        if ($r->avis()->exists()) {
            throw new ReservationImpossible('Vous avez déjà donné votre avis sur ce séjour.');
        }

        // Publié après relecture par VIVI (administration › Avis clients)
        $avis = Avis::create([
            'reservation_id' => $r->getKey(), 'client_id' => $r->client_id, 'logement_id' => $r->logement_id,
            'note' => max(1, min(5, $note)), 'commentaire' => $commentaire ? trim($commentaire) : null, 'statut' => StatutAvis::EnAttente,
        ]);
        \App\Support\Equipe::prevenir('avis.gerer', 'Avis à relire : '.$avis->note.'/5',
            $r->logement->titre.' · '.\Illuminate\Support\Str::limit((string) $avis->commentaire, 120),
            \App\Filament\Resources\Avis\AvisResource::getUrl('index', panel: 'admin'), 'heroicon-o-star', 'info', 'Relire');

        return $avis;
    }

    /* ---------------------------------------------------------------------
     | Tâches automatiques
     * --------------------------------------------------------------------- */

    /**
     * Fin de créneau : séjour en cours → terminé (et demande d'avis) ; client jamais arrivé → consommée
     * (la location revient au propriétaire, seuls lui et VIVI peuvent encore la déplacer).
     *
     * @return array{terminees: int, consommees: int}
     */
    public function cloturer(): array
    {
        $terminees = Reservation::with('client', 'logement')->where('statut', StatutReservation::EnCours)->where('fin', '<=', now())->get();
        foreach ($terminees as $r) {
            $r->update(['statut' => StatutReservation::Terminee, 'terminee_le' => now()]);
            if (! $r->estManuelle()) {
                $this->prevenir(fn () => $r->client->notify(new DemandeAvis($r)));
            }
        }

        // Réservations hors plateforme non scannées : simplement terminées (le propriétaire gère son client)
        Reservation::where('statut', StatutReservation::Confirmee)->where('origine', Reservation::ORIGINE_MANUELLE)
            ->where('fin', '<=', now())->update(['statut' => StatutReservation::Terminee, 'terminee_le' => now()]);

        $absents = Reservation::with('logement.proprietaire')->where('statut', StatutReservation::Confirmee)->where('fin', '<=', now())->get();
        foreach ($absents as $r) {
            DB::transaction(function () use ($r) {
                $r->update(['statut' => StatutReservation::Consommee]);
                $this->caisse->crediterSejour($r);
            });
        }

        return ['terminees' => $terminees->count(), 'consommees' => $absents->count()];
    }

    /** Rappel envoyé la veille (arrivée entre 18 h et 30 h plus tard), une seule fois. */
    public function rappeler(): int
    {
        $aRappeler = Reservation::with('client', 'logement', 'creneau')->where('statut', StatutReservation::Confirmee)
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)
            ->whereNull('rappel_envoye_le')
            ->whereBetween('debut', [now()->addHours(18), now()->addHours(30)])
            ->get();
        foreach ($aRappeler as $r) {
            $r->update(['rappel_envoye_le' => now()]);
            $this->prevenir(fn () => $r->client->notify(new RappelArrivee($r)));
        }

        return $aRappeler->count();
    }

    /* ---------------------------------------------------------------------
     | Outils
     * --------------------------------------------------------------------- */

    private function equipeDuLogement(Reservation $r)
    {
        // Propriétaire, gestionnaires et, si la gestion est confiée à VIVI, le gérant VIVI
        return $r->logement()->first()->equipeAccueil();
    }

    /** Un message qui ne part pas ne doit jamais annuler l'opération. */
    private function prevenir(callable $envoi): void
    {
        try {
            $envoi();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
