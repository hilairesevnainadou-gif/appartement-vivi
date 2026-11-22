<?php

namespace App\Services;

use App\Models\CodeConnexion;
use App\Notifications\CodeDeConnexion;
use App\Support\Telephone;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Codes à usage unique pour la connexion sans mot de passe.
 *
 * - Un code de 6 chiffres, valable quelques minutes, une seule fois.
 * - Seule son empreinte est enregistrée.
 * - Après trop d'essais ratés, il faut en demander un nouveau.
 * - Un nouvel envoi annule les codes précédents du même identifiant.
 */
class CodesConnexion
{
    public const RESULTAT_OK = 'ok';

    public const RESULTAT_FAUX = 'faux';

    public const RESULTAT_EXPIRE = 'expire';

    public const RESULTAT_TROP_ESSAIS = 'trop_essais';

    /**
     * @param  string  $identifiant  email en minuscules ou téléphone normalisé (0197000000)
     * @param  string  $canal  'mail' ou 'sms'
     * @param  string  $motif  'connexion', ou 'suppression' (du compte) : change le texte du message
     * @return string le code envoyé (utile seulement pour l'affichage en local et les tests)
     *
     * @throws RuntimeException si un code vient d'être envoyé à cet identifiant
     */
    public function envoyer(string $identifiant, string $canal, ?string $ip = null, string $motif = 'connexion'): string
    {
        $dernier = CodeConnexion::where('identifiant', $identifiant)->latest('id')->first();
        $attente = (int) config('vivi.connexion.renvoi_secondes');
        if ($dernier && $dernier->created_at->diffInSeconds(now()) < $attente) {
            throw new RuntimeException('Un code vient d’être envoyé. Patientez '.$attente.' secondes avant d’en demander un autre.');
        }

        CodeConnexion::where('identifiant', $identifiant)->whereNull('utilise_le')->update(['utilise_le' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('vivi.connexion.code_validite_minutes');

        CodeConnexion::create([
            'identifiant' => $identifiant,
            'canal' => $canal,
            'empreinte' => self::empreinte($identifiant, $code),
            'expire_le' => now()->addMinutes($minutes),
            'ip' => $ip,
        ]);

        $destination = $canal === 'sms' ? Telephone::international($identifiant) : $identifiant;
        Notification::route($canal === 'sms' ? 'sms' : 'mail', $destination)
            ->notifyNow(new CodeDeConnexion($code, $canal, $minutes, $motif));

        return $code;
    }

    /** Vérifie le code saisi ; en cas de succès il ne pourra plus resservir. */
    public function verifier(string $identifiant, string $code): string
    {
        $enregistrement = CodeConnexion::where('identifiant', $identifiant)
            ->whereNull('utilise_le')
            ->latest('id')
            ->first();

        if (! $enregistrement) {
            return self::RESULTAT_EXPIRE;
        }
        if ($enregistrement->expire_le->isPast()) {
            return self::RESULTAT_EXPIRE;
        }
        if ($enregistrement->tentatives >= (int) config('vivi.connexion.tentatives_max')) {
            return self::RESULTAT_TROP_ESSAIS;
        }

        if (! hash_equals($enregistrement->empreinte, self::empreinte($identifiant, preg_replace('/\D/', '', $code)))) {
            $enregistrement->increment('tentatives');

            return $enregistrement->tentatives >= (int) config('vivi.connexion.tentatives_max')
                ? self::RESULTAT_TROP_ESSAIS
                : self::RESULTAT_FAUX;
        }

        $enregistrement->update(['utilise_le' => now()]);

        return self::RESULTAT_OK;
    }

    /** L'identifiant et la clé de l'application entrent dans l'empreinte : une empreinte volée ne se rejoue pas ailleurs. */
    private static function empreinte(string $identifiant, string $code): string
    {
        return hash_hmac('sha256', $identifiant.'|'.$code, (string) config('app.key'));
    }
}
