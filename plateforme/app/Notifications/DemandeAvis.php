<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Après le départ : invitation à noter le logement. */
class DemandeAvis extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;

        return (new MailMessage)
            ->subject('Comment s’est passé votre séjour ?')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Merci d’avoir séjourné au **'.$r->logement->titre.'** avec VIVI.')
            ->line('Votre note aide les prochains clients et le propriétaire. Cela prend quelques secondes.')
            ->action('Donner mon avis', route('reservation.voir', $r->reference).'#avis')
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI : merci pour votre séjour au '.$this->reservation->logement->titre.' ! Donnez votre avis : '
            .route('reservation.voir', $this->reservation->reference);
    }
}
