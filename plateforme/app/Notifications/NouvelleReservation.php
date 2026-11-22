<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au propriétaire (et à ses gestionnaires) : une réservation payée arrive sur son logement. */
class NouvelleReservation extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $services = $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ');

        $message = (new MailMessage)
            ->subject('Nouvelle réservation · '.$r->logement->titre)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une réservation payée arrive sur **'.$r->logement->titre.'**.')
            ->line('**Quand :** '.$r->debut->translatedFormat('l j F Y').' · '.$r->creneauEnClair())
            ->line('**Client :** '.$r->client->name.$r->loadMissing('logement')->suffixePersonnes())
            ->line('**Location :** '.Montant::fcfa($r->montant_location));
        if ($services !== '') {
            $message->line('**À préparer :** '.$services);
        }

        return $message
            ->line('À l’arrivée, scannez le QR Code du client depuis votre espace pour démarrer le séjour.')
            ->action('Ouvrir mon tableau de bord', route('espace.proprietaire'))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation->loadMissing('logement');

        return 'VIVI : nouvelle réservation '.$r->reference.' sur '.$r->logement->titre.', '
            .$r->debut->translatedFormat('D j M').' à '.$r->debut->format('H:i').($r->personnes() ? ', '.$r->personnes() : '').'.';
    }
}
