<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Le client a annulé : montant remboursé pour lui, créneau libéré pour le propriétaire. */
class ReservationAnnulee extends Notification
{
    use CanauxSelonParametres, Queueable;

    /** $explication : raison de l'annulation quand ce n'est pas le client qui l'a demandée. */
    public function __construct(public readonly Reservation $reservation, public readonly bool $pourProprietaire, public readonly ?string $explication = null) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $message = (new MailMessage)->subject('Réservation annulée · '.$r->reference)->greeting('Bonjour '.$notifiable->name.',');

        if ($this->pourProprietaire) {
            $retenue = (int) $r->montant_total - (int) $r->montant_rembourse;

            return $message->line($r->client->name.' a annulé sa réservation du '.$r->debut->translatedFormat('l j F à H:i').' sur **'.$r->logement->titre.'**.')
                ->line('Le créneau est de nouveau disponible à la réservation.')
                ->line($retenue > 0 ? 'Annulation tardive : la part de location retenue est ajoutée à votre solde.' : 'Annulation dans les délais : aucun montant n’est retenu.')
                ->salutation('L’équipe VIVI');
        }

        $message->line('Votre réservation au **'.$r->logement->titre.'** du '.$r->debut->translatedFormat('l j F').' est annulée.');
        if ($this->explication) {
            $message->line($this->explication);
        }

        return $message
            ->line($r->montant_rembourse > 0
                ? 'Vous serez remboursé de **'.Montant::fcfa($r->montant_rembourse).'** sur votre moyen de paiement, sous 24 h à 7 jours.'
                : 'Aucun remboursement n’est dû pour cette annulation.')
            ->action('Mes réservations', route('espace.client', ['onglet' => 'annulees']))
            ->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->reservation;

        return $this->pourProprietaire
            ? 'VIVI : la réservation '.$r->reference.' du '.$r->debut->translatedFormat('D j M').' sur '.$r->logement->titre.' est annulée. Le créneau est libéré.'
            : 'VIVI : réservation '.$r->reference.' annulée.'.($r->montant_rembourse > 0 ? ' Remboursement de '.Montant::fcfa($r->montant_rembourse).' sous 24 h à 7 jours.' : ' Aucun remboursement dû.');
    }
}
