<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** À l'agent marketing : un client venu par son lien a commencé son séjour, sa commission est sur son solde. */
class CommissionAgent extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $sejour, public readonly int $montant) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Commission reçue : '.Montant::fcfa($this->montant))
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un client venu par votre lien a commencé son séjour au **'.$this->sejour->logement->titre.'** ('.$this->sejour->reference.').')
            ->line('**'.Montant::fcfa($this->montant).'** sont crédités sur votre solde. Vous pouvez demander un retrait depuis votre espace agent.')
            ->action('Ouvrir mon espace agent', route('agent'))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI : commission de '.Montant::fcfa($this->montant).' créditée (séjour '.$this->sejour->reference.', '.$this->sejour->logement->titre.').';
    }

    protected function cibleNotification(): array
    {
        return ['ecran' => 'agent', 'reference' => $this->sejour->reference];
    }
}
