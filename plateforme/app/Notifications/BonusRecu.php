<?php

namespace App\Notifications;

use App\Models\Bonus;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au parrain : un ami invité a fait son premier séjour, un bonus est crédité. */
class BonusRecu extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Bonus $bonus) {}

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->bonus;

        return (new MailMessage)
            ->subject('Vous avez reçu '.Montant::fcfa($b->montant).' de bonus')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Merci de nous avoir recommandés : '.$b->libelle.'.')
            ->line('Vous recevez **'.Montant::fcfa($b->montant).'** de bonus, à déduire de votre prochaine réservation avant le '.$b->expire_le->translatedFormat('j F Y').'.')
            ->action('Voir mon bonus', route('parrainage'))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $b = $this->bonus;

        return 'VIVI : '.$b->libelle.'. Vous recevez '.Montant::fcfa($b->montant).' de bonus sur votre prochaine réservation, valable jusqu’au '.$b->expire_le->format('d/m/Y').'.';
    }
}
