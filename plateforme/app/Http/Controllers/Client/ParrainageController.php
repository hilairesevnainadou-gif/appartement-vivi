<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Parrainage;
use App\Support\Parametres;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Parrainage et bonus » : code d'invitation à partager, amis invités, bonus disponible et historique. */
class ParrainageController extends Controller
{
    public function __invoke(Request $request, Parrainage $parrainage): View
    {
        $user = $request->user();
        $actif = $parrainage->actif();
        $code = $actif ? $parrainage->codeDe($user) : $user->code_parrainage;
        $lien = $code ? route('connexion', ['invitation' => $code]) : null;
        $bonusParrain = (int) Parametres::get('parrainage_bonus_parrain');
        $bonusFilleul = (int) Parametres::get('parrainage_bonus_filleul');

        $message = 'Je réserve mes appartements meublés sur VIVI, à l’heure ou à la journée. Inscris-toi avec mon code '.$code
            .($bonusFilleul > 0 ? ' et reçois '.\App\Support\Montant::fcfa($bonusFilleul).' de bonus' : '').' : '.$lien;

        return view('espace.parrainage', [
            'actif' => $actif,
            'code' => $code,
            'lien' => $lien,
            'partageWhatsapp' => 'https://wa.me/?text='.rawurlencode($message),
            'bonusParrain' => $bonusParrain,
            'bonusFilleul' => $bonusFilleul,
            'plafond' => (int) Parametres::get('bonus_plafond_pourcentage'),
            'disponible' => $parrainage->disponible($user),
            'prochaineExpiration' => $user->bonus()->utilisables()->orderBy('expire_le')->first(),
            'historique' => $user->bonus()->latest()->limit(30)->get(),
            'filleuls' => $user->filleuls()->latest()->limit(50)->get(['id', 'name', 'created_at', 'parrainage_recompense_le']),
        ]);
    }
}
