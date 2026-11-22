<?php

namespace App\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pilote SMS.to (https://sms.to), qui couvre le Bénin.
 * À activer avec VIVI_SMS_PILOTE=smsto et SMSTO_API_KEY dans .env.
 */
class SmsTo implements Sms
{
    public function __construct(
        private readonly string $cle,
        private readonly string $expediteur,
        private readonly string $url,
    ) {}

    public function envoyer(string $numero, string $message): void
    {
        try {
            $reponse = Http::withToken($this->cle)
                ->acceptJson()
                ->timeout(15)
                ->post($this->url, [
                    'message' => $message,
                    'to' => $numero,
                    'sender_id' => $this->expediteur,
                ]);
        } catch (ConnectionException $e) {
            throw new EchecEnvoiSms('SMS.to injoignable : '.$e->getMessage(), previous: $e);
        }

        if ($reponse->failed() || $reponse->json('success') === false) {
            throw new EchecEnvoiSms('SMS.to a refusé l’envoi ('.$reponse->status().') : '.$reponse->body());
        }
    }
}
