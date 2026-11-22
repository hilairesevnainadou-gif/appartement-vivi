<?php

namespace App\Http\Controllers;

use App\Services\Inscriptions;
use App\Services\SuppressionCompte;
use App\Support\Parametres;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** « Supprimer mon compte » : ce qui est perdu, code de confirmation, suppression. */
class SuppressionCompteController extends Controller
{
    public function __construct(private readonly SuppressionCompte $suppression) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('espace.suppression-compte', [
            'obstacles' => $this->suppression->obstacles($user),
            'pertes' => $this->suppression->pertes($user),
            'destination' => $this->suppression->destination($user),
            'enAttente' => $this->suppression->enAttente($user),
            'whatsapp' => Parametres::lienWhatsapp('Bonjour VIVI, je veux supprimer mon compte.'),
        ]);
    }

    public function code(Request $request, Inscriptions $inscriptions): RedirectResponse
    {
        $envoi = $this->suppression->envoyerCode($request->user(), $request->ip());

        return redirect()->route('profil.suppression')->with('code_dev', $inscriptions->codeAAfficher($envoi['code']));
    }

    public function annuler(Request $request): RedirectResponse
    {
        $this->suppression->abandonner($request->user());

        return redirect()->route('profil')->with('statut', 'Votre compte est conservé.');
    }

    public function confirmer(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string'], 'accord' => ['accepted']], [
            'code.required' => 'Saisissez le code reçu.', 'accord.accepted' => 'Cochez la case pour confirmer.',
        ]);
        $this->suppression->confirmer($request->user(), (string) $request->input('code'), $request->ip());

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('accueil')->with('statut', 'Votre compte est supprimé.');
    }
}
