<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au client, après le paiement : récapitulatif, adresse du logement et accès au QR Code. */
class ReservationConfirmee extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $l = $r->logement;

        return (new MailMessage)
            ->subject('Réservation confirmée · '.$r->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre paiement est reçu : votre réservation au **'.$l->titre.'** est confirmée.')
            ->line('**Quand :** '.$r->debut->translatedFormat('l j F Y').' · '.$r->creneauEnClair())
            ->line('**Adresse :** '.$l->adresseComplete().' · [Itinéraire jusqu’au logement]('.$l->lienItineraire().')')
            ->line('**Total payé :** '.Montant::fcfa($r->montantPaye()).' (commission VIVI comprise)'
                .($r->montant_bonus > 0 ? ', plus '.Montant::fcfa($r->montant_bonus).' réglés avec votre bonus' : ''))
            ->line('À votre arrivée, présentez votre QR Code. Code de secours : **'.$r->code_secours.'**.')
            ->action('Voir mon QR Code', route('reservation.voir', $r->reference))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return 'VIVI : réservation '.$r->reference.' confirmée, '.$r->logement->titre.', '
            .$r->debut->translatedFormat('D j M').' à '.$r->debut->format('H:i').'. Adresse : '.$r->logement->adresseComplete()
            .'. Code de secours '.$r->code_secours.'. QR Code et itinéraire : '.route('reservation.voir', $r->reference);
    }
}
