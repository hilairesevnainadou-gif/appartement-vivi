<?php

namespace App\Http\Controllers\Client;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Models\Logement;
use App\Models\Reservation;
use App\Paiement\EchecPaiement;
use App\Paiement\Passerelle;
use App\Services\CoordonneesClient;
use App\Services\Parrainage;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use App\Support\Parametres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tunnel de réservation : retenue du créneau, services, coordonnées, paiement, puis billet et reçu. */
class ReservationController extends Controller
{
    public function __construct(private readonly Reservations $reservations) {}

    /** « Continuer » sur la fiche logement : le créneau est retenu pour le client pendant le paiement. */
    public function bloquer(Request $request, string $slug): RedirectResponse
    {
        $logement = Logement::where('slug', $slug)->firstOrFail();
        $demande = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'formule' => ['required', 'string'],
            'heure' => ['nullable', 'date_format:H:i'],
            'duree' => ['nullable', 'integer', 'min:1', 'max:24'],
            'creneau_id' => ['nullable', 'integer'],
            'jours' => ['nullable', 'integer', 'min:1', 'max:30'],
            'voyageurs' => $logement->regleVoyageurs(),   // sans objet pour une salle : elle se réserve entière
        ]);

        $agents = app(\App\Services\AgentsMarketing::class);
        $agent = $agents->agentPour($logement, $agents->codeRetenu($request, $logement), $request->user());

        try {
            $reservation = $this->reservations->bloquer($request->user(), $logement, $demande, $agent);
        } catch (ReservationImpossible $e) {
            return redirect()->route('logement', ['slug' => $slug] + $demande)->withErrors(['reservation' => $e->getMessage()]);
        }

        return redirect()->route('reservation.paiement', $reservation->reference);
    }

    /** Étapes « Services » et « Paiement ». */
    public function paiement(Request $request, string $reference): View|RedirectResponse
    {
        $reservation = $this->sienne($request, $reference);
        if ($reservation->statut !== StatutReservation::EnAttentePaiement) {
            return redirect()->route('reservation.voir', $reference);
        }
        $reservation->load(['logement.photos', 'logement.services' => fn ($q) => $q->where('actif', true)->orderBy('ordre'), 'creneau', 'services']);

        $bonusDisponible = app(Parrainage::class)->disponible($request->user()) + (int) $reservation->montant_bonus;
        $utiliserBonus = $bonusDisponible > 0 && (bool) old('utiliser_bonus', true);

        return view('reservation.tunnel', [
            'reservation' => $reservation,
            'logement' => $reservation->logement,
            'devis' => $this->reservations->devisAvecBonus($reservation, $reservation->services->pluck('quantite', 'service_id')->all(), $utiliserBonus)->enTableau(),
            'bonusDisponible' => $bonusDisponible,
            'utiliserBonus' => $utiliserBonus,
            'plafondBonus' => (int) Parametres::get('bonus_plafond_pourcentage'),
            'choisis' => $reservation->services->pluck('quantite', 'service_id')->all(),
            'utilisateur' => $request->user(),
        ]);
    }

    /** Prix recalculé par le serveur quand le client ajoute ou retire un service. */
    public function devis(Request $request, string $reference): JsonResponse
    {
        $reservation = $this->sienne($request, $reference);

        return response()->json($this->reservations->devisAvecBonus($reservation, $this->services($request), $request->boolean('bonus'))->enTableau());
    }

    /** « Payer » : coordonnées enregistrées (CoordonneesClient), montants figés, puis direction la page de paiement. */
    public function payer(Request $request, string $reference, Passerelle $passerelle): RedirectResponse
    {
        $reservation = $this->sienne($request, $reference);
        // Nom, numéro WhatsApp (tout pays) et email facultatif : voir App\Services\CoordonneesClient
        $donnees = $request->validate(CoordonneesClient::regles() + [
            'cgv' => ['accepted'],
            'services' => ['array'],
            'services.*' => ['integer', 'min:0', 'max:20'],
        ], CoordonneesClient::messages() + ['cgv.accepted' => 'Acceptez les conditions pour continuer.']);
        app(CoordonneesClient::class)->enregistrer($reservation, $request->user(), $donnees);

        try {
            $paiement = $this->reservations->preparerPaiement($reservation, $this->services($request), $passerelle->nom(), $request->boolean('utiliser_bonus'));
            $url = $passerelle->demarrer($paiement, route('paiement.retour', $paiement));
        } catch (ReservationImpossible|EchecPaiement $e) {
            return back()->withInput()->withErrors(['paiement' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }

    /** Billet : QR Code, code de secours, détail du paiement. */
    public function voir(Request $request, string $reference): View
    {
        $reservation = $this->sienne($request, $reference);
        $reservation->load(['logement.photos', 'creneau', 'services', 'avis', 'client', 'paiements' => fn ($q) => $q->latest()]);

        return view('reservation.billet', [
            'reservation' => $reservation,
            'paiement' => $reservation->paiements->firstWhere('statut', StatutPaiement::Reussi),
            'confirmee' => (bool) $request->session()->get('confirmee'),
        ]);
    }

    /** Reçu imprimable (ou à enregistrer en PDF depuis le navigateur). */
    public function recu(Request $request, string $reference): View
    {
        $reservation = $this->sienne($request, $reference);
        abort_if(in_array($reservation->statut, [StatutReservation::EnAttentePaiement], true), 404);
        $reservation->load(['logement', 'creneau', 'services', 'client', 'paiements']);

        return view('reservation.recu', [
            'reservation' => $reservation,
            'paiement' => $reservation->paiements->firstWhere('statut', StatutPaiement::Reussi),
        ]);
    }

    /** La réservation du client connecté, sinon 404 (on ne révèle pas l'existence des autres). */
    private function sienne(Request $request, string $reference): Reservation
    {
        return Reservation::with('logement')
            ->where('reference', $reference)
            ->where('client_id', $request->user()->getKey())
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)   // saisie par un propriétaire : pas de billet en ligne
            ->firstOrFail();
    }

    /** @return array<int, int> identifiant du service => quantité */
    private function services(Request $request): array
    {
        return collect((array) $request->input('services', []))
            ->mapWithKeys(fn ($q, $id) => [(int) $id => min(20, max(0, (int) $q))])
            ->filter()
            ->all();
    }
}
