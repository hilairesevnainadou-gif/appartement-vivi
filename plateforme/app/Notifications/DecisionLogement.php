<?php

namespace App\Notifications;

use App\Enums\StatutLogement;
use App\Models\Logement;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au propriétaire : VIVI a publié, refusé ou suspendu son logement. */
class DecisionLogement extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Logement $logement) {}

    public function toMail(object $notifiable): MailMessage
    {
        $l = $this->logement;
        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        $message = match ($l->statut) {
            StatutLogement::Publie => $message->subject('Logement publié : '.$l->titre)
                ->line('Bonne nouvelle : **'.$l->titre.'** est vérifié et visible dans la recherche VIVI.')
                ->line('Les clients peuvent maintenant le réserver.'),
            StatutLogement::Suspendu => $message->subject('Logement suspendu : '.$l->titre)
                ->line('**'.$l->titre.'** n’apparaît plus dans la recherche. Motif : '.($l->motif_refus ?: 'non précisé').'.')
                ->line('Les réservations déjà payées restent valables.'),
            default => $message->subject('Logement à corriger : '.$l->titre)
                ->line('**'.$l->titre.'** n’a pas été validé. Motif : '.($l->motif_refus ?: 'non précisé').'.')
                ->line('Corrigez la fiche puis renvoyez-la pour vérification.'),
        };

        return $message->action('Ouvrir la fiche', route('pro.logements.edit', $l))->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $l = $this->logement;

        return match ($l->statut) {
            StatutLogement::Publie => 'VIVI : votre logement '.$l->titre.' est publié et réservable.',
            StatutLogement::Suspendu => 'VIVI : votre logement '.$l->titre.' est suspendu ('.($l->motif_refus ?: 'voir votre espace').').',
            default => 'VIVI : votre logement '.$l->titre.' est à corriger ('.($l->motif_refus ?: 'voir votre espace').').',
        };
    }

    protected function cibleNotification(): array
    {
        return ['ecran' => 'pro_logement', 'id' => $this->logement->getKey()];
    }
}
