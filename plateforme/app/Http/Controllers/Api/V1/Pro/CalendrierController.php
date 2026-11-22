<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\Formule;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Http\Resources\Api\ReservationProResource;
use App\Models\Blocage;
use App\Models\Logement;
use App\Models\Reservation;
use App\Services\Arrivees;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use App\Support\Telephone;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Réservations et calendrier des logements gérés (propriétaire et gestionnaire) : liste par onglet, mois,
 * détail d'un jour, blocages, déplacement, réservations hors plateforme et encaissement sur place.
 */
class CalendrierController extends Controller
{
    private const ONGLETS = [
        'avenir' => [StatutReservation::Confirmee, StatutReservation::EnCours],
        'passees' => [StatutReservation::Terminee, StatutReservation::Consommee],
        'annulees' => [StatutReservation::Annulee],
    ];

    private const VISIBLES = [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];

    public function __construct(private readonly Arrivees $arrivees, private readonly Reservations $reservations) {}

    /** Logements gérés (pour les sélecteurs) : propres ou confiés. */
    public function logements(Request $request): JsonResponse
    {
        $logements = Logement::with(['tarifs', 'creneaux', 'photos'])->whereIn('id', $this->arrivees->logementsDe($request->user()))->get()
            ->sortBy(fn (Logement $l) => [$l->statut === StatutLogement::Publie ? 0 : 1, $l->titre])->values();

        return response()->json(['data' => $logements->map(fn (Logement $l) => [
            'id' => $l->id, 'titre' => $l->titre, 'statut' => Format::enum($l->statut), 'unites' => (int) $l->unites,
            'est_salle' => $l->estSalle(), 'capacite' => (int) $l->capacite,
            'photo' => $l->urlPhoto(),
            'formules' => collect($l->formulesProposees())->map(fn ($f) => Format::enum($f))->values(),
            'creneaux' => $l->creneaux->sortBy('ordre')->values()->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom,
                'heure_debut' => substr((string) $c->heure_debut, 0, 5), 'heure_fin' => substr((string) $c->heure_fin, 0, 5)]),
        ])]);
    }

    /** GET /pro/reservations?onglet=avenir|passees|annulees&logement={id}&page= */
    public function reservations(Request $request): JsonResponse
    {
        $ids = $this->arrivees->logementsDe($request->user());
        $onglet = isset(self::ONGLETS[$request->query('onglet')]) ? $request->query('onglet') : 'avenir';
        $logement = in_array((int) $request->query('logement'), $ids, true) ? (int) $request->query('logement') : null;
        $base = Reservation::query()->whereIn('logement_id', $logement ? [$logement] : $ids);

        $page = (clone $base)->with(['client', 'logement', 'creneau', 'services'])
            ->whereIn('statut', self::ONGLETS[$onglet])
            ->orderBy('debut', $onglet === 'avenir' ? 'asc' : 'desc')
            ->paginate(20);

        return response()->json([
            'data' => ReservationProResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'onglet' => $onglet,
                'compteurs' => collect(self::ONGLETS)->map(fn ($statuts) => (clone $base)->whereIn('statut', $statuts)->count()),
                'page' => $page->currentPage(), 'derniere_page' => $page->lastPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function reservation(Request $request, Reservation $reservation): ReservationProResource
    {
        $this->accessible($request, $reservation->logement);

        return new ReservationProResource($reservation->load(['client', 'logement', 'creneau', 'services']));
    }

    /**
     * GET /pro/logements/{logement}/calendrier?mois=AAAA-MM&jour=AAAA-MM-JJ
     * État de chaque jour du mois (libre, partiel, complet, bloque) et détail du jour choisi.
     */
    public function calendrier(Request $request, Logement $logement): JsonResponse
    {
        $this->accessible($request, $logement);
        $aujourdhui = CarbonImmutable::today();
        $jour = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('jour')), null, false) ?? $aujourdhui;
        $mois = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('mois')), null, false) ?? $jour->startOfMonth();
        $debut = $mois->startOfMonth();
        $fin = $mois->endOfMonth();

        $reservations = Reservation::where('logement_id', $logement->id)->whereIn('statut', self::VISIBLES)
            ->where('debut', '<', $fin->addDay())->where('fin', '>', $debut->subDay())->get();
        $blocages = $logement->blocages()->whereDate('date_fin', '>=', $debut)->whereDate('date_debut', '<=', $fin)->get();

        $jours = collect(CarbonPeriod::create($debut, $fin))->map(fn ($d) => CarbonImmutable::instance($d))->map(fn (CarbonImmutable $d) => [
            'date' => Format::jour($d),
            'etat' => $this->etat($d, $reservations, $blocages, (int) $logement->unites),
            'passe' => $d->lt($aujourdhui),
        ])->values();

        $duJour = Reservation::with(['client', 'logement', 'creneau', 'services'])
            ->where('logement_id', $logement->id)->whereIn('statut', self::VISIBLES)
            ->where('debut', '<', $jour->addDay())->where('fin', '>', $jour)
            ->orderBy('debut')->get();
        $blocage = $logement->blocages()->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour)->first();

        return response()->json(['data' => [
            'logement' => ['id' => $logement->id, 'titre' => $logement->titre, 'unites' => (int) $logement->unites],
            'mois' => $mois->format('Y-m'),
            'jours' => $jours,
            'jour' => [
                'date' => Format::jour($jour),
                'blocage' => $blocage ? ['id' => $blocage->id, 'motif' => $blocage->motif] : null,
                'reservations' => ReservationProResource::collection($duJour)->resolve($request),
            ],
        ]]);
    }

    /** POST /pro/logements/{logement}/blocages {jour, motif} : la journée n'est plus proposée aux clients. */
    public function bloquer(Request $request, Logement $logement): JsonResponse
    {
        $this->accessible($request, $logement);
        $donnees = $request->validate([
            'jour' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);
        $jour = CarbonImmutable::createFromFormat('!Y-m-d', $donnees['jour']);

        if (Reservation::where('logement_id', $logement->id)->occupantes()->where('debut', '<', $jour->addDay())->where('fin', '>', $jour)->exists()) {
            throw ValidationException::withMessages(['jour' => 'Cette journée a déjà des réservations : elle ne peut pas être bloquée.']);
        }
        $blocage = $logement->blocages()->create([
            'date_debut' => $jour, 'date_fin' => $jour,
            'motif' => ($donnees['motif'] ?? null) ?: 'Bloqué par le propriétaire', 'cree_par' => $request->user()->getKey(),
        ]);

        return response()->json(['message' => 'Journée bloquée : elle n’est plus proposée aux clients.', 'data' => ['id' => $blocage->id]], 201);
    }

    public function debloquer(Request $request, Logement $logement, Blocage $blocage): JsonResponse
    {
        $this->accessible($request, $logement);
        abort_unless($blocage->logement_id === $logement->id, 404);
        $blocage->delete();

        return response()->json(['message' => 'Journée débloquée : elle est de nouveau réservable.']);
    }

    /** POST /pro/reservations/{reference}/deplacer {date, heure} : réservation consommée ou à moins de 3 h. */
    public function deplacer(Request $request, Reservation $reservation): ReservationProResource
    {
        $this->accessible($request, $reservation->logement);
        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'heure' => ['required', 'date_format:H:i'],
        ]);

        try {
            $r = $this->reservations->deplacer($reservation, CarbonImmutable::createFromFormat('!Y-m-d H:i', $donnees['date'].' '.$donnees['heure']), $request->user());
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['date' => $e->getMessage()]);
        }

        return $this->fiche($r)->additional(['message' => 'Réservation '.$r->reference.' déplacée au '.$r->debut->translatedFormat('l j F à H:i').'. Le client est prévenu.']);
    }

    /** POST /pro/logements/{logement}/reservations : client venu hors de la plateforme (ni paiement en ligne ni commission). */
    public function ajouter(Request $request, Logement $logement): ReservationProResource
    {
        $this->accessible($request, $logement);
        $d = $request->validate([
            'formule' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'heure' => ['nullable', 'date_format:H:i'],
            'duree' => ['nullable', 'integer', 'min:1', 'max:24'],
            'creneau_id' => ['nullable', 'integer'],
            'jours' => ['nullable', 'integer', 'min:1', 'max:30'],
            'voyageurs' => $logement->regleVoyageurs(),   // sans objet pour une salle : elle se réserve entière
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:20', function ($a, $v, $echec) {
                if ($v && ! Telephone::normaliser((string) $v)) {
                    $echec('Numéro béninois à 10 chiffres, par exemple 01 97 00 00 00.');
                }
            }],
            'montant' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'encaisse' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['nom.required' => 'Indiquez le nom du client.']);

        try {
            $r = $this->reservations->ajouterManuelle($request->user(), $logement, $d);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['reservation' => $e->getMessage()]);
        }

        return $this->fiche($r)->additional(['message' => 'Réservation '.$r->reference.' ajoutée au calendrier : la période n’est plus proposée en ligne.']);
    }

    /** POST /pro/reservations/{reference}/encaisser {montant} : acompte ou solde reçu sur place (hors plateforme). */
    public function encaisser(Request $request, Reservation $reservation): ReservationProResource
    {
        $this->accessible($request, $reservation->logement);
        $montant = (int) $request->validate(['montant' => ['required', 'integer', 'min:1', 'max:10000000']])['montant'];
        try {
            $this->reservations->encaisser($reservation, $montant);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        return $this->fiche($reservation);
    }

    /** POST /pro/reservations/{reference}/retirer : annule une réservation hors plateforme. */
    public function retirer(Request $request, Reservation $reservation): ReservationProResource
    {
        $this->accessible($request, $reservation->logement);
        try {
            $this->reservations->annulerManuelle($reservation, $request->user());
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['reservation' => $e->getMessage()]);
        }

        return $this->fiche($reservation)->additional(['message' => 'Réservation retirée : la période est de nouveau réservable.']);
    }

    private function fiche(Reservation $r): ReservationProResource
    {
        return new ReservationProResource($r->fresh(['client', 'logement', 'creneau', 'services']));
    }

    /** libre, partiel, complet ou bloque (même règle que le calendrier du site). */
    private function etat(CarbonImmutable $jour, Collection $reservations, Collection $blocages, int $unites = 1): string
    {
        if ($blocages->contains(fn ($b) => $jour->betweenIncluded($b->date_debut->startOfDay(), $b->date_fin->startOfDay()))) {
            return 'bloque';
        }
        $ceJour = $reservations->filter(fn ($r) => $r->debut->lt($jour->addDay()) && $r->fin->gt($jour));
        if ($ceJour->isEmpty()) {
            return 'libre';
        }

        return $ceJour->filter(fn ($r) => $r->formule === Formule::Journee)->count() >= max(1, $unites) ? 'complet' : 'partiel';
    }

    private function accessible(Request $request, ?Logement $logement): void
    {
        abort_unless($logement && in_array($logement->id, $this->arrivees->logementsDe($request->user()), true), 404);
    }
}
