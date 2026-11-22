<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** À la personne choisie comme agent marketing : logements à promouvoir et façon d'obtenir ses liens. */
class AgentDesigne extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly User $proprietaire, public readonly Collection $logements) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->proprietaire->name.' vous choisit comme agent marketing sur VIVI')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->proprietaire->name.' vous confie la promotion de : **'.$this->logements->pluck('titre')->implode(', ').'**.')
            ->line('Dans votre espace agent, vous trouverez le lien de chaque logement. Quand un client réserve par votre lien, votre commission est versée sur votre solde VIVI au début de son séjour.')
            ->line('Connectez-vous avec cette adresse : vous recevrez un code, pas de mot de passe à retenir.')
            ->action('Ouvrir mon espace agent', route('connexion'))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI : '.$this->proprietaire->name.' vous choisit comme agent marketing ('.$this->logements->pluck('titre')->implode(', ')
            .'). Vos liens à partager : '.route('connexion');
    }
}
