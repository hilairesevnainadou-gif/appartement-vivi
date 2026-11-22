<?php

namespace App\Notifications;

use App\Models\Logement;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gestion confiée à VIVI : l'administration a désigné un gérant VIVI.
 *  - au propriétaire : qui accueille désormais ses clients ;
 *  - au gérant : la fiche qu'il prend en charge (accueil, scan du QR Code).
 */
class GerantViviDesigne extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Logement $logement, public readonly bool $pourGerant) {}

    public function toMail(object $notifiable): MailMessage
    {
        $l = $this->logement;
        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        return $this->pourGerant
            ? $message->subject('Nouvelle fiche à gérer : '.$l->titre)
                ->line('VIVI vous confie l’accueil des clients de **'.$l->titre.'** ('.$l->quartier.', '.$l->ville.'), propriétaire : '.$l->proprietaire->name.'.')
                ->line('Depuis votre espace, vous voyez les arrivées, le calendrier, et vous scannez le QR Code des clients.')
                ->action('Ouvrir mon espace', route('espace.proprietaire'))
                ->salutation('L’équipe VIVI')
            : $message->subject('VIVI gère désormais '.$l->titre)
                ->line('Votre demande est prise en compte : **'.$l->gerantVivi->name.'**, gérant VIVI, accueille désormais les clients de **'.$l->titre.'** et scanne leur QR Code.')
                ->lineIf((bool) $l->libelleGestion(), '**Rémunération de VIVI :** '.$l->libelleGestion().', selon votre contrat'.($l->gestion_contrat ? ' ('.$l->gestion_contrat.')' : '').'. Elle est retirée de chaque réservation ; le reste est crédité sur votre solde.')
                ->line('Vous suivez les réservations et vos revenus depuis votre espace, comme d’habitude.')
                ->action('Voir la fiche', route('pro.logements.edit', $l))
                ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $l = $this->logement;

        return $this->pourGerant
            ? 'VIVI : vous gérez désormais l’accueil de '.$l->titre.' ('.$l->quartier.'). Arrivées et scan dans votre espace.'
            : 'VIVI : '.$l->gerantVivi->name.', gérant VIVI, accueille désormais les clients de '.$l->titre.'.';
    }

    protected function cibleNotification(): array
    {
        return $this->pourGerant ? ['ecran' => 'pro_accueil'] : ['ecran' => 'pro_logement', 'id' => $this->logement->getKey()];
    }
}
