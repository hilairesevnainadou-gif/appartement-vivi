<?php

namespace App\Notifications;

use App\Models\Publicite;
use App\Models\User;
use App\Support\Montant;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * À l'annonceur : vues, clics et taux de clic de sa publicité, sur la période et depuis le début,
 * dans l'application et sur le site web. Par email (et dans l'espace Notifications s'il a un compte VIVI).
 */
class RapportPublicite extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Publicite $publicite,
        public readonly CarbonInterface $du,
        public readonly CarbonInterface $au,
        public readonly bool $final = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = $notifiable instanceof User && $notifiable->exists ? ['database'] : [];
        if ($notifiable->routeNotificationFor('mail', $this)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->publicite;
        $periode = $p->statistiques($this->du, $this->au);
        $total = $p->statistiques();
        $nombre = fn (int $n) => number_format($n, 0, ',', ' ');
        $taux = fn (float $t) => number_format($t, 1, ',', '').' %';
        $dates = 'du '.$this->du->format('d/m/Y').' au '.$this->au->format('d/m/Y');

        $message = (new MailMessage)
            ->subject(($this->final ? 'Bilan final' : 'Rapport').' de votre publicité « '.$p->titre.' »')
            ->greeting('Bonjour'.($notifiable instanceof User ? ' '.$notifiable->name : ($p->annonceur ? ' '.$p->annonceur : '')).',')
            ->line(($this->final ? 'La diffusion de votre publicité **'.$p->titre.'** est terminée. Voici son bilan.' : 'Voici les résultats de votre publicité **'.$p->titre.'** '.$dates.'.'))
            ->line('**Sur la période ('.$dates.')** : '.$nombre($periode['vues']).' vues, '.$nombre($periode['clics']).' clics, taux de clic '.$taux($periode['taux']).'.')
            ->line('Application : '.$nombre($periode['vues_application']).' vues, '.$nombre($periode['clics_application']).' clics · Site web : '
                .$nombre($periode['vues_site']).' vues, '.$nombre($periode['clics_site']).' clics.')
            ->line('**Depuis le début** : '.$nombre($total['vues']).' vues, '.$nombre($total['clics']).' clics, taux de clic '.$taux($total['taux']).'.')
            ->line('Emplacements : '.$p->espacesEnClair().'.')
            ->line('Diffusion : '.($p->debut ? 'du '.$p->debut->format('d/m/Y') : 'depuis sa création').($p->fin ? ' au '.$p->fin->format('d/m/Y') : ', sans date de fin').' · '.$p->etatEnClair().'.')
            ->lineIf((int) $p->prix > 0 && $p->resteAPayer() > 0, 'Reste à régler : '.Montant::fcfa($p->resteAPayer()).' sur '.Montant::fcfa((int) $p->prix).'.');

        return ($p->lien ? $message->action('Voir la publicité', $p->lien) : $message)
            ->line('Une question ou une nouvelle campagne ? Répondez simplement à cet email.')
            ->salutation('L’équipe VIVI');
    }

    /** Espace Notifications du site, pour un annonceur qui a un compte. */
    public function toDatabase(object $notifiable): array
    {
        $s = $this->publicite->statistiques($this->du, $this->au);

        return [
            'format' => 'vivi',
            'titre' => ($this->final ? 'Bilan final' : 'Rapport').' de votre publicité « '.$this->publicite->titre.' »',
            'texte' => number_format($s['vues'], 0, ',', ' ').' vues et '.number_format($s['clics'], 0, ',', ' ').' clics du '
                .$this->du->format('d/m/Y').' au '.$this->au->format('d/m/Y').'.'.($notifiable->email ? ' Le détail vous a été envoyé par email.' : ''),
            'lien' => $this->publicite->lien,
            'action' => $this->publicite->lien ? 'Voir la publicité' : null,
            'icone' => 'bi-bar-chart-line',
            'categorie' => 'compte',
            'cible' => ['ecran' => 'notifications'],
        ];
    }
}
