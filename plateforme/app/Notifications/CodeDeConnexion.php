<?php

namespace App\Notifications;

use App\Notifications\Canaux\CanalSms;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code à 6 chiffres pour se connecter, créer son compte ou le supprimer.
 * Envoyé sur le canal de l'identifiant saisi : email → email, téléphone → SMS.
 * Envoyé tout de suite (pas de file d'attente) : le client attend son code.
 */
class CodeDeConnexion extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $canal,      // 'mail' ou 'sms'
        public readonly int $validiteMinutes,
        public readonly string $motif = 'connexion',   // ou 'suppression' (du compte)
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->canal === 'sms' ? CanalSms::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->motif === 'suppression') {
            return (new MailMessage)
                ->subject('Suppression de votre compte VIVI : '.$this->code)
                ->greeting('Bonjour,')
                ->line('Voici votre code pour **supprimer définitivement** votre compte VIVI :')
                ->line('**'.$this->code.'**')
                ->line('Il est valable '.$this->validiteMinutes.' minutes et ne sert qu’une fois.')
                ->line('Si vous n’avez rien demandé, ne communiquez ce code à personne et contactez VIVI.')
                ->salutation('L’équipe VIVI');
        }

        return (new MailMessage)
            ->subject('Votre code de connexion VIVI : '.$this->code)
            ->greeting('Bonjour,')
            ->line('Voici votre code pour vous connecter à VIVI :')
            ->line('**'.$this->code.'**')
            ->line('Il est valable '.$this->validiteMinutes.' minutes et ne sert qu’une fois.')
            ->line('Si vous n’avez rien demandé, ignorez ce message : personne ne peut se connecter sans ce code.')
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        if ($this->motif === 'suppression') {
            return 'VIVI : code pour SUPPRIMER votre compte : '.$this->code.'. Valable '.$this->validiteMinutes.' min. Ne le communiquez à personne.';
        }

        return 'VIVI : votre code de connexion est '.$this->code.'. Valable '.$this->validiteMinutes.' min. Ne le communiquez à personne.';
    }
}
