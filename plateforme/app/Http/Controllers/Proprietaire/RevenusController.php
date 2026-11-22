<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Models\Logement;
use App\Models\Reservation;
use App\Services\Arrivees;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Réservations » (toutes, par onglet) et « Revenus » (solde, mouvements, retraits) de l'espace propriétaire. */
class RevenusController extends Controller
{
    private const ONGLETS = [
        'avenir' => [StatutReservation::Confirmee, StatutReservation::EnCours],
        'passees' => [StatutReservation::Terminee, StatutReservation::Consommee],
        'annulees' => [StatutReservation::Annulee],
    ];

    public function reservations(Request $request, Arrivees $arrivees): View
    {
        $ids = $arrivees->logementsDe($request->user());
        $onglet = isset(self::ONGLETS[$request->query('onglet')]) ? $request->query('onglet') : 'avenir';
        $logement = in_array((int) $request->query('logement'), $ids, true) ? (int) $request->query('logement') : null;

        $base = Reservation::query()->whereIn('logement_id', $logement ? [$logement] : $ids);
        $compteurs = collect(self::ONGLETS)->map(fn ($statuts) => (clone $base)->whereIn('statut', $statuts)->count());

        return view('pro.reservations', [
            'reservations' => (clone $base)->with(['client', 'logement', 'creneau', 'services'])
                ->whereIn('statut', self::ONGLETS[$onglet])
                ->orderBy('debut', $onglet === 'avenir' ? 'asc' : 'desc')
                ->paginate(20)->withQueryString(),
            'onglet' => $onglet,
            'compteurs' => $compteurs,
            'logements' => Logement::whereIn('id', $ids)->orderBy('titre')->get(['id', 'titre']),
            'logementChoisi' => $logement,
        ]);
    }

    public function revenus(Request $request): View
    {
        $p = $request->user();

        return view('pro.revenus', [
            'solde' => $p->solde(),
            'mouvements' => $p->mouvementsSolde()->with('reservation:id,reference')->latest('id')->paginate(15, ['*'], 'mouvements'),
            'retraits' => $p->retraits()->latest('id')->limit(20)->get(),
            'ceMois' => (int) $p->mouvementsSolde()->where('montant', '>', 0)->whereNotNull('reservation_id')->where('created_at', '>=', now()->startOfMonth())->sum('montant'),
        ]);
    }
}
