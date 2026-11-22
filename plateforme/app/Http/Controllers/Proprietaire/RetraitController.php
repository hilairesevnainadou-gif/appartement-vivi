<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Services\Caisse;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Demande de retrait du solde disponible. La caissière ou l'administrateur la valide ensuite. */
class RetraitController extends Controller
{
    public function store(Request $request, Caisse $caisse): RedirectResponse
    {
        $proprietaire = $request->user();
        $solde = $proprietaire->solde();

        if ($solde <= 0) {
            return back()->withErrors(['retrait' => 'Votre solde est vide : aucun retrait possible pour le moment.']);
        }

        // Montant choisi (page Revenus), sinon tout le solde (tableau de bord)
        $montant = (int) ($request->validate(['montant' => ['nullable', 'integer', 'min:1000', 'max:'.$solde]], [
            'montant.max' => 'Vous ne pouvez pas retirer plus que votre solde.',
            'montant.min' => 'Le retrait minimum est de 1 000 FCFA.',
        ])['montant'] ?? $solde);

        try {
            $retrait = $caisse->demanderRetrait($proprietaire, $montant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['retrait' => $e->getMessage()]);
        }

        return back()->with('statut', 'Demande de retrait '.$retrait->reference.' de '.Montant::fcfa($retrait->montant)
            .' envoyée. La caissière VIVI la validera et indiquera le mode de règlement.');
    }
}
