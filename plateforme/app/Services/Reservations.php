<?php

namespace App\Services;

use App\Enums\Formule;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NouvelleReservation;
use App\Notifications\ReservationConfirmee;
use App\Notifications\ReservationDeplacee;
use App\Paiement\EtatTransaction;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

/**
 * Cycle de vie d'une réservation jusqu'au paiement :
 *  1. bloquer() : le créneau est retenu pour le client pendant le paiement (15 min, paramétrable) ;
 *  2. preparerPaiement() : services à la carte, montants figés, paiement créé ;
 *  3. confirmer() ou echouer() : selon le statut lu chez FedaPay (retour du client ou webhook) ;
 *  4. libererExpirees() : les créneaux retenus sans paiement sont rendus.
 *
 * Toutes les vérifications de disponibilité se font sous verrou sur le logement, pour qu'un même
 * créneau ne puisse pas être vendu deux fois.
 */
class Reservations
{
    public function __construct(
        private readonly Disponibilites $disponibilites,
        private readonly SimulationSejour $simulation,
        private readonly Tarification $tarification,
        private readonly Parrainage $parrainage,
    ) {}

    /**
     * Retient le séjour demandé pour le client (statut « en attente de paiement »).
     *
     * @param  array<string, mixed>  $demande  date, formule, heure, duree, creneau_id, jours, voyageurs
     *
     * @throws ReservationImpossible
     *
     * @param  User|null  $agent  agent marketing dont le lien a amené le client (déjà vérifié par AgentsMarketing::agentPour)
     */
    public function bloquer(User $client, Logement $logement, array $demande, ?User $agent = null): Reservation
    {
        return DB::transaction(function () use ($client, $logement, $demande, $agent) {
            $logement = Logement::whereKey($logement->getKey())->lockForUpdate()->firstOrFail();
            $this->disponibilites->oublier();

            if (! $logement->estVisible()) {
                throw new ReservationImpossible(ucfirst($logement->mot('ce')).' n’est plus proposé'.($logement->mot() === 'salle' ? 'e' : '').' à la réservation.');
            }

            // Un client qui revient en arrière abandonne sa retenue précédente sur ce logement
            Reservation::where('client_id', $client->getKey())->where('logement_id', $logement->getKey())
                ->where('statut', StatutReservation::EnAttentePaiement)
                ->whereDoesntHave('paiements', fn ($q) => $q->where('statut', StatutPaiement::Reussi))
                ->get()->each(function (Reservation $ancienne) {
                    $this->parrainage->liberer($ancienne);
                    $ancienne->update(['statut' => StatutReservation::Annulee, 'annulee_le' => now(), 'motif_annulation' => 'Remplacée par une nouvelle demande du client']);
                });

            $etat = $this->simulation->etat($logement, $demande);
            // Le simulateur corrige les choix impossibles : on vérifie qu'il n'a rien changé à la demande
            $identique = $etat['formule'] === ($demande['formule'] ?? null)
                && $etat['date'] === ($demande['date'] ?? null)
                && match ($etat['formule']) {
                    Formule::Heure->value => $etat['heure'] === ($demande['heure'] ?? null) && $etat['duree'] === (int) ($demande['duree'] ?? 0),
                    Formule::DemiJournee->value => $etat['creneau_id'] === (int) ($demande['creneau_id'] ?? 0),
                    Formule::Journee->value => $etat['jours'] === (int) ($demande['jours'] ?? 0),
                    default => false,
                };
            if (! $etat['devis'] || ! $identique) {
                throw new ReservationImpossible($etat['message'] ?? 'Ce créneau vient d’être pris. Choisissez-en un autre : les disponibilités sont à jour.');
            }

            $devis = $etat['devis'];
            $partMarketing = $logement->partMarketing((int) $devis['location']);
            // Agent qui réserve un logement qu'il promeut : il ne paie pas sa propre part (rien à verser à personne)
            $remiseAgent = $this->estAgentDuLogement($client, $logement) ? $partMarketing : 0;

            return Reservation::create([
                'reference' => Reservation::nouvelleReference(),
                'client_id' => $client->getKey(),
                'logement_id' => $logement->getKey(),
                'formule' => $etat['formule'],
                'creneau_id' => $etat['creneau_id'],
                'debut' => CarbonImmutable::parse($devis['debut']),
                'fin' => CarbonImmutable::parse($devis['fin']),
                'voyageurs' => $etat['voyageurs'],
                'statut' => StatutReservation::EnAttentePaiement,
                'bloque_jusqua' => now()->addMinutes((int) Parametres::get('blocage_creneau_minutes')),
                'montant_location' => $devis['location'],
                'montant_services' => 0,
                'taux_commission' => $devis['taux_commission'],
                'montant_commission' => $devis['commission'],
                'montant_total' => $devis['total'] - $remiseAgent,
                // Part marketing : règle du logement figée maintenant, prise sur la part du propriétaire
                'agent_id' => $remiseAgent > 0 ? null : $agent?->getKey(),
                'marketing_type' => $logement->marketing_type,
                'marketing_valeur' => (float) $logement->marketing_valeur,
                'montant_marketing' => $partMarketing,
                'remise_agent' => $remiseAgent,
                // Gestion confiée à VIVI : rémunération du contrat figée maintenant, prise sur la part du propriétaire
                'gestion_type' => $logement->estGereParVivi() ? $logement->gestion_type : null,
                'gestion_valeur' => $logement->estGereParVivi() ? (float) $logement->gestion_valeur : 0,
                'montant_gestion' => $logement->partGestion((int) $devis['location'], (int) $devis['location'] - $partMarketing,
                    $etat['formule'] === Formule::Journee->value ? (int) $etat['jours'] : 1),
                'services_beneficiaire' => Parametres::get('services_beneficiaire'),
                'code_secours' => (string) random_int(100000, 999999),
            ]);
        });
    }

    /** Prix avec les services choisis et, si le client le demande, la part payable avec son bonus. */
    public function devisAvecBonus(Reservation $reservation, array $services, bool $utiliserBonus): Devis
    {
        $devis = $this->devis($reservation, $services);

        return $utiliserBonus
            ? $devis->avecBonus($this->parrainage->applicable($reservation->client, $devis->total, $reservation))
            : $devis;
    }

    /** Prix de la réservation retenue avec les services choisis (bloc récapitulatif du tunnel). */
    public function devis(Reservation $reservation, array $services = []): Devis
    {
        $logement = $reservation->logement->loadMissing(['tarifs', 'creneaux', 'services']);
        $debut = CarbonImmutable::instance($reservation->debut);
        $choix = match ($reservation->formule) {
            Formule::Heure => ['heure' => $debut->format('H:i'), 'duree' => (int) round($debut->diffInMinutes($reservation->fin) / 60)],
            Formule::DemiJournee => ['creneau_id' => $reservation->creneau_id],
            Formule::Journee => ['jours' => $reservation->nombreJours()],
        };

        try {
            $devis = $this->tarification->devis($logement, $reservation->formule, $debut->startOfDay(), $choix, $services);
        } catch (InvalidArgumentException $e) {
            throw new ReservationImpossible('Les tarifs de ce logement ont changé. Recommencez votre réservation.', previous: $e);
        }

        // Réservation d'un agent sur un logement qu'il promeut : sa part marketing est déduite du total
        return (int) $reservation->remise_agent > 0 ? $devis->avecRemiseAgent($reservation->calculerPartMarketing($devis->location)) : $devis;
    }

    /** Le client est-il agent marketing de ce logement ? */
    private function estAgentDuLogement(User $client, Logement $logement): bool
    {
        return $client->hasRole(\App\Enums\Role::AgentMarketing->value)
            && $client->logementsAgent()->whereKey($logement->getKey())->exists();
    }

    /**
     * Fige les services et les montants, prolonge si besoin la retenue du créneau, et crée le paiement.
     *
     * @param  array<int|string, int>  $services
     *
     * @throws ReservationImpossible
     */
    public function preparerPaiement(Reservation $reservation, array $services, string $passerelle, bool $utiliserBonus = false): Paiement
    {
        return DB::transaction(function () use ($reservation, $services, $passerelle, $utiliserBonus) {
            Logement::whereKey($reservation->logement_id)->lockForUpdate()->first();
            $reservation = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->disponibilites->oublier();

            if ($reservation->statut !== StatutReservation::EnAttentePaiement) {
                throw new ReservationImpossible('Cette réservation n’attend plus de paiement.');
            }
            // Retenue expirée : on la renouvelle si le créneau est encore libre
            if ($reservation->bloque_jusqua->isPast()
                && ! $this->disponibilites->estLibre($reservation->logement, $reservation->debut, $reservation->fin, $reservation->getKey())) {
                throw new ReservationImpossible('Le délai de paiement est dépassé et ce créneau a été pris entre-temps. Choisissez-en un autre.');
            }

            $devis = $this->devis($reservation, $services);
            $reservation->services()->delete();
            foreach ($devis->lignesServices as $ligne) {
                $reservation->services()->create([
                    'service_id' => $ligne['service_id'], 'nom' => $ligne['nom'], 'prix_unitaire' => $ligne['prix_unitaire'],
                    'quantite' => $ligne['quantite'], 'montant' => $ligne['montant'], 'beneficiaire' => $ligne['beneficiaire'],
                ]);
            }
            $beneficiaires = array_unique(array_column($devis->lignesServices, 'beneficiaire'));
            $reservation->update([
                'montant_services_proprietaire' => array_sum(array_column(array_filter($devis->lignesServices, fn ($l) => $l['beneficiaire'] === 'proprietaire'), 'montant')),
                'services_beneficiaire' => count($beneficiaires) > 1 ? 'mixte' : ($beneficiaires[0] ?? Parametres::get('services_beneficiaire')),
                'bloque_jusqua' => now()->addMinutes((int) Parametres::get('blocage_creneau_minutes')),
                'montant_location' => $devis->location,
                'montant_services' => $devis->services,
                'taux_commission' => $devis->tauxCommission,
                'montant_commission' => $devis->commission,
                'montant_total' => $devis->total,
                'montant_marketing' => $partMarketing = $reservation->calculerPartMarketing($devis->location),
                'montant_gestion' => $reservation->calculerPartGestion($devis->location, $partMarketing),
                'remise_agent' => $devis->remiseAgent,
            ]);

            // Un paiement en cours non abouti est abandonné : le nouveau le remplace, avec le bonus recalculé
            $reservation->paiements()->where('statut', StatutPaiement::EnAttente)->update(['statut' => StatutPaiement::Echoue]);
            $this->parrainage->liberer($reservation);
            $bonus = $utiliserBonus ? $this->parrainage->reserver($reservation, $this->parrainage->applicable($reservation->client, $devis->total)) : 0;

            return $reservation->paiements()->create([
                'payeur_id' => $reservation->client_id,
                'passerelle' => $passerelle,
                'montant' => $devis->total - $bonus,
                'statut' => StatutPaiement::EnAttente,
            ]);
        });
    }

    /**
     * Paiement reçu : la réservation est confirmée et les messages partent. Sans effet si c'est déjà fait
     * (le retour du client et le webhook peuvent arriver tous les deux).
     */
    public function confirmer(Paiement $paiement, EtatTransaction $etat): Reservation
    {
        $resultat = DB::transaction(function () use ($paiement, $etat) {
            $paiement = Paiement::whereKey($paiement->getKey())->lockForUpdate()->firstOrFail();
            /** @var Reservation $reservation */
            $reservation = Reservation::whereKey($paiement->payable_id)->lockForUpdate()->firstOrFail();

            if ($paiement->statut === StatutPaiement::Reussi) {
                return [$reservation, false];
            }
            $paiement->update([
                'statut' => StatutPaiement::Reussi,
                'paye_le' => now(),
                'moyen' => $etat->moyen ?? $paiement->moyen,
                'donnees' => array_merge($paiement->donnees ?? [], ['transaction' => $etat->donnees]),
            ]);

            if ($reservation->statut !== StatutReservation::EnAttentePaiement) {
                return [$reservation, false];
            }

            // Paiement arrivé après l'expiration de la retenue, et créneau repris entre-temps :
            // la réservation est annulée et le montant sera remboursé par la caisse
            $this->disponibilites->oublier();
            if ($reservation->bloque_jusqua->isPast()
                && ! $this->disponibilites->estLibre($reservation->logement, $reservation->debut, $reservation->fin, $reservation->getKey())) {
                $bonusRendu = $this->parrainage->rendre($reservation, (int) $reservation->montant_bonus);
                $reservation->update([
                    'statut' => StatutReservation::Annulee, 'annulee_le' => now(),
                    'motif_annulation' => 'Paiement reçu après l’expiration du créneau, repris entre-temps : remboursement intégral.',
                    'montant_rembourse' => $reservation->montantPaye(),
                    'bonus_rendu' => $bonusRendu,
                ]);

                return [$reservation, false];
            }

            $reservation->update(['statut' => StatutReservation::Confirmee, 'bloque_jusqua' => null]);

            return [$reservation, true];
        });

        [$reservation, $nouvelle] = $resultat;
        if ($nouvelle) {
            $this->prevenir($reservation->fresh(['client', 'logement.proprietaire', 'logement.gestionnaires', 'services', 'creneau']));
        }

        return $reservation;
    }

    /** Paiement refusé ou abandonné : le client peut réessayer tant que le créneau est retenu. */
    public function echouer(Paiement $paiement, EtatTransaction $etat): void
    {
        $paiement->refresh();
        if ($paiement->statut === StatutPaiement::EnAttente) {
            $paiement->update([
                'statut' => StatutPaiement::Echoue,
                'donnees' => array_merge($paiement->donnees ?? [], ['transaction' => $etat->donnees]),
            ]);
            app(Surveillance::class)->paiementEchoue();   // beaucoup d'échecs d'un coup : FedaPay ou Mobile Money en panne
        }
    }

    /**
     * Déplacement par le propriétaire ou l'équipe VIVI : la même durée, à une nouvelle date et heure.
     * C'est le seul moyen de déplacer une réservation consommée (client absent) ou à moins de 3 h de l'arrivée.
     *
     * @throws ReservationImpossible
     */
    public function deplacer(Reservation $reservation, CarbonImmutable $nouveauDebut, User $auteur): Reservation
    {
        $deplacee = DB::transaction(function () use ($reservation, $nouveauDebut, $auteur) {
            Logement::whereKey($reservation->logement_id)->lockForUpdate()->first();
            $r = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->disponibilites->oublier();

            if (! in_array($r->statut, [StatutReservation::Confirmee, StatutReservation::Consommee], true)) {
                throw new ReservationImpossible('Seule une réservation confirmée ou consommée (client absent) peut être déplacée.');
            }
            $duree = (int) round($r->debut->diffInMinutes($r->fin));
            $nouvelleFin = $nouveauDebut->addMinutes($duree);
            if (! $this->disponibilites->estLibre($r->logement, $nouveauDebut, $nouvelleFin, $r->getKey())) {
                throw new ReservationImpossible('Le logement n’est pas libre à ce moment-là (réservation, temps de préparation ou jour bloqué).');
            }

            $r->update([
                'debut_initial' => $r->debut_initial ?? $r->debut,
                'debut' => $nouveauDebut,
                'fin' => $nouvelleFin,
                'statut' => StatutReservation::Confirmee,
                'deplacee_par' => $auteur->getKey(),
            ]);

            return $r;
        });

        try {
            $deplacee->client->notify(new ReservationDeplacee($deplacee->fresh(['logement', 'creneau'])));
        } catch (Throwable $e) {
            report($e);
        }

        return $deplacee;
    }

    /**
     * Réservation saisie par le propriétaire ou le gestionnaire pour un client venu hors de la plateforme
     * (téléphone, sur place). Elle occupe le calendrier comme les autres, sans paiement en ligne ni commission VIVI :
     * le propriétaire encaisse lui-même, rien n'est crédité sur son solde.
     *
     * @param  array{formule: string, date: string, heure?: ?string, duree?: ?int, creneau_id?: ?int, jours?: ?int, voyageurs?: ?int,
     *               nom: string, telephone?: ?string, montant?: ?int, note?: ?string}  $d
     *
     * @throws ReservationImpossible
     */
    public function ajouterManuelle(User $auteur, Logement $logement, array $d): Reservation
    {
        $formule = Formule::tryFrom($d['formule']) ?? throw new ReservationImpossible('Choisissez une formule proposée pour ce logement.');

        return DB::transaction(function () use ($auteur, $logement, $d, $formule) {
            $logement = Logement::with(['tarifs', 'creneaux', 'services'])->whereKey($logement->getKey())->lockForUpdate()->firstOrFail();
            $this->disponibilites->oublier();
            try {
                $devis = $this->tarification->devis($logement, $formule, CarbonImmutable::parse($d['date']), [
                    'heure' => $d['heure'] ?? null, 'duree' => $d['duree'] ?? null, 'creneau_id' => $d['creneau_id'] ?? null, 'jours' => $d['jours'] ?? null,
                ]);
            } catch (InvalidArgumentException $e) {
                throw new ReservationImpossible($e->getMessage(), previous: $e);
            }
            $voyageurs = $logement->estSalle() ? 1 : (int) ($d['voyageurs'] ?? 1);   // une salle se réserve entière
            if ($voyageurs > (int) $logement->capacite) {
                throw new ReservationImpossible('Ce logement accueille '.$logement->personnes().' au maximum.');
            }
            if (! $this->disponibilites->estLibre($logement, $devis->debut, $devis->fin, avecMarge: false)) {
                throw new ReservationImpossible($logement->unites > 1
                    ? 'Toutes les chambres sont occupées sur cette période (réservation, temps de préparation ou jour bloqué).'
                    : ucfirst($logement->mot('le')).' n’est pas libre sur cette période (réservation, temps de préparation ou jour bloqué).');
            }

            // Prix convenu (le tarif du logement par défaut) et ce qui a déjà été encaissé (acompte ou tout)
            $montant = isset($d['montant']) && $d['montant'] !== null ? (int) $d['montant'] : $devis->location;
            $encaisse = isset($d['encaisse']) && $d['encaisse'] !== null ? (int) $d['encaisse'] : 0;
            if ($encaisse > $montant) {
                throw new ReservationImpossible('Le montant encaissé ne peut pas dépasser le prix du séjour.');
            }

            return Reservation::create([
                'reference' => Reservation::nouvelleReference(),
                'client_id' => $this->clientHorsPlateforme($d['nom'], $d['telephone'] ?? null)->getKey(),
                'logement_id' => $logement->getKey(),
                'formule' => $formule,
                'creneau_id' => $devis->creneauId,
                'debut' => $devis->debut,
                'fin' => $devis->fin,
                'voyageurs' => $voyageurs,
                'statut' => StatutReservation::Confirmee,
                'origine' => Reservation::ORIGINE_MANUELLE,
                'cree_par' => $auteur->getKey(),
                'note_interne' => $d['note'] ?? null,
                'montant_location' => $montant,
                'montant_services' => 0,
                'taux_commission' => 0,
                'montant_commission' => 0,
                'montant_total' => $montant,
                'montant_encaisse' => $encaisse,
                'services_beneficiaire' => 'proprietaire',
                'code_secours' => (string) random_int(100000, 999999),
            ]);
        });
    }

    /** Paiement reçu sur place pour une réservation hors plateforme : s'ajoute à l'encaissé, sans dépasser le prix. */
    public function encaisser(Reservation $reservation, int $montant): Reservation
    {
        if (! $reservation->estManuelle() || $reservation->statut === StatutReservation::Annulee) {
            throw new ReservationImpossible('Seules les réservations saisies hors plateforme s’encaissent ici.');
        }
        if ($montant <= 0 || $montant > $reservation->resteAPayer()) {
            throw new ReservationImpossible('Le montant doit être compris entre 1 et '.\App\Support\Montant::fcfa($reservation->resteAPayer()).'.');
        }
        $reservation->update(['montant_encaisse' => (int) $reservation->montant_encaisse + $montant]);

        return $reservation;
    }

    /** Annulation d'une réservation saisie hors plateforme (aucun remboursement : rien n'a été payé en ligne). */
    public function annulerManuelle(Reservation $reservation, User $auteur): Reservation
    {
        if (! $reservation->estManuelle() || $reservation->statut !== StatutReservation::Confirmee) {
            throw new ReservationImpossible('Seule une réservation saisie par vous, pas encore commencée, peut être retirée.');
        }
        $reservation->update([
            'statut' => StatutReservation::Annulee, 'annulee_le' => now(),
            'motif_annulation' => 'Retirée du calendrier par '.$auteur->name,
        ]);
        $this->disponibilites->oublier();

        return $reservation;
    }

    /**
     * Client d'une réservation hors plateforme : son compte s'il en a un avec ce numéro, sinon un compte client
     * créé à son nom (il pourra s'y connecter avec son numéro). Sans numéro, une fiche sans accès (inactive).
     */
    private function clientHorsPlateforme(string $nom, ?string $telephone): User
    {
        $telephone = $telephone ? \App\Support\Telephone::normaliser($telephone) : null;
        if ($telephone && ($existant = User::where('telephone', $telephone)->first())) {
            return $existant;
        }
        $client = User::create(['name' => trim($nom), 'telephone' => $telephone, 'actif' => (bool) $telephone]);
        $client->assignRole(\App\Enums\Role::Client->value);

        return $client;
    }

    /** Retenues expirées depuis plus d'une heure, sans paiement : réservation annulée, créneau rendu. */
    public function libererExpirees(): int
    {
        $expirees = Reservation::where('statut', StatutReservation::EnAttentePaiement)
            ->where('bloque_jusqua', '<', now()->subHour())
            ->whereDoesntHave('paiements', fn ($q) => $q->where('statut', StatutPaiement::Reussi))
            ->get();

        foreach ($expirees as $r) {
            $this->parrainage->liberer($r);
            $r->update(['statut' => StatutReservation::Annulee, 'annulee_le' => now(), 'motif_annulation' => 'Paiement non finalisé']);
            $r->paiements()->where('statut', StatutPaiement::EnAttente)->update(['statut' => StatutPaiement::Echoue]);
        }

        return $expirees->count();
    }

    /** Messages au client et au propriétaire (et à ses gestionnaires), sans bloquer la confirmation. */
    private function prevenir(Reservation $reservation): void
    {
        try {
            $reservation->client->notify(new ReservationConfirmee($reservation));
            Notification::send(
                $reservation->logement->equipeAccueil(),   // propriétaire, gestionnaires, gérant VIVI
                new NouvelleReservation($reservation),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
