<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\StatutReservation;
use App\Http\Controllers\Api\V1\PaiementsController;
use App\Http\Controllers\Client\ReservationsController as OngletsWeb;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Logement;
use App\Models\Reservation;
use App\Paiement\EchecPaiement;
use App\Paiement\Passerelle;
use App\Services\ApresVente;
use App\Services\CoordonneesClient;
use App\Services\Parrainage;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Réservations du client connecté : liste par onglet, tunnel (créneau retenu → services → paiement),
 * billet avec QR Code, report, annulation et avis. Mêmes règles que le site (App\Services\Reservations, ApresVente).
 */
class ReservationsController extends Controller
{
    public function __construct(private readonly Reservations $reservations, private readonly ApresVente $apresVente) {}

    /** GET /reservations?onglet=avenir|passees|annulees */
    public function index(Request $request): AnonymousResourceCollection
    {
        $onglet = isset(OngletsWeb::ONGLETS[$request->query('onglet')]) ? $request->query('onglet') : 'avenir';

        $reservations = $request->user()->reservations()
            ->with(['logement.photos' => fn ($q) => $q->orderBy('ordre'), 'creneau', 'avis'])
            ->whereIn('statut', OngletsWeb::ONGLETS[$onglet])
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)
            ->orderBy('debut', $onglet === 'avenir' ? 'asc' : 'desc')
            ->get()
            // Paiement à finaliser : montré seulement tant que le créneau est retenu
            ->reject(fn (Reservation $r) => $r->statut === StatutReservation::EnAttentePaiement && ! $r->bloque_jusqua?->isFuture())
            ->values();

        return ReservationResource::collection($reservations)->additional(['onglet' => $onglet]);
    }

    /**
     * POST /logements/{slug}/reserver : le créneau est retenu pour le client pendant le paiement
     * (durée « blocage_creneau_minutes »). Mêmes champs que GET /logements/{slug}/disponibilites.
     */
    public function bloquer(Request $request, string $slug): JsonResponse
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
        // Code de l'agent marketing lu dans le lien d'affiliation ouvert par le client (…/logements/{slug}?agent=AG…)
        $code = $request->validate(['agent' => ['nullable', 'string', 'max:12']])['agent'] ?? null;
        $agent = app(\App\Services\AgentsMarketing::class)->agentPour($logement, $code, $request->user());

        try {
            $reservation = $this->reservations->bloquer($request->user(), $logement, $demande, $agent);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['reservation' => $e->getMessage()]);
        }

        return $this->tunnel($request, $reservation)->setStatusCode(201);
    }

    /** GET /reservations/{reference} : billet (QR Code et code de secours si la réservation est active). */
    public function show(Request $request, string $reference): JsonResponse|ReservationResource
    {
        $reservation = $this->sienne($request, $reference);
        if ($reservation->statut === StatutReservation::EnAttentePaiement) {
            return $this->tunnel($request, $reservation);
        }
        $reservation->load(['logement.photos', 'creneau', 'services', 'avis', 'paiements' => fn ($q) => $q->latest()]);

        return new ReservationResource($reservation);
    }

    /** POST /reservations/{reference}/devis {services: {id: quantite}, bonus: bool} : prix recalculé par le serveur. */
    public function devis(Request $request, string $reference): JsonResponse
    {
        $reservation = $this->sienne($request, $reference);

        return response()->json(['data' => $this->reservations->devisAvecBonus($reservation, $this->services($request), $request->boolean('bonus'))->enTableau()]);
    }

    /**
     * POST /reservations/{reference}/payer : coordonnées enregistrées, montants figés, paiement ouvert chez FedaPay.
     * Réponse : url_paiement à ouvrir, et l'identifiant du paiement à suivre (GET /paiements/{id}).
     */
    public function payer(Request $request, string $reference, Passerelle $passerelle): JsonResponse
    {
        $reservation = $this->sienne($request, $reference);
        // { nom, whatsapp (tout pays, avec indicatif), email? } ; « telephone » reste accepté (anciennes versions)
        $donnees = $request->validate(CoordonneesClient::regles() + [
            'cgv' => ['accepted'],
            'services' => ['array'],
            'services.*' => ['integer', 'min:0', 'max:20'],
            'utiliser_bonus' => ['boolean'],
        ], CoordonneesClient::messages() + ['cgv.accepted' => 'Acceptez les conditions pour continuer.']);
        app(CoordonneesClient::class)->enregistrer($reservation, $request->user(), $donnees);

        try {
            $paiement = $this->reservations->preparerPaiement($reservation, $this->services($request), $passerelle->nom(), $request->boolean('utiliser_bonus'));
            $url = $passerelle->demarrer($paiement, route('paiement.retour-app', $paiement));
        } catch (ReservationImpossible|EchecPaiement $e) {
            throw ValidationException::withMessages(['paiement' => $e->getMessage()]);
        }

        return response()->json(['data' => ['url_paiement' => $url, 'paiement' => PaiementsController::etat($paiement->refresh())]]);
    }

    /** GET /reservations/{reference}/report : jours possibles (fenêtre de report) et leurs créneaux libres. */
    public function optionsReport(Request $request, string $reference): JsonResponse
    {
        $r = $this->sienne($request, $reference);
        $jours = $this->apresVente->joursDeReport($r);

        return response()->json(['data' => [
            'possible' => $r->reportPossible() && collect($jours)->contains(fn ($j) => $j['options'] !== []),
            'limite' => Format::jour($this->apresVente->limiteReport($r)),
            'delai_minimum_heures' => (int) Parametres::get('report_delai_minimum_heures'),
            'non_remboursable_apres' => true,   // une réservation reportée n'est plus remboursable
            'jours' => collect($jours)->map(fn ($j) => [
                'date' => Format::jour($j['date']),
                'options' => collect($j['options'])->map(fn ($o) => ['valeur' => $o['valeur'], 'libelle' => $o['libelle'], 'debut' => Format::date($o['debut'])])->values(),
                // Toutes les arrivées du jour : celles qui sont prises (« libre » à false) s'affichent barrées
                'creneaux' => collect($j['creneaux'])->map(fn ($o) => ['valeur' => $o['valeur'], 'libelle' => $o['libelle'], 'debut' => Format::date($o['debut']), 'libre' => $o['libre']])->values(),
            ])->values(),
        ]]);
    }

    /** POST /reservations/{reference}/report {date, option, accord} : nouveau QR Code, l'ancien n'est plus valable. */
    public function reporter(Request $request, string $reference): ReservationResource
    {
        $r = $this->sienne($request, $reference);
        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'option' => ['required', 'string', 'max:10'],
            'accord' => ['accepted'],
        ], ['accord.accepted' => 'Confirmez le report.', 'option.required' => 'Choisissez un créneau.']);

        try {
            $r = $this->apresVente->reporter($r, $donnees['date'], $donnees['option']);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['report' => $e->getMessage()]);
        }

        return $this->billet($r)->additional(['message' => 'Réservation reportée au '.$r->debut->translatedFormat('l j F à H:i').'. Voici votre nouveau QR Code : l’ancien n’est plus valable.']);
    }

    /** GET /reservations/{reference}/annulation : ce qui serait remboursé si le client annule maintenant. */
    public function simulationAnnulation(Request $request, string $reference): JsonResponse
    {
        $r = $this->sienne($request, $reference);
        if (! $this->apresVente->annulable($r)) {
            throw ValidationException::withMessages(['annulation' => 'Cette réservation ne peut plus être annulée.']);
        }

        return response()->json(['data' => $this->apresVente->remboursement($r) + [
            'moyen' => $r->paiements()->where('statut', 'reussi')->value('moyen'),
        ]]);
    }

    /** POST /reservations/{reference}/annuler {motif} */
    public function annuler(Request $request, string $reference): ReservationResource
    {
        $r = $this->sienne($request, $reference);
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:120']])['motif'] ?? null;

        try {
            $r = $this->apresVente->annuler($r, $motif);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['annulation' => $e->getMessage()]);
        }

        return $this->billet($r)->additional(['message' => $r->montant_rembourse > 0
            ? 'Réservation annulée. Vous serez remboursé de '.\App\Support\Montant::fcfa($r->montant_rembourse).' sous 24 h à 7 jours.'
            : 'Réservation annulée. Aucun remboursement n’est dû pour cette annulation.']);
    }

    /** POST /reservations/{reference}/avis {note: 1..5, commentaire} : publié après vérification par VIVI. */
    public function avis(Request $request, string $reference): JsonResponse
    {
        $r = $this->sienne($request, $reference);
        $donnees = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], ['note.required' => 'Choisissez une note de 1 à 5 étoiles.']);

        try {
            $avis = $this->apresVente->laisserAvis($r, (int) $donnees['note'], $donnees['commentaire'] ?? null);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['note' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Merci, votre avis est enregistré. Il sera publié après vérification par VIVI.',
            'data' => ['note' => (int) $avis->note, 'commentaire' => $avis->commentaire, 'statut' => Format::enum($avis->statut)],
        ], 201);
    }

    /* ---------- Outils ---------- */

    /** Réservation en attente de paiement, avec ce qu'il faut pour les étapes « Services » et « Paiement ». */
    private function tunnel(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation->load(['logement.photos', 'logement.services' => fn ($q) => $q->where('actif', true)->orderBy('ordre'), 'creneau', 'services']);
        $choisis = $reservation->services->pluck('quantite', 'service_id')->all();
        $bonusDisponible = app(Parrainage::class)->disponible($request->user()) + (int) $reservation->montant_bonus;

        return response()->json(['data' => (new ReservationResource($reservation))->resolve($request) + [
            'services_proposes' => $reservation->logement->services->map(fn ($s) => [
                'id' => $s->id, 'nom' => $s->nom, 'description' => $s->description, 'prix' => (int) $s->prix,
                'unite' => $s->unite, 'icone' => $s->icone, 'quantite' => (int) ($choisis[$s->id] ?? 0),
            ])->values(),
            'devis' => $this->reservations->devisAvecBonus($reservation, $choisis, $bonusDisponible > 0)->enTableau(),
            'bonus' => ['disponible' => $bonusDisponible, 'plafond_pourcentage' => (int) Parametres::get('bonus_plafond_pourcentage')],
            'coordonnees' => ['nom' => $request->user()->name, 'telephone' => $request->user()->telephone, 'email' => $request->user()->email],
        ]]);
    }

    private function billet(Reservation $r): ReservationResource
    {
        return new ReservationResource($r->fresh(['logement.photos', 'creneau', 'services', 'avis', 'paiements']));
    }

    /** La réservation du client connecté, sinon 404 (on ne révèle pas l'existence des autres). */
    private function sienne(Request $request, string $reference): Reservation
    {
        return Reservation::with(['logement', 'creneau', 'avis'])
            ->where('reference', $reference)
            ->where('client_id', $request->user()->getKey())
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)
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
