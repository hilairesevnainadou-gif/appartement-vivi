<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Le client a reporté sa réservation : nouveau QR Code pour lui, nouvelle date pour le propriétaire. */
class ReservationReportee extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation, public readonly bool $pourProprietaire) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $quand = $r->debut->translatedFormat('l j F Y').' · '.$r->creneauEnClair();
        $message = (new MailMessage)->subject('Réservation reportée · '.$r->reference)->greeting('Bonjour '.$notifiable->name.',');

        return $this->pourProprietaire
            ? $message->line($r->client->name.' a reporté sa réservation sur **'.$r->logement->titre.'**.')
                ->line('**Nouvelle date :** '.$quand)
                ->action('Voir le calendrier', route('pro.calendrier', ['logement' => $r->logement_id, 'jour' => $r->debut->toDateString()]))
                ->salutation('L’équipe VIVI')
            : $message->line('Votre réservation au **'.$r->logement->titre.'** est reportée.')
                ->line('**Nouvelle date :** '.$quand)
                ->line('Un nouveau QR Code a été créé : l’ancien n’est plus valable. Nouveau code de secours : **'.$r->code_secours.'**.')
                ->action('Voir mon nouveau QR Code', route('reservation.voir', $r->reference))
                ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;
        $quand = $r->debut->translatedFormat('D j M').' à '.$r->debut->format('H:i');

        return $this->pourProprietaire
            ? 'VIVI : la réservation '.$r->reference.' ('.$r->logement->titre.') est reportée au '.$quand.'.'
            : 'VIVI : réservation '.$r->reference.' reportée au '.$quand.'. Nouveau code de secours '.$r->code_secours.'. QR Code : '.route('reservation.voir', $r->reference);
    }
}
