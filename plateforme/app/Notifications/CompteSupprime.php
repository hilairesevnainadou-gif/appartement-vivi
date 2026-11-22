<?php

namespace App\Notifications;

use App\Notifications\Canaux\CanalSms;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dernier message, envoyé à l'adresse ou au numéro qui a servi à confirmer la suppression du compte. */
class CompteSupprime extends Notification
{
    use Queueable;

    public function __construct(public readonly string $canal) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->canal === 'sms' ? CanalSms::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte VIVI est supprimé')
            ->greeting('Bonjour,')
            ->line('Votre compte VIVI et vos données personnelles ont été supprimés, à votre demande.')
            ->line('Seules restent les pièces que la loi nous oblige à conserver, comme les paiements.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, contactez VIVI sans attendre.')
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI : votre compte est supprimé, à votre demande. Si ce n’est pas vous, contactez VIVI.';
    }
}
