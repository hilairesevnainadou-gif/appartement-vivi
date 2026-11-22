<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Parametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** La veille de l'arrivée : rappel de l'heure, de l'adresse et du QR Code, et dernier délai pour reporter. */
class RappelArrivee extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $l = $r->logement;
        $limite = $r->debut->subHours((int) Parametres::get('report_delai_minimum_heures'));

        return (new MailMessage)
            ->subject('À demain · '.$l->titre)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre séjour au **'.$l->titre.'** approche : '.$r->debut->translatedFormat('l j F').' · '.$r->creneauEnClair().'.')
            ->line('**Adresse :** '.$l->adresseComplete().' · [Itinéraire jusqu’au logement]('.$l->lienItineraire().')')
            ->line('Présentez votre QR Code à l’arrivée (code de secours : **'.$r->code_secours.'**).')
            ->line('Un empêchement ? Vous pouvez encore reporter jusqu’au '.$limite->translatedFormat('l j F à H:i').'.')
            ->action('Voir mon QR Code', route('reservation.voir', $r->reference))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return 'VIVI : rappel, '.$r->logement->titre.' '.$r->debut->translatedFormat('D j M').' à '.$r->debut->format('H:i')
            .'. Adresse : '.$r->logement->adresseComplete().'. Code de secours '.$r->code_secours.'. QR Code : '.route('reservation.voir', $r->reference);
    }
}
