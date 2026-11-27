<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Http\Controllers\Controller;
use App\Models\FormuleAbonnement;
use App\Models\Paiement;
use App\Paiement\EchecPaiement;
use App\Paiement\Passerelle;
use App\Services\Abonnements;
use App\Services\ReservationImpossible;
use App\Support\Parametres;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Abonnement » : formule en cours, changement de formule ou renouvellement, historique des paiements. */
class AbonnementController extends Controller
{
    public function index(Request $request, Abonnements $abonnements): View
    {
        $proprietaire = $request->user();
        $formules = FormuleAbonnement::where('actif', true)->orderBy('ordre')->orderBy('prix_mensuel')->get();
        $actif = $proprietaire->abonnementActif()->with('formule')->first();

        $modele = app(\App\Services\ModeleEconomique::class);

        return view('pro.abonnement', [
            // Sur commission : pas de formule, page des conditions (quotas des Paramètres et taux retenu sur les retraits)
            'commission' => ($q = $modele->quotas($proprietaire)) ? $q + ['taux' => $modele->taux($proprietaire)] : null,
            'actif' => $actif,
            // Renouvellement déjà payé, qui commence après la période en cours
            'suivant' => $actif ? $proprietaire->abonnements()->with('formule')->where('statut', StatutAbonnement::Actif)
                ->whereKeyNot($actif->getKey())->whereDate('debut', '>', today())->first() : null,
            'formules' => $formules,
            'tarifs' => $formules->mapWithKeys(fn ($f) => [$f->id => $abonnements->tarif($proprietaire, $f)]),
            'promotion' => $abonnements->promotionEnCours(),
            'essai' => $abonnements->essaiPossible($proprietaire),
            'essaiFormule' => (int) Parametres::get('essai_formule_id'),
            'essaiJours' => (int) Parametres::get('essai_jours'),
            // Quotas séparés : logements et salles publiés ou en vérification
            'utilises' => ($quota = app(\App\Services\GestionLogements::class)->quota($proprietaire))['utilises'],
            'sallesUtilisees' => $quota['salles']['utilises'],
            'paiements' => Paiement::with('payable.formule')
                ->where('payable_type', 'abonnement')->where('payeur_id', $proprietaire->getKey())
                ->latest()->limit(12)->get(),
        ]);
    }

    /** Essai gratuit : la formule démarre tout de suite, sans paiement. */
    public function essai(Request $request, FormuleAbonnement $formule, Abonnements $abonnements): RedirectResponse
    {
        try {
            $abonnement = $abonnements->commencerEssai($request->user(), $formule);
        } catch (ReservationImpossible $e) {
            return back()->withErrors(['abonnement' => $e->getMessage()]);
        }

        return redirect()->route('pro.abonnement')->with('statut', 'Votre essai gratuit de la formule '.$formule->nom.' a commencé : il se termine le '.$abonnement->fin->translatedFormat('l j F').'. Vous pouvez publier vos logements dès maintenant.');
    }

    /** Choix d'une formule (ou renouvellement) : paiement par la passerelle, activation au paiement. */
    public function souscrire(Request $request, FormuleAbonnement $formule, Abonnements $abonnements, Passerelle $passerelle): RedirectResponse
    {
        try {
            $paiement = $abonnements->souscrire($request->user(), $formule, $passerelle->nom());
            $url = $passerelle->demarrer($paiement, route('paiement.retour', $paiement));
        } catch (ReservationImpossible|EchecPaiement $e) {
            return back()->withErrors(['abonnement' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }
}
