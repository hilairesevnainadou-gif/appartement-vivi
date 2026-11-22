<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Services\SuiviPaiements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paiements lancés depuis l'application. L'application ouvre « url_paiement » (navigateur ou WebView) ;
 * à la fin, FedaPay renvoie vers /paiements/{id}/retour-app, qui ouvre l'application par son lien profond.
 * L'application demande alors GET /api/v1/paiements/{id} pour connaître le résultat.
 */
class PaiementsController extends Controller
{
    public function __construct(private readonly SuiviPaiements $suivi) {}

    public function show(Request $request, Paiement $paiement): JsonResponse
    {
        abort_unless((int) $paiement->payeur_id === (int) $request->user()->getKey(), 404);

        return response()->json(['data' => $this->etat($this->suivi->actualiser($paiement))]);
    }

    /** Retour de la page de paiement (route web, sans connexion) : statut relu, puis ouverture de l'application. */
    public function retourApp(Paiement $paiement): RedirectResponse
    {
        $paiement = $this->suivi->actualiser($paiement);
        $statut = match ($paiement->statut) {
            StatutPaiement::Reussi => 'reussi',
            StatutPaiement::Echoue => 'echoue',
            default => 'en_attente',
        };

        return redirect()->away(rtrim((string) config('vivi.api.lien_retour_app'), '/').'/'.$paiement->getKey().'?statut='.$statut);
    }

    public static function etat(Paiement $paiement): array
    {
        $objet = $paiement->loadMissing('payable')->payable;

        return [
            'id' => $paiement->getKey(),
            'statut' => Format::enum($paiement->statut),
            'termine' => $paiement->statut !== StatutPaiement::EnAttente,
            'montant' => (int) $paiement->montant,
            'moyen' => $paiement->moyen,
            'paye_le' => Format::date($paiement->paye_le),
            'objet' => match (true) {
                $objet instanceof Reservation => ['type' => 'reservation', 'reference' => $objet->reference],
                $objet instanceof Abonnement => ['type' => 'abonnement', 'id' => $objet->getKey()],
                default => null,
            },
        ];
    }
}
