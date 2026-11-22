<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Validation\ValidationException;

/**
 * Coordonnées données au moment de payer (site et application) :
 *  - nom ;
 *  - numéro WhatsApp de n'importe quel pays : le gérant peut joindre le client, même étranger ;
 *  - email facultatif (confirmation et reçu).
 * Un numéro béninois devient aussi le téléphone du compte s'il n'en avait pas (QR Code et rappels par SMS).
 */
class CoordonneesClient
{
    /** @return array<string, mixed> règles de validation (« telephone » reste accepté pour les anciennes versions de l'application) */
    public static function regles(): array
    {
        $numero = function (string $attribut, $valeur, $echec) {
            if ($valeur !== null && $valeur !== '' && ! Telephone::whatsapp((string) $valeur)) {
                $echec('Indiquez votre numéro WhatsApp avec l’indicatif du pays, par exemple +229 01 97 00 00 00 ou +33 6 12 34 56 78.');
            }
        };

        return [
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'whatsapp' => ['required_without:telephone', 'nullable', 'string', 'max:30', $numero],
            'telephone' => ['nullable', 'string', 'max:30', $numero],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['whatsapp.required_without' => 'Indiquez un numéro WhatsApp où vous joindre.'];
    }

    /** Enregistre les coordonnées sur le compte et sur la réservation. */
    public function enregistrer(Reservation $reservation, User $client, array $donnees): void
    {
        $whatsapp = Telephone::whatsapp((string) ($donnees['whatsapp'] ?? $donnees['telephone'] ?? ''));

        $client->name = trim($donnees['nom']);
        $client->whatsapp = $whatsapp;
        $beninois = Telephone::beninoisDe($whatsapp);
        if ($beninois && ! $client->telephone && ! User::where('telephone', $beninois)->exists()) {
            $client->telephone = $beninois;
        }
        // Email : enregistré s'il n'en avait pas et qu'aucun autre compte ne l'utilise
        $email = mb_strtolower(trim((string) ($donnees['email'] ?? '')));
        if ($email !== '' && ! $client->email) {
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Cet email est déjà utilisé par un autre compte VIVI.']);
            }
            $client->email = $email;
        }
        $client->save();
        $reservation->update(['whatsapp' => $whatsapp]);
    }

    /** Numéro à proposer dans le formulaire : le dernier WhatsApp donné, sinon le téléphone béninois du compte. */
    public static function suggestion(User $client): ?string
    {
        return Telephone::lisibleInternational($client->whatsapp ?: ($client->telephone ? Telephone::international($client->telephone) : null));
    }
}
