<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au client, quand le gérant scanne son QR Code : la réservation est activée, le séjour commence. */
class SejourCommence extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;

        return (new MailMessage)
            ->subject('Bienvenue, votre séjour a commencé · '.$r->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre arrivée au **'.$r->logement->titre.'** est enregistrée : votre réservation est activée.')
            ->line('**Fin du séjour :** '.$r->fin->translatedFormat('l j F').' à '.$r->fin->format('H:i').'.')
            ->line('Bon séjour ! En cas de besoin, contactez le gérant sur place.')
            ->action('Voir ma réservation', route('reservation.voir', $r->reference))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return 'VIVI : bienvenue au '.$r->logement->titre.' ! Séjour '.$r->reference.' commencé, fin le '
            .$r->fin->translatedFormat('D j M').' à '.$r->fin->format('H:i').'. Bon séjour.';
    }
}
