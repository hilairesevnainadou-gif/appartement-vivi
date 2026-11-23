<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Bienvenue;
use App\Sms\EchecEnvoiSms;
use App\Support\Parametres;
use App\Support\Telephone;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Connexion et création de compte par code à 6 chiffres, communes au site (ConnexionController, en session)
 * et à l'application mobile (Api\AuthController, avec un jeton de demande).
 * Les erreurs sont levées en ValidationException, sur le champ indiqué, avec un message destiné à la personne.
 */
class Inscriptions
{
    public function __construct(private readonly CodesConnexion $codes, private readonly Parrainage $parrainage) {}

    /**
     * VIVI est international :
     *  - « benin » : numéro béninois (code par SMS) ou email, au choix ;
     *  - « international » : email seulement (pas de SMS hors du Bénin).
     * Sans choix (anciennes versions de l'application), la saisie est reconnue toute seule, comme au Bénin.
     *
     * @return array{0: string, 1: string} identifiant normalisé et canal ('mail' ou 'sms')
     */
    public function analyserIdentifiant(string $saisie, ?string $choix = null): array
    {
        $saisie = trim($saisie);

        if ($choix === 'international' && ! str_contains($saisie, '@')) {
            $this->erreur('Hors du Bénin, indiquez votre adresse email (ex. vous@exemple.com).');
        }

        if (str_contains($saisie, '@')) {
            if (! filter_var($saisie, FILTER_VALIDATE_EMAIL)) {
                $this->erreur('Cette adresse email n’est pas valide.');
            }

            return [mb_strtolower($saisie), 'mail'];
        }

        $telephone = Telephone::normaliser($saisie);
        if (! $telephone) {
            $this->erreur('Indiquez un numéro béninois à 10 chiffres commençant par 01 (ex. 01 97 00 00 00), ou votre adresse email.');
        }

        return [$telephone, 'sms'];
    }

    public function verifierCanalActif(string $canal): void
    {
        if ($canal === 'sms' && ! Parametres::smsActif()) {
            $this->erreur('L’envoi de codes par SMS est désactivé pour le moment : utilisez votre adresse email.');
        }
        if ($canal === 'mail' && ! Parametres::get('notifications_email')) {
            $this->erreur('L’envoi de codes par email est désactivé pour le moment : utilisez votre numéro de téléphone.');
        }
    }

    public function trouverUtilisateur(string $identifiant, string $canal): ?User
    {
        return User::where($canal === 'sms' ? 'telephone' : 'email', $identifiant)->first();
    }

    /**
     * Contrôles avant l'envoi du code : compte existant ou non selon le mode, compte actif, code d'invitation.
     *
     * @return string code d'invitation retenu ('' si aucun)
     */
    public function controlerDemande(string $mode, string $identifiant, string $canal, ?string $invitation): string
    {
        $utilisateur = $this->trouverUtilisateur($identifiant, $canal);

        if ($mode === 'connexion' && ! $utilisateur) {
            $this->erreur('Aucun compte n’utilise cet identifiant. Vérifiez-le, ou créez un compte.');
        }
        if ($mode === 'inscription' && $utilisateur) {
            $this->erreur('Un compte existe déjà avec cet identifiant : connectez-vous.');
        }
        if ($utilisateur && ! $utilisateur->actif) {
            $this->erreur('Ce compte est désactivé. Contactez VIVI pour le réactiver.');
        }
        $invitation = $this->parrainage->actif() ? trim((string) $invitation) : '';
        if ($invitation !== '' && ! $this->parrainage->parrainDuCode($invitation)) {
            $this->erreur('Ce code d’invitation n’existe pas. Vérifiez-le, ou laissez le champ vide.', 'invitation');
        }

        return $invitation;
    }

    public function envoyer(string $identifiant, string $canal, ?string $ip, string $champ = 'identifiant'): string
    {
        try {
            return $this->codes->envoyer($identifiant, $canal, $ip);
        } catch (RuntimeException $e) {
            if ($e instanceof EchecEnvoiSms) {
                Log::error('Envoi du code de connexion impossible : '.$e->getMessage());
                $this->erreur('Le SMS n’a pas pu être envoyé. Réessayez dans un instant, ou utilisez votre adresse email.', $champ);
            }
            $this->erreur($e->getMessage(), $champ);
        } catch (Throwable $e) {
            Log::error('Envoi du code de connexion impossible : '.$e->getMessage());
            $this->erreur('Le code n’a pas pu être envoyé. Réessayez dans un instant.', $champ);
        }
    }

    /** Vérifie le code ; en cas d'échec, ValidationException sur « code ». */
    public function verifierCode(string $identifiant, string $code, ?string $ip): void
    {
        $resultat = $this->codes->verifier($identifiant, $code);
        if ($resultat !== CodesConnexion::RESULTAT_OK) {
            // Surveillance : codes faux en série depuis une même adresse (tentative de deviner un code)
            app(Surveillance::class)->codeFaux((string) $ip);
            throw ValidationException::withMessages(['code' => match ($resultat) {
                CodesConnexion::RESULTAT_FAUX => 'Ce code ne correspond pas. Vérifiez les 6 chiffres.',
                CodesConnexion::RESULTAT_TROP_ESSAIS => 'Trop d’essais. Demandez un nouveau code.',
                default => 'Ce code a expiré. Demandez un nouveau code.',
            }]);
        }
    }

    /**
     * Après un code juste : compte créé (inscription) ou retrouvé (connexion), canal marqué comme vérifié.
     * Null si le compte n'est plus accessible.
     *
     * @param  array{identifiant: string, canal: string, mode: string, nom: ?string, role: ?string, invitation: ?string}  $attente
     */
    public function finaliser(array $attente): ?User
    {
        $utilisateur = $attente['mode'] === 'inscription'
            ? $this->creerCompte($attente)
            : $this->trouverUtilisateur($attente['identifiant'], $attente['canal']);

        if (! $utilisateur || ! $utilisateur->actif) {
            return null;
        }

        // La personne a prouvé qu'elle reçoit les messages sur ce canal
        $colonne = $attente['canal'] === 'sms' ? 'telephone_verified_at' : 'email_verified_at';
        if (! $utilisateur->{$colonne}) {
            $utilisateur->forceFill([$colonne => now()])->save();
        }

        return $utilisateur;
    }

    /** Code renvoyé à l'écran (site) ou dans la réponse (API) en environnement local seulement, pour tester sans email ni SMS. */
    public function codeAAfficher(string $code): ?string
    {
        return app()->environment('local') && config('vivi.connexion.afficher_code_en_local') ? $code : null;
    }

    /** @param array{identifiant: string, canal: string, nom: ?string, role: ?string, invitation: ?string} $attente */
    private function creerCompte(array $attente): User
    {
        // Le même identifiant a pu être inscrit entre-temps (double clic, deux onglets)
        $existant = $this->trouverUtilisateur($attente['identifiant'], $attente['canal']);
        if ($existant) {
            return $existant;
        }

        $role = Role::from($attente['role'] ?? Role::Client->value);
        $utilisateur = User::create([
            'name' => $attente['nom'],
            'email' => $attente['canal'] === 'mail' ? $attente['identifiant'] : null,
            'telephone' => $attente['canal'] === 'sms' ? $attente['identifiant'] : null,
            'actif' => true,
        ]);
        $utilisateur->assignRole($role->value);
        $this->parrainage->rattacher($utilisateur, $attente['invitation'] ?? null);

        try {
            $utilisateur->notify(new Bienvenue($role));
        } catch (Throwable $e) {
            Log::warning('Message de bienvenue non envoyé à l’utilisateur '.$utilisateur->id.' : '.$e->getMessage());
        }

        return $utilisateur;
    }

    private function erreur(string $message, string $champ = 'identifiant'): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
