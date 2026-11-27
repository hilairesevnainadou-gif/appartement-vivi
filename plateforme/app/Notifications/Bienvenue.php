<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Message de bienvenue après la création d'un compte.
 * Envoyé tout de suite ; à passer en file d'attente (ShouldQueue) quand un
 * « php artisan queue:work » tournera sur le serveur.
 */
class Bienvenue extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Role $role) {}

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Bienvenue sur VIVI')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre compte VIVI est créé.');

        return $this->role === Role::Proprietaire
            ? $message
                ->line(\App\Services\ModeleEconomique::formulesProposees()
                    ? 'Prochaine étape : choisissez un abonnement, puis publiez votre premier logement. VIVI le vérifie avant sa mise en ligne.'
                    : 'Prochaine étape : publiez votre premier logement. VIVI le vérifie avant sa mise en ligne.')
                ->action('Ouvrir mon espace propriétaire', route('espace.proprietaire'))
                ->salutation('L’équipe VIVI')
            : $message
                ->line('Vous pouvez réserver un appartement meublé à l’heure, à la demi-journée ou à la journée.')
                ->action('Trouver un logement', route('accueil'))
                ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return $this->role === Role::Proprietaire
            ? (\App\Services\ModeleEconomique::formulesProposees()
                ? 'Bienvenue sur VIVI ! Choisissez un abonnement puis publiez votre premier logement depuis votre espace propriétaire.'
                : 'Bienvenue sur VIVI ! Publiez votre premier logement depuis votre espace propriétaire.')
            : 'Bienvenue sur VIVI ! Réservez un appartement meublé à l’heure, à la demi-journée ou à la journée.';
    }
}
