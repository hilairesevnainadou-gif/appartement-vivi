<?php

namespace App\Paiement;

use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Support\Telephone;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paiement par FedaPay (Mobile Money MTN, Moov, Celtiis, carte), avec la bibliothèque officielle fedapay/fedapay-php.
 * La page de paiement est hébergée par FedaPay : VIVI ne voit jamais les données bancaires.
 */
class FedaPayPasserelle implements Passerelle
{
    /** Statuts FedaPay qui valent paiement reçu (voir FedaPay\Transaction::$PAID_STATUS). */
    private const PAYES = ['approved', 'transferred'];

    private const ECHECS = ['declined', 'canceled', 'cancelled', 'expired', 'refunded'];

    public function __construct(string $cleSecrete, string $environnement)
    {
        FedaPay::setApiKey(trim($cleSecrete));
        FedaPay::setEnvironment(self::environnement($cleSecrete, $environnement));
    }

    /**
     * « sandbox » ou « live » : le préfixe de la clé secrète fait foi (sk_live_…, sk_sandbox_…). Avec une autre valeur,
     * la bibliothèque n'aurait aucune adresse à appeler et chaque paiement échouerait.
     */
    public static function environnement(string $cleSecrete, string $environnement): string
    {
        return match (true) {
            str_starts_with(trim($cleSecrete), 'sk_live_') => 'live',
            str_starts_with(trim($cleSecrete), 'sk_sandbox_') => 'sandbox',
            in_array($environnement, ['live', 'production'], true) => 'live',
            default => 'sandbox',
        };
    }

    public function nom(): string
    {
        return 'fedapay';
    }

    public function demarrer(Paiement $paiement, string $urlRetour): string
    {
        $payeur = $paiement->payeur;
        $objet = $paiement->payable;
        $description = match (true) {
            $objet instanceof Reservation => 'Réservation '.$objet->reference.' · '.$objet->logement->titre,
            $objet instanceof Abonnement => 'Abonnement VIVI '.$objet->formule->nom.' · un mois',
            default => 'Paiement VIVI n° '.$paiement->getKey(),
        };

        $client = array_filter([
            'firstname' => $payeur ? (string) str($payeur->name)->before(' ') : null,
            'lastname' => $payeur ? ((string) str($payeur->name)->after(' ') ?: null) : null,
            'email' => $payeur?->email,
        ]);
        if ($numero = Telephone::normaliser($payeur?->telephone)) {
            // Numéro béninois à 10 chiffres, sans l'indicatif
            $client['phone_number'] = ['number' => $numero, 'country' => 'bj'];
        }

        try {
            $transaction = Transaction::create(array_filter([
                'description' => mb_substr($description, 0, 250),
                'amount' => $paiement->montant,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $urlRetour,
                'customer' => $client ?: null,
                'custom_metadata' => ['paiement_id' => $paiement->getKey()],
            ]));
            $jeton = $transaction->generateToken();
        } catch (Throwable $e) {
            // Dans le journal : clé refusée (401) ou demande refusée ne se règlent pas en réessayant
            Log::error('FedaPay : création du paiement impossible', [
                'paiement' => $paiement->getKey(), 'environnement' => FedaPay::getEnvironment(),
                'http' => $e instanceof \FedaPay\Error\Base ? $e->getHttpStatus() : null, 'erreur' => $e->getMessage(),
            ]);
            report($e);
            throw new EchecPaiement('Le service de paiement ne répond pas. Réessayez dans un instant.', previous: $e);
        }

        $paiement->forceFill(['reference_passerelle' => (string) $transaction->id])->save();

        return (string) $jeton->url;
    }

    public function consulter(Paiement $paiement): EtatTransaction
    {
        try {
            $transaction = Transaction::retrieve($paiement->reference_passerelle);
        } catch (Throwable $e) {
            report($e);
            throw new EchecPaiement('Impossible de vérifier le paiement pour le moment.', previous: $e);
        }

        $statut = (string) $transaction->status;
        $moyen = match (true) {
            str_contains((string) $transaction->mode, 'mtn') => 'MTN Mobile Money',
            str_contains((string) $transaction->mode, 'moov') => 'Moov Money',
            str_contains((string) $transaction->mode, 'celtiis') || str_contains((string) $transaction->mode, 'sbin') => 'Celtiis Cash',
            str_contains((string) $transaction->mode, 'card') || str_contains((string) $transaction->mode, 'visa') => 'Carte bancaire',
            default => $transaction->mode ? (string) $transaction->mode : null,
        };

        return new EtatTransaction(
            statut: in_array($statut, self::PAYES, true) ? EtatTransaction::PAYEE
                : (in_array($statut, self::ECHECS, true) ? EtatTransaction::ECHOUEE : EtatTransaction::EN_ATTENTE),
            moyen: $moyen,
            donnees: ['id' => $transaction->id, 'status' => $statut, 'mode' => $transaction->mode, 'reference' => $transaction->reference],
        );
    }
}
