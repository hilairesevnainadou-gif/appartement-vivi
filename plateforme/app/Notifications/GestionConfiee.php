<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** À la personne choisie comme gestionnaire : logements confiés et façon de se connecter. */
class GestionConfiee extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly User $proprietaire, public readonly Collection $logements) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->proprietaire->name.' vous confie la gestion de logements sur VIVI')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->proprietaire->name.' vous confie l’accueil des clients de : **'.$this->logements->pluck('titre')->implode(', ').'**.')
            ->line('Depuis votre espace, vous verrez les arrivées du jour, le calendrier, et vous scannerez le QR Code des clients.')
            ->line('Connectez-vous avec cette adresse : vous recevrez un code, pas de mot de passe à retenir.')
            ->action('Ouvrir mon espace', route('connexion'))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI : '.$this->proprietaire->name.' vous confie l’accueil de ses clients ('.$this->logements->pluck('titre')->implode(', ')
            .'). Connectez-vous avec ce numéro : '.route('connexion');
    }
}
