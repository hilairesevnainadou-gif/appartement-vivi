<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ApresVente;
use App\Services\ReservationImpossible;
use App\Support\Montant;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Report, annulation et avis, depuis le billet du client (maquettes reporter.html, annuler.html, ma-reservation.html). */
class ApresVenteController extends Controller
{
    public function __construct(private readonly ApresVente $apresVente) {}

    public function formulaireReport(Request $request, string $reference): View
    {
        $r = $this->sienne($request, $reference);
        $jours = $this->apresVente->joursDeReport($r);
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date')), null, false);
        $choisi = $date ? collect($jours)->first(fn ($j) => $j['date']->isSameDay($date) && $j['options'] !== []) : null;

        return view('reservation.reporter', [
            'reservation' => $r,
            'possible' => $r->reportPossible() && collect($jours)->contains(fn ($j) => $j['options'] !== []),
            'jours' => $jours,
            'choisi' => $choisi,
            'limite' => $this->apresVente->limiteReport($r),
            'delai' => (int) Parametres::get('report_delai_minimum_heures'),
        ]);
    }

    public function reporter(Request $request, string $reference): RedirectResponse
    {
        $r = $this->sienne($request, $reference);
        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'option' => ['required', 'string', 'max:10'],
            'accord' => ['accepted'],
        ], ['accord.accepted' => 'Cochez cette case pour confirmer le report.', 'option.required' => 'Choisissez un créneau.']);

        try {
            $r = $this->apresVente->reporter($r, $donnees['date'], $donnees['option']);
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return redirect()->route('reservation.voir', $r->reference)
            ->with('statut', 'Réservation reportée au '.$r->debut->translatedFormat('l j F à H:i').'. Voici votre nouveau QR Code : l’ancien n’est plus valable.');
    }

    public function formulaireAnnulation(Request $request, string $reference): View|RedirectResponse
    {
        $r = $this->sienne($request, $reference);
        if (! $this->apresVente->annulable($r)) {
            return redirect()->route('reservation.voir', $reference)->withErrors(['annulation' => 'Cette réservation ne peut plus être annulée.']);
        }

        return view('reservation.annuler', [
            'reservation' => $r,
            'calcul' => $this->apresVente->remboursement($r),
            'limite' => $this->apresVente->limiteReport($r),
            'moyen' => $r->paiements()->where('statut', 'reussi')->value('moyen'),
        ]);
    }

    public function annuler(Request $request, string $reference): RedirectResponse
    {
        $r = $this->sienne($request, $reference);
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:120']])['motif'] ?? null;

        try {
            $r = $this->apresVente->annuler($r, $motif);
        } catch (ReservationImpossible $e) {
            return redirect()->route('reservation.voir', $reference)->withErrors(['annulation' => $e->getMessage()]);
        }

        return redirect()->route('reservation.voir', $r->reference)->with('statut', $r->montant_rembourse > 0
            ? 'Réservation annulée. Vous serez remboursé de '.Montant::fcfa($r->montant_rembourse).' sous 24 h à 7 jours.'
            : 'Réservation annulée. Aucun remboursement n’est dû pour cette annulation.');
    }

    public function avis(Request $request, string $reference): RedirectResponse
    {
        $r = $this->sienne($request, $reference);
        $donnees = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], ['note.required' => 'Choisissez une note de 1 à 5 étoiles.']);

        try {
            $this->apresVente->laisserAvis($r, (int) $donnees['note'], $donnees['commentaire'] ?? null);
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return redirect()->to(route('reservation.voir', $reference).'#avis')
            ->with('statut', 'Merci, votre avis est enregistré. Il sera publié après vérification par VIVI.');
    }

    private function sienne(Request $request, string $reference): Reservation
    {
        return Reservation::with(['logement', 'creneau', 'avis'])
            ->where('reference', $reference)->where('client_id', $request->user()->getKey())
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)->firstOrFail();
    }
}
