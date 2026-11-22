<?php

namespace App\Http\Controllers\Client;

use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Espace client : « Mes réservations », en trois onglets comme dans la maquette. */
class ReservationsController extends Controller
{
    /** Statuts rangés dans chaque onglet. « À venir » montre aussi un paiement à finaliser, tant que le créneau est retenu. */
    public const ONGLETS = [
        'avenir' => [StatutReservation::EnAttentePaiement, StatutReservation::Confirmee, StatutReservation::EnCours],
        'passees' => [StatutReservation::Terminee, StatutReservation::Consommee],
        'annulees' => [StatutReservation::Annulee],
    ];

    public function __invoke(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        // Propriétaire ou gestionnaire sans réservation personnelle : son espace, c'est le tableau de bord
        $user = $request->user();
        if ($user->estProprietaireOuGestionnaire()
            && ! $user->reservations()->where('origine', '!=', Reservation::ORIGINE_MANUELLE)->exists()) {
            return redirect()->route('espace.proprietaire');
        }

        $reservations = $request->user()->reservations()
            ->with(['logement.photos' => fn ($q) => $q->orderBy('ordre'), 'creneau', 'avis'])
            ->whereIn('statut', collect(self::ONGLETS)->flatten())
            ->where('origine', '!=', Reservation::ORIGINE_MANUELLE)   // saisies par un propriétaire : pas de billet en ligne
            ->get()
            // Paiement à finaliser : affiché seulement tant que le créneau est retenu
            ->reject(fn (Reservation $r) => $r->statut === StatutReservation::EnAttentePaiement && ! $r->bloque_jusqua?->isFuture());

        $groupes = collect(self::ONGLETS)->map(fn (array $statuts, string $onglet) => $reservations
            ->filter(fn (Reservation $r) => in_array($r->statut, $statuts, true))
            ->sortBy('debut', descending: $onglet !== 'avenir')
            ->values());

        $onglet = $request->query('onglet');

        return view('espace.client', [
            'groupes' => $groupes,
            'onglet' => isset(self::ONGLETS[$onglet]) ? $onglet : 'avenir',
        ]);
    }
}
