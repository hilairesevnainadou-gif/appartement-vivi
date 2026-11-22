<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FormuleAbonnement;
use App\Services\Abonnements;
use App\Support\Parametres;
use Illuminate\View\View;

/** Page publique « Propriétaires » : avantages et formules d'abonnement (paramétrées dans l'administration). */
class ProprietairesController extends Controller
{
    public function __invoke(Abonnements $abonnements): View
    {
        $utilisateur = auth()->user();
        $proprietaire = $utilisateur?->hasRole('proprietaire') ? $utilisateur : null;
        $formules = FormuleAbonnement::where('actif', true)->orderBy('ordre')->orderBy('prix_mensuel')->get();

        return view('pages.proprietaires', [
            'formules' => $formules,
            'tarifs' => $formules->mapWithKeys(fn ($f) => [$f->id => $abonnements->tarif($proprietaire, $f)]),
            'promotion' => $abonnements->promotionEnCours(),
            // Visiteur : l'essai est annoncé s'il est ouvert ; propriétaire : seulement s'il y a droit
            'essai' => $proprietaire ? $abonnements->essaiPossible($proprietaire) : ($utilisateur ? false : (bool) Parametres::get('essai_actif')),
            'essaiJours' => (int) Parametres::get('essai_jours'),
            'essaiFormule' => (int) Parametres::get('essai_formule_id'),
            'commission' => Parametres::commissionPourcentage(),
            'estProprietaire' => (bool) $utilisateur?->hasRole('proprietaire'),
            'abonnementActif' => $utilisateur?->abonnementActif,
            'whatsapp' => Parametres::lienWhatsapp('Bonjour VIVI, je suis propriétaire et j’ai une question sur les abonnements.'),
            'emailContact' => Parametres::get('email_contact'),
        ]);
    }
}
