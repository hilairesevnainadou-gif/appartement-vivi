<?php

namespace App\Notifications;

use App\Models\Offre;
use App\Notifications\Concerns\CanauxSelonParametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Offre ou annonce de VIVI (Administration › Offres) : espace Notifications et push seulement, jamais par
 * SMS ni email. Envoyée uniquement aux personnes qui acceptent les offres (users.recevoir_offres).
 */
class OffreVivi extends Notification
{
    use CanauxSelonParametres, Queueable;

    public function __construct(public readonly Offre $offre) {}

    protected function parEmailEtSms(): bool
    {
        return false;
    }

    /** Sert seulement à construire le contenu de l'espace Notifications (titre, texte, bouton). */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->offre->titre)->line($this->offre->texte);

        return $this->offre->lien ? $message->action('Voir l’offre', $this->offre->lien) : $message->action('Trouver un logement', route('accueil'));
    }

    protected function cibleNotification(): array
    {
        return ['ecran' => 'offre', 'id' => $this->offre->getKey()];
    }
}
