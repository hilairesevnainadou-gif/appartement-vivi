<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au client : son remboursement a été versé. */
class RemboursementVerse extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;

        return (new MailMessage)
            ->subject('Remboursement effectué · '.$r->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Le remboursement de votre réservation annulée **'.$r->reference.'** a été versé : **'.Montant::fcfa($r->montant_rembourse).'** par '.$r->mode_remboursement.'.')
            ->line($r->reference_remboursement ? 'Référence de l’opération : '.$r->reference_remboursement.'.' : 'Selon votre opérateur, il peut apparaître sous quelques heures.')
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return 'VIVI : remboursement de '.Montant::fcfa($r->montant_rembourse).' versé par '.$r->mode_remboursement.' (réservation '.$r->reference.').';
    }
}
