<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\Formule;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Models\Blocage;
use App\Models\Logement;
use App\Models\Reservation;
use App\Services\Arrivees;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Calendrier d'un logement : un mois, et le détail de la journée choisie (réservations, blocage).
 * Le propriétaire (et le gestionnaire, sur les logements confiés) bloque ou débloque des journées et
 * déplace une réservation consommée ou à moins de 3 h de l'arrivée.
 */
class CalendrierController extends Controller
{
    /** Statuts affichés dans le calendrier. */
    private const VISIBLES = [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];

    public function __construct(private readonly Arrivees $arrivees) {}

    public function index(Request $request): View
    {
        // Logements publiés d'abord : ce sont ceux qui ont des réservations
        $logements = Logement::with(['tarifs', 'creneaux'])->whereIn('id', $this->arrivees->logementsDe($request->user()))->get()
            ->sortBy(fn (Logement $l) => [$l->statut === StatutLogement::Publie ? 0 : 1, $l->titre])->values();
        $logement = $logements->firstWhere('id', (int) $request->query('logement')) ?? $logements->first();

        $aujourdhui = CarbonImmutable::today();
        $jour = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('jour')), null, false) ?? $aujourdhui;
        $mois = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('mois')), null, false) ?? $jour->startOfMonth();
        if ($mois->lt($aujourdhui->startOfMonth())) {
            $mois = $aujourdhui->startOfMonth();
        }

        $jours = collect();
        $duJour = collect();
        $blocage = null;
        if ($logement) {
            $debut = $mois->startOfMonth();
            $fin = $mois->endOfMonth();
            $reservations = Reservation::with(['client', 'creneau', 'services'])
                ->where('logement_id', $logement->id)->whereIn('statut', self::VISIBLES)
                ->where('debut', '<', $fin->addDay())->where('fin', '>', $debut->subDay())
                ->orderBy('debut')->get();
            $blocages = $logement->blocages()->whereDate('date_fin', '>=', $debut)->whereDate('date_debut', '<=', $fin)->get();

            foreach (CarbonPeriod::create($debut, $fin) as $d) {
                $d = CarbonImmutable::instance($d);
                $jours->push(['date' => $d, 'etat' => $this->etat($d, $reservations, $blocages, (int) $logement->unites), 'passe' => $d->lt($aujourdhui)]);
            }

            $duJour = Reservation::with(['client', 'creneau', 'services'])
                ->where('logement_id', $logement->id)->whereIn('statut', self::VISIBLES)
                ->where('debut', '<', $jour->addDay())->where('fin', '>', $jour)
                ->orderBy('debut')->get();
            $blocage = $logement->blocages()->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour)->first();
        }

        return view('pro.calendrier', compact('logements', 'logement', 'mois', 'jour', 'jours', 'duJour', 'blocage', 'aujourdhui'));
    }

    public function bloquer(Request $request, Logement $logement): RedirectResponse
    {
        $this->accessible($request, $logement);
        $donnees = $request->validate([
            'jour' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);
        $jour = CarbonImmutable::createFromFormat('!Y-m-d', $donnees['jour']);

        $occupee = Reservation::where('logement_id', $logement->id)->occupantes()
            ->where('debut', '<', $jour->addDay())->where('fin', '>', $jour)->exists();
        if ($occupee) {
            return back()->withErrors(['calendrier' => 'Cette journée a déjà des réservations : elle ne peut pas être bloquée.']);
        }
        $logement->blocages()->create([
            'date_debut' => $jour, 'date_fin' => $jour,
            'motif' => $donnees['motif'] ?: 'Bloqué par le propriétaire', 'cree_par' => $request->user()->getKey(),
        ]);

        return back()->with('statut', 'Journée bloquée : elle n’est plus proposée aux clients.');
    }

    public function debloquer(Request $request, Logement $logement, Blocage $blocage): RedirectResponse
    {
        $this->accessible($request, $logement);
        abort_unless($blocage->logement_id === $logement->id, 404);
        $blocage->delete();

        return back()->with('statut', 'Journée débloquée : elle est de nouveau réservable.');
    }

    /** Jours complets (grisés) et heures du jour choisi, prises comprises (barrées), pour le formulaire « Déplacer ». */
    public function optionsDeplacement(Request $request, Reservation $reservation, Reservations $reservations): JsonResponse
    {
        $this->accessible($request, $reservation->logement);

        return response()->json(['data' => $reservations->optionsDeplacement($reservation, $request->query('date'))]);
    }

    public function deplacer(Request $request, Reservation $reservation, Reservations $reservations): RedirectResponse
    {
        $this->accessible($request, $reservation->logement);
        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'heure' => ['required', 'date_format:H:i'],
        ]);
        $nouveauDebut = CarbonImmutable::createFromFormat('!Y-m-d H:i', $donnees['date'].' '.$donnees['heure']);

        try {
            $r = $reservations->deplacer($reservation, $nouveauDebut, $request->user());
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['deplacement_'.$reservation->id => $e->getMessage()])->withInput();
        }

        return redirect()->route('pro.calendrier', ['logement' => $r->logement_id, 'jour' => $r->debut->toDateString()])
            ->with('statut', 'Réservation '.$r->reference.' déplacée au '.$r->debut->translatedFormat('l j F à H:i').'. Le client est prévenu.');
    }

    /**
     * Réservation saisie par le propriétaire ou le gestionnaire pour un client venu hors de la plateforme.
     */
    public function reserver(Request $request, Logement $logement, Reservations $reservations): RedirectResponse
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
                if ($v && ! \App\Support\Telephone::normaliser((string) $v)) {
                    $echec('Numéro béninois à 10 chiffres, par exemple 01 97 00 00 00.');
                }
            }],
            'montant' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'encaisse' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['nom.required' => 'Indiquez le nom du client.']);

        try {
            $r = $reservations->ajouterManuelle($request->user(), $logement, $d);
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['manuelle' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pro.calendrier', ['logement' => $logement->id, 'jour' => $r->debut->toDateString()])
            ->with('statut', 'Réservation '.$r->reference.' ajoutée au calendrier pour '.$r->client->name.' : la période n’est plus proposée en ligne.');
    }

    /** Paiement reçu sur place (acompte ou solde) pour une réservation hors plateforme. */
    public function encaisser(Request $request, Reservation $reservation, Reservations $reservations): RedirectResponse
    {
        $this->accessible($request, $reservation->logement);
        $montant = (int) $request->validate(['montant' => ['required', 'integer', 'min:1', 'max:10000000']])['montant'];
        try {
            $reservations->encaisser($reservation, $montant);
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['calendrier' => $e->getMessage()]);
        }
        $reste = $reservation->fresh()->resteAPayer();

        return back()->with('statut', \App\Support\Montant::fcfa($montant).' encaissés pour '.$reservation->reference.'.'.($reste > 0 ? ' Reste à payer : '.\App\Support\Montant::fcfa($reste).'.' : ' Séjour entièrement payé.'));
    }

    public function retirer(Request $request, Reservation $reservation, Reservations $reservations): RedirectResponse
    {
        $this->accessible($request, $reservation->logement);
        try {
            $reservations->annulerManuelle($reservation, $request->user());
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['calendrier' => $e->getMessage()]);
        }

        return back()->with('statut', 'Réservation '.$reservation->reference.' retirée : la période est de nouveau réservable.');
    }

    /** libre, partiel, complet ou bloque. */
    private function etat(CarbonImmutable $jour, Collection $reservations, Collection $blocages, int $unites = 1): string
    {
        if ($blocages->contains(fn ($b) => $jour->betweenIncluded($b->date_debut->startOfDay(), $b->date_fin->startOfDay()))) {
            return 'bloque';
        }
        $ceJour = $reservations->filter(fn ($r) => $r->debut->lt($jour->addDay()) && $r->fin->gt($jour));
        if ($ceJour->isEmpty()) {
            return 'libre';
        }
        // Complet quand toutes les unités sont prises à la journée
        $journees = $ceJour->filter(fn ($r) => $r->formule === Formule::Journee)->count();

        return $journees >= max(1, $unites) ? 'complet' : 'partiel';
    }

    private function accessible(Request $request, Logement $logement): void
    {
        abort_unless(in_array($logement->id, $this->arrivees->logementsDe($request->user()), true), 404);
    }
}
