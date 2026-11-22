<?php

namespace App\Notifications;

use App\Enums\StatutRetrait;
use App\Models\Retrait;
use App\Notifications\Concerns\CanauxSelonParametres;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Au propriétaire ou à l'agent : son retrait est payé, ou refusé (le montant revient sur son solde). */
class RetraitTraite extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Retrait $retrait) {}

    private function paye(): bool
    {
        return $this->retrait->statut === StatutRetrait::Paye;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->retrait;
        $montant = Montant::fcfa((int) $r->montant);
        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        $message = $this->paye()
            ? $message->subject('Retrait payé : '.$montant)
                ->line('Votre retrait **'.$r->reference.'** de **'.$montant.'** est payé'.($r->mode_reglement ? ' par '.$r->mode_reglement : '').'.')
                ->line($r->reference_reglement ? 'Référence du paiement : '.$r->reference_reglement.'.' : 'Vérifiez votre compte de réception.')
            : $message->subject('Retrait refusé : '.$r->reference)
                ->line('Votre retrait **'.$r->reference.'** de **'.$montant.'** n’a pas pu être payé.')
                ->line('Motif : '.($r->note ?: 'non précisé').'. Le montant est de retour sur votre solde.');

        return $message->action('Voir mon solde', $this->lienSolde($notifiable))->salutation('L’équipe VIVI');
    }

    public function toSms(object $notifiable): string
    {
        $r = $this->retrait;

        return $this->paye()
            ? 'VIVI : retrait '.$r->reference.' de '.Montant::fcfa((int) $r->montant).' payé'.($r->mode_reglement ? ' par '.$r->mode_reglement : '').'.'
            : 'VIVI : retrait '.$r->reference.' refusé ('.($r->note ?: 'voir votre espace').'). Le montant est revenu sur votre solde.';
    }

    private function lienSolde(object $notifiable): string
    {
        return method_exists($notifiable, 'estAgent') && $notifiable->estAgent() && ! $notifiable->hasRole('proprietaire')
            ? route('agent') : route('pro.revenus');
    }

    protected function cibleNotification(): array
    {
        return ['ecran' => 'solde', 'reference' => $this->retrait->reference];
    }
}
