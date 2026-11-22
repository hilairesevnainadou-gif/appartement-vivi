<?php

namespace App\Http\Controllers;

use App\Support\Parametres;
use Illuminate\View\View;

/** Pages d'aide publiques : conditions d'annulation et de report, conditions générales. */
class AideController extends Controller
{
    public function annulation(): View
    {
        return view('pages.aide.annulation', $this->reglages());
    }

    public function conditions(): View
    {
        return view('pages.aide.conditions', $this->reglages());
    }

    public function suppression(): View
    {
        return view('pages.aide.suppression', $this->reglages());
    }

    /** Tant qu'elle n'est pas publiée (Administration › Paramètres), seule l'équipe qui gère les paramètres la voit. */
    public function confidentialite(): View
    {
        $publiee = (bool) Parametres::get('confidentialite_publiee');
        abort_unless($publiee || auth()->user()?->can('parametres.gerer'), 404);

        return view('pages.aide.confidentialite', $this->reglages() + [
            'publiee' => $publiee,
            'admob' => Parametres::get('pub_active') && Parametres::get('admob_actif'),
            'joursJournal' => (int) config('activitylog.delete_records_older_than_days'),
        ]);
    }

    /** Les chiffres des pages suivent les paramètres de l'administration. */
    private function reglages(): array
    {
        return [
            'commission' => Parametres::commissionPourcentage(),
            'delaiReport' => Parametres::get('report_delai_minimum_heures'),
            'fenetreReport' => Parametres::get('report_fenetre_jours'),
            'blocage' => Parametres::get('blocage_creneau_minutes'),
            'whatsapp' => Parametres::lienWhatsapp(),
            'emailContact' => Parametres::get('email_contact'),
        ];
    }
}
