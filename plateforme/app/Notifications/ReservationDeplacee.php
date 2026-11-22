<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au client : le propriétaire (ou VIVI) a déplacé sa réservation. Son QR Code reste valable. */
class ReservationDeplacee extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;

        return (new MailMessage)
            ->subject('Réservation déplacée · '.$r->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre réservation au **'.$r->logement->titre.'** a été déplacée.')
            ->line('**Nouvelle date :** '.$r->debut->translatedFormat('l j F Y').' · '.$r->creneauEnClair())
            ->line('Votre QR Code et votre code de secours restent les mêmes.')
            ->action('Voir ma réservation', route('reservation.voir', $r->reference))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return 'VIVI : votre réservation '.$r->reference.' ('.$r->logement->titre.') est déplacée au '
            .$r->debut->translatedFormat('D j M').' à '.$r->debut->format('H:i').'. Même QR Code.';
    }
}
