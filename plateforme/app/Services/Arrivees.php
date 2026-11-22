<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutReservation;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Accueil du client : vérification du QR Code (ou du code de secours) puis démarrage du séjour.
 * Le propriétaire ne voit que les réservations de ses logements ; le gestionnaire, celles des logements confiés.
 */
class Arrivees
{
    public function __construct(private readonly Caisse $caisse) {}

    /** Identifiants des logements que cette personne accueille. */
    public function logementsDe(User $pro): array
    {
        $ids = $pro->hasRole(Role::Proprietaire->value)
            ? Logement::where('proprietaire_id', $pro->getKey())->pluck('id')
            : $pro->logementsDelegues()->pluck('logements.id');
        // Gérant VIVI : les logements et salles dont le propriétaire a confié la gestion à VIVI
        $ids = $ids->merge(Logement::where('gerant_vivi_id', $pro->getKey())->where('mode_gestion', \App\Enums\ModeGestion::Vivi)->pluck('id'));

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Réservation correspondant au QR Code scanné ou au code de secours saisi.
     *
     * @throws ArriveeRefusee message à afficher tel quel
     */
    public function verifier(User $pro, string $saisie): Reservation
    {
        $saisie = trim($saisie);
        $logements = $this->logementsDe($pro);
        $requete = fn () => Reservation::query()->with(['client', 'logement', 'creneau', 'services'])->whereIn('logement_id', $logements);

        if (str_starts_with($saisie, 'VIVI1.')) {
            $reference = Reservation::referenceDuJeton($saisie)
                ?? throw new ArriveeRefusee('QR Code non reconnu', 'Ce QR Code n’a pas été émis par VIVI ou a été modifié. Demandez au client son code de secours.');
            $reservation = $requete()->where('reference', $reference)->first()
                ?? throw new ArriveeRefusee('Autre logement', 'Ce QR Code concerne un logement que vous ne gérez pas. Vérifiez l’adresse avec le client.');
        } elseif (preg_match('/^\d{6}$/', $saisie)) {
            // Plusieurs réservations peuvent partager un code : on prend celle d'aujourd'hui en priorité
            $reservation = $requete()->where('code_secours', $saisie)
                ->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee, StatutReservation::Annulee])
                ->orderByRaw('case when date(debut) = ? then 0 else 1 end', [today()->toDateString()])
                ->orderByDesc('debut')
                ->first()
                ?? throw new ArriveeRefusee('Code inconnu', 'Ce code ne correspond à aucune réservation de vos logements. Vérifiez les 6 chiffres avec le client.');
        } else {
            throw new ArriveeRefusee('Code incomplet', 'Le code de secours contient 6 chiffres.');
        }

        $this->controler($reservation);

        return $reservation;
    }

    /** Démarre le séjour et crédite le solde du propriétaire. Un QR Code ne sert qu'une fois. */
    public function demarrer(User $pro, Reservation $reservation): Reservation
    {
        $demarree = DB::transaction(function () use ($pro, $reservation) {
            $reservation = Reservation::with('logement.proprietaire')->whereKey($reservation->getKey())
                ->whereIn('logement_id', $this->logementsDe($pro))
                ->lockForUpdate()->firstOrFail();
            $this->controler($reservation);

            $reservation->update(['statut' => StatutReservation::EnCours, 'scanne_le' => now(), 'scanne_par' => $pro->getKey()]);
            $this->caisse->crediterSejour($reservation);

            return $reservation;
        });

        // Le client est prévenu : sa réservation est activée, le séjour commence
        \App\Support\Prevenir::sansBloquer(fn () => $demarree->loadMissing('client')->client->notify(new \App\Notifications\SejourCommence($demarree)));

        // Premier séjour d'un client invité : son parrain reçoit son bonus
        app(Parrainage::class)->recompenser($demarree);

        return $demarree;
    }

    /** Clients attendus aujourd'hui (confirmés, pas encore arrivés). */
    public function attendus(User $pro)
    {
        return Reservation::with(['client', 'logement', 'creneau'])
            ->whereIn('logement_id', $this->logementsDe($pro))
            ->where('statut', StatutReservation::Confirmee)
            ->whereDate('debut', today())
            ->orderBy('debut')
            ->get();
    }

    /** @throws ArriveeRefusee */
    private function controler(Reservation $r): void
    {
        $quand = $r->debut->translatedFormat('l j F');
        match (true) {
            $r->statut === StatutReservation::EnCours => throw new ArriveeRefusee('QR Code déjà utilisé', 'Le séjour de '.$r->client->name.' a déjà démarré à '.$r->scanne_le?->format('H:i').'. Un QR Code ne sert qu’une fois.'),
            $r->statut === StatutReservation::Terminee => throw new ArriveeRefusee('Séjour terminé', 'Cette réservation est terminée depuis le '.$quand.'.'),
            $r->statut === StatutReservation::Consommee => throw new ArriveeRefusee('Réservation consommée', 'Le client ne s’est pas présenté le '.$quand.' : la réservation est consommée.'),
            $r->statut === StatutReservation::Annulee => throw new ArriveeRefusee('Réservation annulée', 'Cette réservation a été annulée. Le client ne peut pas entrer avec ce code.'),
            $r->statut !== StatutReservation::Confirmee => throw new ArriveeRefusee('Paiement non reçu', 'Cette réservation n’est pas encore payée.'),
            // Accueil possible le jour de l'arrivée, pendant le séjour (arrivée tardive) ou dans l'heure qui
            // précède (lieu ouvert 24 h/24 : client en avance, avant minuit)
            ! $r->debut->isToday() && ! now()->between($r->debut->copy()->subHour(), $r->fin) => throw new ArriveeRefusee(
                'Réservation prévue un autre jour', 'Ce client est attendu le '.$quand.' à '.$r->debut->format('H:i').', pas aujourd’hui.'),
            default => null,
        };
    }
}
