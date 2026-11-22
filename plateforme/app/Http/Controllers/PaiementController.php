<?php

namespace App\Http\Controllers;

use App\Enums\StatutPaiement;
use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Paiement\EchecPaiement;
use App\Paiement\EtatTransaction;
use App\Paiement\Passerelle;
use App\Services\Abonnements;
use App\Services\Reservations;
use FedaPay\Error\SignatureVerification;
use FedaPay\Webhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use UnexpectedValueException;

/**
 * Retour du client après la page de paiement, webhook FedaPay et page de paiement simulée (local).
 * Le statut d'un paiement est toujours relu chez le prestataire : les paramètres de l'adresse de retour
 * (?status=approved) ne sont jamais crus sur parole.
 */
class PaiementController extends Controller
{
    public function __construct(
        private readonly Reservations $reservations,
        private readonly Abonnements $abonnements,
        private readonly Passerelle $passerelle,
    ) {}

    /** Le client (ou le propriétaire, pour un abonnement) revient de la page de paiement. */
    public function retour(Request $request, Paiement $paiement): View|RedirectResponse
    {
        $objet = $paiement->payable;
        abort_unless($objet instanceof Reservation || $objet instanceof Abonnement, 404);

        if ($paiement->statut === StatutPaiement::EnAttente) {
            try {
                $this->appliquer($paiement, $this->passerelle->consulter($paiement));
            } catch (EchecPaiement) {
                // Prestataire injoignable : le webhook confirmera ; la page d'attente se recharge
            }
            $paiement->refresh();
        }

        if ($objet instanceof Abonnement) {
            return match ($paiement->statut) {
                StatutPaiement::Reussi => redirect()->route('pro.abonnement')->with('statut', 'Paiement reçu : votre abonnement '.$objet->formule->nom.' est actif.'),
                StatutPaiement::Echoue => redirect()->route('pro.abonnement')->withErrors(['abonnement' => 'Le paiement n’a pas abouti. Aucun montant n’a été débité : vous pouvez réessayer.']),
                default => view('reservation.attente', ['reservation' => null, 'paiement' => $paiement]),
            };
        }

        return $this->retourReservation($objet, $paiement);
    }

    private function retourReservation(Reservation $reservation, Paiement $paiement): View|RedirectResponse
    {
        return match ($paiement->statut) {
            StatutPaiement::Reussi => redirect()->route('reservation.voir', $reservation->reference)->with('confirmee', true),
            StatutPaiement::Echoue => redirect()->route('reservation.paiement', $reservation->reference)
                ->withErrors(['paiement' => 'Le paiement n’a pas abouti. Aucun montant n’a été débité : vous pouvez réessayer.']),
            default => view('reservation.attente', ['reservation' => $reservation, 'paiement' => $paiement]),
        };
    }

    /** Notification de FedaPay (Développeurs > Webhooks). Signature vérifiée avec la bibliothèque officielle. */
    public function webhook(Request $request): Response
    {
        $cle = (string) config('vivi.paiement.fedapay.cle_webhook');
        if ($cle === '') {
            Log::warning('Webhook FedaPay reçu mais FEDAPAY_WEBHOOK_SECRET est vide : ignoré.');

            return response('Webhook non configuré', 503);
        }

        try {
            $evenement = Webhook::constructEvent($request->getContent(), (string) $request->header('X-FEDAPAY-SIGNATURE'), $cle);
        } catch (SignatureVerification|UnexpectedValueException) {
            // Appel qui ne vient pas de FedaPay : alerte critique (tentative de faux paiement)
            app(\App\Services\Surveillance::class)->webhookInvalide((string) $request->ip());

            return response('Signature invalide', 400);
        }

        $identifiant = (string) ($evenement->entity->id ?? '');
        $paiement = $identifiant !== '' ? Paiement::where('passerelle', 'fedapay')->where('reference_passerelle', $identifiant)->first() : null;
        if ($paiement && $paiement->statut === StatutPaiement::EnAttente) {
            try {
                // On relit la transaction plutôt que de se fier au contenu de l'événement
                $this->appliquer($paiement, $this->passerelle->consulter($paiement));
            } catch (Throwable $e) {
                report($e);

                return response('Erreur temporaire', 500);   // FedaPay renverra l'événement
            }
        }

        return response('OK', 200);
    }

    /** Page de paiement simulée (local seulement) : « Paiement réussi » ou « Paiement refusé ». */
    public function simulation(Paiement $paiement): View
    {
        abort_if(app()->isProduction() || $paiement->passerelle !== 'simulation', 404);

        return view('reservation.simulation', ['paiement' => $paiement, 'reservation' => $paiement->payable]);
    }

    public function simuler(Request $request, Paiement $paiement): RedirectResponse
    {
        abort_if(app()->isProduction() || $paiement->passerelle !== 'simulation', 404);
        $choix = $request->validate([
            'choix' => ['required', 'in:reussi,refuse'],
            'moyen' => ['required', 'in:'.implode(',', config('vivi.paiement.moyens'))],
        ]);
        $paiement->update(['donnees' => array_merge($paiement->donnees ?? [], ['simulation' => $choix['choix'], 'moyen' => $choix['moyen']])]);

        // Adresse de retour donnée au lancement (site ou application mobile), si elle est bien sur ce site
        $retour = (string) ($paiement->donnees['retour'] ?? '');

        return str_starts_with($retour, url('/').'/') ? redirect()->to($retour) : redirect()->route('paiement.retour', $paiement);
    }

    /** Applique le statut lu chez le prestataire, selon ce qui est payé (réservation ou abonnement). */
    private function appliquer(Paiement $paiement, EtatTransaction $etat): void
    {
        $abonnement = $paiement->payable_type === 'abonnement';
        if ($etat->estPayee()) {
            $abonnement ? $this->abonnements->activer($paiement, $etat) : $this->reservations->confirmer($paiement, $etat);
        } elseif ($etat->estEchouee()) {
            $abonnement ? $this->abonnements->echouer($paiement, $etat) : $this->reservations->echouer($paiement, $etat);
        }
    }
}
